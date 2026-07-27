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

    	if(!IS_POST){
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

            obj("api/ApiData")->insertData("yun_manage",$data);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));
    	}
    }

   public function editManage(){


   $this->checkManageSession();

     if(!IS_POST){
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
}