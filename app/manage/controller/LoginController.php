<?php
namespace app\manage\controller;

class LoginController extends \app\base\controller\BaseController {

	public function index(){
	   if(!\IS_POST){
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
            $where['username'] = $userName;
            $where['password'] = $passWord;
            $user = obj('api/ApiData')->dataSelect('yun_manage', $where);
            if (!empty($user)) {
                $_SESSION['manage_system'] = $userName;
                $_SESSION['manage_uid'] = is_object($user) ? $user->id : $user['id'];
                $_SESSION['manage_pic'] = is_object($user) ? $user->pic : $user['pic'];
                \ZhiCms\ext\AdminLog::write('login', '管理员「' . $userName . '」登录后台');
                echo json_encode(array("info" => "登录成功", "status" => "y"));
                exit;
            } else {
                echo json_encode(array("info" => "账号或密码错误", "status" => "n"));
                exit;
            }
        }

	   }
	}


    public function logout(){
        $user = isset($_SESSION['manage_system']) ? $_SESSION['manage_system'] : '';
        \ZhiCms\ext\AdminLog::write('logout', '管理员「' . $user . '」退出登录');
        obj("api/Api")->unsetSession("manage_system");
        obj("api/Api")->unsetSession("manage_uid");
        obj("api/Api")->unsetSession("manage_pic");
        // 退出前一键清理全站缓存
        if (class_exists('\\app\\manage\\controller\\IndexController')) {
            \app\manage\controller\IndexController::clearAllCache();
        }
        $url = 'index.php?r=manage';
        $this->redirect($url, $code = 302);
    }


}