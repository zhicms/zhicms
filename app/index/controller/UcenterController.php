<?php
namespace app\index\controller;

class UcenterController extends \app\base\controller\BaseController {

  /**
   * 登录守卫：直接检查 cookie，避免 obj() 在构造期递归
   */
  private function guardLogin(){
    if (empty($_COOKIE['ZhiCmsUser'])) {
      header('location:index.php?r=index/login/index');
      exit;
    }
  }

  /**
   * 当前登录用户
   */
  private function me(){
    return obj("index/global","controller")->findUser("y",$_COOKIE['ZhiCmsUser'],"cookie");
  }

  /**
   * 用户中心首页（emlog 风格：左侧菜单 + 右侧内容）
   * 默认展示"我的收藏"
   */
  public function index(){
    $this->guardLogin();
    $uInfo = $this->me();
    $this->uInfo = $uInfo;
    $this->menu  = 'like';
    $this->loadLike($uInfo);
    $this->display('app/index/view/ucenter/index');
  }

  /**
   * 个人资料
   */
  public function profile(){
    $this->guardLogin();
    $uInfo = $this->me();
    if ($this->isPost()){
      $email = trim($this->arg('email',''));
      $nick  = trim($this->arg('username',''));
      if ($nick === ''){
        exit(json_encode(array("info" => '请输入昵称', "status" => "n")));
      }
      $exist = obj('api/ApiData')->thisQuery(
        "SELECT `id` FROM `{pre}user` WHERE `username` = ? AND `id` <> ? LIMIT 1",
        array($nick, $uInfo['id'])
      );
      if (!empty($exist[0])){
        exit(json_encode(array("info" => '昵称已被占用', "status" => "n")));
      }
      if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)){
        exit(json_encode(array("info" => '邮箱格式不正确', "status" => "n")));
      }
      $data = array('username' => $nick, 'email' => $email);
      obj('api/ApiData')->executeQuery(
        "UPDATE `{pre}user` SET `username` = ?, `email` = ? WHERE `id` = ?",
        array($nick, $email, $uInfo['id'])
      );
      exit(json_encode(array("info" => '资料已更新', "status" => "y")));
    }
    $this->uInfo = $uInfo;
    $this->menu  = 'profile';
    $this->display('app/index/view/ucenter/index');
  }

  /**
   * 修改密码
   */
  public function pwd(){
    $this->guardLogin();
    $uInfo = $this->me();
    if ($this->isPost()){
      $old = trim($this->arg('oldpwd',''));
      $new = trim($this->arg('newpwd',''));
      $rep = trim($this->arg('repwd',''));
      if ($old === '' || $new === ''){
        exit(json_encode(array("info" => '请填写密码', "status" => "n")));
      }
      if (md5($old . 'zhicms') !== $uInfo['password'] && !password_verify($old, $uInfo['password'])){
        exit(json_encode(array("info" => '原密码错误', "status" => "n")));
      }
      if (strlen($new) < 6){
        exit(json_encode(array("info" => '新密码至少 6 位', "status" => "n")));
      }
      if ($new !== $rep){
        exit(json_encode(array("info" => '两次密码不一致', "status" => "n")));
      }
      // 优先使用 bcrypt 存储；兼容历史 md5 校验已通过
      $newHash = password_hash($new, PASSWORD_BCRYPT);
      obj('api/ApiData')->executeQuery(
        "UPDATE `{pre}user` SET `password` = ? WHERE `id` = ?",
        array($newHash, $uInfo['id'])
      );
      exit(json_encode(array("info" => '密码修改成功', "status" => "y")));
    }
    $this->uInfo = $uInfo;
    $this->menu  = 'pwd';
    $this->display('app/index/view/ucenter/index');
  }

  /**
   * 我的评论
   */
  public function myComment(){
    $this->guardLogin();
    $uInfo = $this->me();
    $where[] = "`yun_comment`.`uid` = " . intval($uInfo['id']);
    $page = obj('api/ApiData')->pageIndex("20", "`yun_comment`, `yun_article`", $where, "`yun_comment`.`id` DESC", "index.php?r=index/ucenter/myComment");
    $this->page = $page;
    $this->uInfo = $uInfo;
    $this->menu  = 'myComment';
    $this->display('app/index/view/ucenter/index');
  }

  /**
   * 我的帖子（微社区）
   */
  public function myForum(){
    $this->guardLogin();
    $uInfo = $this->me();
    $where[] = "`yun_forum`.`uid` = " . intval($uInfo['id']);
    $page = obj('api/ApiData')->pageIndex("20", "yun_forum", $where, "`yun_forum`.`id` DESC", "index.php?r=index/ucenter/myForum");
    $this->page = $page;
    $this->uInfo = $uInfo;
    $this->menu  = 'myForum';
    $this->display('app/index/view/ucenter/index');
  }

  /**
   * 我的收藏（点赞）列表数据
   */
  private function loadLike($uInfo){
    $type = $this->arg("type");
    if ($type == 'article'){
      $where[] = "`yun_like`.fid=`yun_article`.id and `yun_like`.uid=" . intval($uInfo['id']) . " and `yun_like`.`model` LIKE 'article'";
      $baseUrl = "index.php?r=index/ucenter/index&type=article";
      $this->likeModel = 'article';
      $this->page = obj('api/ApiData')->pageIndex("20", "`yun_like`, `yun_article`", $where, "`yun_article`.`id` DESC", $baseUrl);
    } else {
      $where[] = "`yun_like`.fid=`yun_forum`.id and `yun_like`.uid=" . intval($uInfo['id']) . " and `yun_like`.`model` LIKE 'index'";
      $baseUrl = "index.php?r=index/ucenter/index";
      $this->likeModel = 'index';
      $this->page = obj('api/ApiData')->pageIndex("20", "`yun_like`, `yun_forum`", $where, "`yun_forum`.`id` DESC", $baseUrl);
    }
  }

  public function like(){
    $this->guardLogin();
    $this->checkSameOrigin();
    $id=$this->arg("id");
    if(!is_numeric($id)){
      exit('error');
    }
    if($this->arg("model")=='index'){
      $likeTable='yun_like';
      $modelTable='yun_forum';
      $data['model']="index";
      $likeData['model']="index";
    }
    if($this->arg("model")=='article'){
      $likeTable='yun_like';
      $modelTable='yun_article';
      $data['model']="article";
      $likeData['model']="article";
    }
    $uInfo=$this->me();
    $whereLike[]="`fid` ={$id} and `model` LIKE '{$likeData['model']}' and `uid` ={$uInfo['id']}";
    $ret=obj("api/ApiData")->dataSelect($likeTable,$whereLike);
    if(!empty($ret)){
      exit(json_encode(array("info" => "你已经喜欢过了~", "status" => "n")));
    }
    $where[]="`id` ={$id}";
    $view=obj("api/ApiData")->dataSelect($modelTable, $where);
    $new=$view['like']+1;
    $data['like']=$new;
    obj('api/ApiData')->dataUpdate($modelTable, $data,$where);
    $likeData['uid']=$uInfo['id'];
    $likeData['fid']=$id;
    $likeData['date']=date("Y-m-d H:i:s",time());
    obj('api/ApiData')->insertData($likeTable, $likeData);
    exit(json_encode(array("info" => "喜欢成功", "status" => "y")));
  }

  public function comSend(){
    $this->guardLogin();
    $this->checkSameOrigin();
    $body=$_POST['mybody'];
    $preg = "/<script[\s\S]*?<\/script>/i";
    $newBody= preg_replace($preg,"",$body,3);
    if(!$newBody){
      exit(json_encode(array("info" => "请填写评论", "status" => "n")));
    }
    if($this->arg("model")=='index'){
      $model='1';
    }else if($this->arg("model")=='article'){
      $model='2';
    }else{
      exit(json_encode(array("info" => "请勿修改模型~", "status" => "n")));
    }
    $id=$this->arg("id");
    if(!is_numeric($id)){
      exit(json_encode(array("info" => "参数错误", "status" => "n")));
    }
    $uInfo=$this->me();
    if(empty($uInfo)){
      exit(json_encode(array("info" => "请先登录", "status" => "n")));
    }
    $data['uid']=$uInfo['id'];
    $data['mid']=$id;
    $data['content']=$newBody;
    $data['model']=$model;
    $data['date']=date("Y-m-d H:i:s",time());
    obj("api/ApiData")->insertData("yun_comment",$data);
    exit(json_encode(array(
      "info" => "评论成功",
      "status" => "y",
      "username" => $uInfo['username'],
      "content" => $newBody,
      "date" => $data['date']
    )));
  }

}
