<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class UserController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;
    public function index(){
        $this->checkManageSession();
        $this->pageText=array("用户管理","用户列表");
        $where[] = "1";
        $baseUrl = "index.php?r=manage/user/index";
        $page = obj('api/ApiData')->page("50", "yun_user", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->display();
    }
    public function addUser(){
        $this->checkManageSession();
        if(!\IS_POST){
            $this->pageText=array("用户管理","添加用户");
            $this->display();
            exit;
        }else{
            if(!$this->arg("username")){
                exit(json_encode(array("info" => "请填写用户名", "status" => "n")));
            }
            if(!$this->arg("password")){
                exit(json_encode(array("info" => "请填写密码", "status" => "n")));
            }
            if(!$this->arg("mobile")){
                exit(json_encode(array("info" => "请填写手机号", "status" => "n")));
            }
            $username = $this->arg("username");
            $where['username'] = $username;
            $exists = obj("api/ApiData")->dataSelect("yun_user",$where);
            if($exists){
                exit(json_encode(array("info" => "用户名已存在", "status" => "n")));
            }
            $data['username']=$username;
            $data['password']= md5($this->arg('password') . 'zhicms');
            $data['mobile']=$this->arg("mobile");
            $data['vest']=intval($this->arg("vest", 1));
            $data['lock']=0;
            $data['date']=date('Y-m-d H:i:s');
            obj("api/ApiData")->insertData("yun_user",$data);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));
        }
    }
    public function editorUser(){
        $this->checkManageSession();
        if(!\IS_POST){
            $id=intval($this->arg("id"));
            $where['id'] = $id;
            $ret=obj("api/ApiData")->dataSelect("yun_user",$where);
            if(empty($ret)){
                exit("Error:002");
            }
            $this->ret=$ret;
            $this->pageText=array("用户管理","编辑用户");
            $this->html='<input type="hidden" name="id" value="' . $ret['id'] . '" />';
            $this->display('app/manage/view/user/adduser');
        }else{
            if(!$this->arg("username")){
                exit(json_encode(array("info" => "请填写用户名", "status" => "n")));
            }
            if(!$this->arg("mobile")){
                exit(json_encode(array("info" => "请填写手机号", "status" => "n")));
            }
            if($this->arg("password")!=''){
                $data['password']= md5($this->arg('password') . 'zhicms');
            }
            $data['username']=$this->arg("username");
            $data['mobile']=$this->arg("mobile");
            $data['vest']=intval($this->arg("vest", 1));
            $id=intval($this->arg("id"));
            $where['id'] = $id;
            obj("api/ApiData")->dataUpdate("yun_user",$data,$where);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));
        }
    }
    public function deleteUser(){
        $this->checkManageSession();
        error_reporting(0);
        $id=intval($this->arg("id"));
        $where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_user', $where, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }
    public function lockUser(){
        $this->checkManageSession();
        $id=intval($this->arg("id"));
        $lock=intval($this->arg("lock"));
        $where['id'] = $id;
        $data['lock']=$lock;
        obj("api/ApiData")->dataUpdate("yun_user",$data,$where);
        if($lock==1){
            exit(json_encode(array("info" => "冻结成功", "status" => "y")));
        }else{
            exit(json_encode(array("info" => "解冻成功", "status" => "y")));
        }
    }
}