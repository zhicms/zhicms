<?php
namespace app\manage\controller;

class LoginController extends \app\base\controller\BaseController {

	public function index(){
	   if(!\IS_POST){
	   	$this->display();
	    exit;
	   }else{
	   	 // 整个登录流程用 try/catch 兜底：任何异常都返回 JSON，避免输出 HTML 导致前端「网络请求失败」
	   	 try {
	   	  $userName = $this->arg('username');
         $inputPwd = $this->arg('password');
        if (!$userName) {
            echo json_encode(array("info" => "请填写用户名", "status" => "n"));
            exit;
        } elseif (!$inputPwd) {
            echo json_encode(array("info" => "请填写密码", "status" => "n"));
            exit;
        } else {
            // 登录失败限流：同一 IP 5 分钟内最多失败 5 次
            $this->throttleLogin($userName);
            $where['username'] = $userName;
            $user = obj('api/ApiData')->dataSelect('yun_manage', $where);
            if (!empty($user)) {
                $stored = is_object($user) ? $user->password : $user['password'];
                if ($this->verifyPassword($inputPwd, $stored, 'yun_manage')) {
                    // 旧 md5 命中：透明升级为 bcrypt
                    if (strlen($stored) < 60 || !preg_match('/^\$2[aby]\$/', $stored)) {
                        $uid = is_object($user) ? $user->id : $user['id'];
                        // 旧库 password 列为 varchar(35)，bcrypt(60字符) 写入会触发 1406 超长。
                        // 写入前幂等扩容该列，避免升级抛异常导致登录接口输出 HTML、前端报「网络请求失败」。
                        $this->ensurePasswordColumnWidth();
                        obj('api/ApiData')->dataUpdate('yun_manage', array('password' => $this->hashPassword($inputPwd)), array("`id`={$uid}"));
                    }
                    $_SESSION['manage_system'] = $userName;
                    $_SESSION['manage_uid'] = is_object($user) ? $user->id : $user['id'];
                    $_SESSION['manage_pic'] = is_object($user) ? $user->pic : $user['pic'];
                    $_SESSION['manage_nickname'] = is_object($user) ? (isset($user->nickname) ? $user->nickname : '') : (isset($user['nickname']) ? $user['nickname'] : '');
                    $this->clearLoginFails($userName);
                    \ZhiCms\ext\AdminLog::write('login', '管理员「' . $userName . '」登录后台');
                    echo json_encode(array("info" => "登录成功", "status" => "y"));
                    exit;
                }
            }
            $this->recordLoginFail($userName);
            echo json_encode(array("info" => "账号或密码错误", "status" => "n"));
            exit;
        }
	   	 } catch (\Throwable $e) {
	   	 	header('Content-Type: application/json; charset=utf-8');
	   	 	echo json_encode(array("info" => "登录处理异常：" . $e->getMessage(), "status" => "n"), JSON_UNESCAPED_UNICODE);
	   	 	exit;
	   	 }

	   }
	}

	/**
	 * 登录失败限流（基于会话的 IP+用户名 计数，5 分钟内最多失败 5 次）
	 */
	private function throttleKey($user) {
		return 'login_fail_' . md5($user . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
	}
	private function throttleLogin($user) {
		$key = $this->throttleKey($user);
		$data = $_SESSION[$key] ?? array();
		if (!empty($data) && $data['time'] > time() - 300 && $data['count'] >= 5) {
			echo json_encode(array("info" => "尝试次数过多，请 5 分钟后再试", "status" => "n"));
			exit;
		}
	}
	private function recordLoginFail($user) {
		$key = $this->throttleKey($user);
		$data = $_SESSION[$key] ?? array();
		if (empty($data) || $data['time'] < time() - 300) {
			$data = array('time' => time(), 'count' => 0);
		}
		$data['count']++;
		$data['time'] = time();
		$_SESSION[$key] = $data;
	}
	private function clearLoginFails($user) {
	unset($_SESSION[$this->throttleKey($user)]);
	}

	/**
	* 幂等扩容 yun_manage.password 列为 varchar(100)。
	* 旧库该列为 varchar(35)，仅够存 md5（32字符）；登录成功后透明升级 bcrypt（60字符）
	* 会触发 1406 Data too long for column 'password'，导致接口抛异常、前端报「网络请求失败」。
	* 这里在升级前自动扩列，失败不影响登录主流程。
	*/
	private function ensurePasswordColumnWidth() {
		try {
			// 通过 realTable 动态解析真实表名，兼容自定义前缀（默认 yun_）
			$real = str_replace('`', '', obj('api/ApiData')->realTable('yun_manage'));
			$row = obj('api/ApiData')->thisQuery("SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '{$real}' AND `COLUMN_NAME` = 'password'");
			$len = 0;
			if (!empty($row[0])) {
				$len = intval($row[0]['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
			}
			if ($len > 0 && $len < 60) {
				obj('api/ApiData')->executeQuery("ALTER TABLE `{$real}` MODIFY COLUMN `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '登录密码（md5 或 bcrypt）'");
			}
		} catch (\Throwable $e) {
			// 扩列失败不阻断登录；若写入仍超长，主流程会走 catch 返回错误提示
		}
	}


	public function logout(){
        $user = isset($_SESSION['manage_system']) ? $_SESSION['manage_system'] : '';
        \ZhiCms\ext\AdminLog::write('logout', '管理员「' . $user . '」退出登录');
        obj("api/Api")->unsetSession("manage_system");
        obj("api/Api")->unsetSession("manage_uid");
        obj("api/Api")->unsetSession("manage_pic");
        obj("api/Api")->unsetSession("manage_nickname");
        // 退出前一键清理全站缓存
        if (class_exists('\\app\\manage\\controller\\CacheController')) {
            \app\manage\controller\CacheController::clearAllCache();
        }
        $url = 'index.php?r=manage';
        $this->redirect($url, $code = 302);
    }


}