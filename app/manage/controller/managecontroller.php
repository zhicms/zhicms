<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class manageController extends \app\base\controller\BaseController
{
    public function index()
    {
    	$this->pagetext=array("账户管理","管理员列表");
    	$where[] = "1";
        $baseurl = "index.php?r=manage/manage/index";
        $Page = obj('api/ApiData')->page("50", "manage", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
        $this->display();
    }

    //添加管理员
    public function addmanage(){
    	if(!IS_POST){
    		$this->pagetext=array("账户管理","添加账户");
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
           
            obj("api/Apidata")->Inserts("manage",$data);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));
    	}
    }
   
   //编辑管理员
   public function editormanage(){
     if(!IS_POST){
        $id=$this->arg("id");
        $where[]="  `id` ={$id} ";
        $ret=obj("api/Apidata")->Data_Select("manage",$where);
        if(empty($ret)){
            exit("Error:002");
        }
        $this->ret=$ret;
        $this->pagetext=array("账户管理","编辑管理账号");
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
            $id=$this->arg("id");
            $where[]="  `id` ={$id} ";
            $ret=obj("api/Apidata")->Data_Select("manage",$where);
            obj("api/Apidata")->Data_Updata("manage",$data,$where);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));   
    }
   }

   public function deletemanage(){
       error_reporting('0');
        $id=$this->arg("id");
        if($id=="1"){
              exit(json_encode(array("info" => "创始人不能删除", "status" => "n")));
        }
        $where = " `id` ={$id}";
        obj('api/ApiData')->Deletethis('manage', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));


   }
}