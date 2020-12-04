<?php
namespace app\manage\controller;

class LoginController extends \app\base\controller\BaseController {

	public function index(){
	   if(!IS_POST){

	  	//echo md5("zhangyuan"."yun_manage");
	   	$this->display();
	    exit;
	   }else{
	   	 $userName = $this->arg('username');
         $passWord = md5($this->arg('password') . 'yun_manage');
        if (!$userName) {
            echo json_encode(array("info" => "请填写用户名", "status" => "n"));
            exit;
        } elseif (!$this->arg('password')) {
            echo json_encode(array("info" => "请填写密码", "status" => "n"));
            exit;
        }else{
            $where[] = " `username` LIKE '{$userName}' AND `password` LIKE '{$passWord}'";
            $user = obj('api/ApiData')->Data_Select('manage', $where);
            if (!empty($user)) {
                $_SESSION['manage_system'] = $userName;
                $_SESSION['manage_uid']=$user['id'];
                $_SESSION['manage_pic']=$user['pic'];
                echo json_encode(array("info" => "登录成功", "status" => "y"));
            } else {
                echo json_encode(array("info" => "账号或密码错误", "status" => "n"));
                exit;
            }
        }

	   }
	}


    public function loginout(){
        obj("api/Api")->unsetSession("manage_system");
        obj("api/Api")->unsetSession("manage_uid");
        obj("api/Api")->unsetSession("manage_pic");
        $url = 'index.php?r=manage';
        $this->redirect($url, $code = 302);
    }


}