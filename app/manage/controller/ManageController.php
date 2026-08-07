<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class ManageController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;
    public function index(){

    $this->checkManageSession();

    	$this->pageText=array("账户管理","管理员列表");
    	$where[] = "1";
        $baseUrl = "index.php?r=manage/manage/index";
        $page = obj('api/ApiData')->page("50", "yun_manage", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->display();
    }

    public function addManage(){


    $this->checkManageSession();

    	if(!\IS_POST){
    		$this->pageText=array("账户管理","添加账户");
    		$this->display();
    		exit;
    	}else{
             if(!$this->arg("username")){
                exit(json_encode(array("info" => "请填写用户名", "status" => "n")));
             }
             if(!$this->arg("password")){
                exit(json_encode(array("info" => "请填写密码", "status" => "n")));
             }

             $data['username']=$this->arg("username");
             $data['password']= md5($this->arg('password') . 'yun_manage');
             $data['pic']=$this->arg("pic");
             $data['nickname']=$this->arg("nickname");

            obj("api/ApiData")->insertData("yun_manage",$data);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));
    	}
    }

   public function editManage(){


   $this->checkManageSession();

     if(!\IS_POST){
        $id=intval($this->arg("id"));
        $where['id'] = $id;
        $ret=obj("api/ApiData")->dataSelect("yun_manage",$where);
        if(empty($ret)){
            exit("Error:002");
        }
        $this->ret=$ret;
        $this->pageText=array("账户管理","编辑管理账号");
        $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
        $this->display('app/manage/view/manage/addmanage');
    }else{
          if(!$this->arg("username")){
                exit(json_encode(array("info" => "请填写用户名", "status" => "n")));
             }
             if($this->arg("password")!=''){
                 $data['password']= md5($this->arg('password') . 'yun_manage');
             }

            $data['username']=$this->arg("username");
            $data['pic']=$this->arg("pic");
            $data['nickname']=$this->arg("nickname");
            $id=intval($this->arg("id"));
            $where['id'] = $id;
            $ret=obj("api/ApiData")->dataSelect("yun_manage",$where);
            obj("api/ApiData")->dataUpdate("yun_manage",$data,$where);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));
    }
   }

   public function deleteManage(){


   $this->checkManageSession();

       error_reporting(0);
        $id=intval($this->arg("id"));
        if($id==1){
              exit(json_encode(array("info" => "创始人不能删除", "status" => "n")));
        }
        $where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_manage', $where, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));


   }

   /**
    * 编辑管理员（视图 manage/index.html 调用 manage/manage/edit）
    * 转发到 editManage
    */
   public function edit(){
       return $this->editManage();
   }

   /**
    * 修改密码（视图 manage/index.html 调用 manage/manage/setpass）
    * 复用编辑表单（含密码框），转发到 editManage
    */
   public function setpass(){
       return $this->editManage();
   }

   /**
    * 删除管理员（视图 manage/index.html 调用 manage/manage/del）
    * 转发到 deleteManage
    */
   public function del(){
       return $this->deleteManage();
   }

   /**
    * 修改当前登录管理员资料（右上角头像 -> 修改资料）
    * 可修改：昵称、头像，以及（可选）账号密码
    */
   public function profile(){
       $this->checkManageSession();
       $uid = intval($_SESSION['manage_uid'] ?? 0);
       if(!\IS_POST){
           $where['id'] = $uid;
           $ret = obj("api/ApiData")->dataSelect("yun_manage", $where);
           if(empty($ret)){
               exit("Error:002");
           }
           $this->ret = $ret;
           $this->pageText = array("个人中心","修改资料");
           $this->display('app/manage/view/manage/profile');
           exit;
       }

       // 保存
       try {
           $data = array();
           $nickname = trim($this->arg("nickname"));
           $username = trim($this->arg("username"));
           if($username === ''){
               exit(json_encode(array("info" => "请填写登录账号", "status" => "n")));
           }
           $data['username'] = $username;
           $data['nickname'] = $nickname;
           $data['pic'] = $this->arg("pic");

           $oldpass = $this->arg("oldpass");
           $newpass = $this->arg("newpass");
           $confirmpass = $this->arg("confirmpass");
           if($newpass !== ''){
               if($oldpass === ''){
                   exit(json_encode(array("info" => "请输入当前密码", "status" => "n")));
               }
               $where['id'] = $uid;
               $row = obj("api/ApiData")->dataSelect("yun_manage", $where);
               if(empty($row) || $row['password'] !== md5($oldpass . 'yun_manage')){
                   exit(json_encode(array("info" => "当前密码不正确", "status" => "n")));
               }
               if($newpass !== $confirmpass){
                   exit(json_encode(array("info" => "两次输入的新密码不一致", "status" => "n")));
               }
               $data['password'] = md5($newpass . 'yun_manage');
           }

           $where['id'] = $uid;
           obj("api/ApiData")->dataUpdate("yun_manage", $data, $where);

           // 同步更新 session 中的昵称与头像
           $_SESSION['manage_system'] = $username;
           $_SESSION['manage_nickname'] = $nickname;
           $_SESSION['manage_pic'] = $data['pic'];

           exit(json_encode(array("info" => "资料修改成功", "status" => "y")));
       } catch (\Throwable $e) {
           exit(json_encode(array("info" => "保存失败：" . $e->getMessage(), "status" => "n")));
       }
   }
}