<?php
namespace app\index\controller;
obj('api/Api')->isCookies('ZhiCmsUser','index.php?r=index/index/login');

class UcenterController extends \app\base\controller\BaseController {
  
  public function index(){
    $uInfo=obj("index/global","controller")->findUser("y",$_COOKIE['ZhiCmsUser'],"cookie");
     if(!\IS_POST){
       $type=$this->arg("type");
      if(!$type){
      
        $where[] = "`yun_like`.fid=`yun_forum`.id and `yun_like`.uid={$uInfo['id']} and  `yun_like`.`model` LIKE  'index'";
        $baseUrl = "ucenter.html";
        $page = obj('api/ApiData')->pageIndex("50", "yun_like`, `yun_forum", $where, "`yun_forum`.`id` DESC", $baseUrl);
        $this->page=$page;
    }
    if($type=='article'){

        $where[] = "`yun_like`.fid=`yun_article`.id and `yun_like`.uid={$uInfo['id']} and  `yun_like`.`model` LIKE  'article'";
        $baseUrl = "ucenter.html?type=article";
        $page = obj('api/ApiData')->pageIndex("50", "yun_like`, `yun_article", $where, "`yun_article`.`id` DESC", $baseUrl);
        $this->page=$page;

    }
       $this->uInfo=$uInfo;
       $this->display();
       exit;
     }else{

        $userName=$this->arg("username");
        $password=$this->arg("password");
        if(!$userName){
          exit(json_encode(array("info" => '请输入您的昵称', "status" => "n")));
        }
        if(!$password){
          exit(json_encode(array("info" => '请输入您的密码', "status" => "n")));
        }
        $nickSql[] = "  `username` LIKE  '{$userName}'";
        $nickData = obj('api/ApiData')->dataSelect("yun_user", $nickSql);
        if (!empty($nickData)) {
              exit(json_encode(array("info" => '昵称被占用', "status" => "n")));
         }
        $where[]="  `mobile` LIKE  '{$_COOKIE['ZhiCmsUser']}'";
        $data['username'] = $userName;
        $data['password'] = md5($password . 'zhicms');
        obj('api/ApiData')->dataUpdate("yun_user", $data,$where);
        exit(json_encode(array("info" => "修改成功", "status" => "y")));

         
     }
  	
  }



  public function like(){
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
   $uInfo=obj("index/global","controller")->findUser("y",$_COOKIE['ZhiCmsUser'],"cookie");

   $whereLike[]="`fid` ={$id} and `model` LIKE  '{$likeData['model']}' and `uid` ={$uInfo['id']}";
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

    $uInfo=obj("index/global","controller")->findUser("y",$_COOKIE['ZhiCmsUser'],"cookie");
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
