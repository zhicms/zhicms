<?php
namespace app\index\controller;

/**
 * 前台用户登录 / 注册 / 登出 / 图形验证码
 * 对接 yun_user 表，登录态用 Cookie（ZhiCmsUser = 手机号）持久化。
 *
 * 路由：
 *   index.php?r=index/login/index   登录页
 *   index.php?r=index/login/register   注册页
 *   index.php?r=index/login/doLogin  登录提交（POST）
 *   index.php?r=index/login/doReg     注册提交（POST）
 *   index.php?r=index/login/logout    登出
 *   index.php?r=index/login/captcha   图形验证码（PNG）
 */
class LoginController extends \app\base\controller\BaseController
{
    /** Cookie 名称（与 InteractController / ForumController 识别登录用户一致） */
    const COOKIE_NAME = 'ZhiCmsUser';
    /** 密码加盐（与已有 findUser/password 体系一致）：md5(pwd . 'zhicms') */
    const SALT = 'zhicms';
    /** 图形验证码 session key */
    const CAPTCHA_KEY = 'zc_reg_captcha';

    /**
     * 读取用户开关（yun_config）
     */
    private function userSwitch($key, $default = '1') {
        $row = obj("api/ApiData")->thisQuery(
            "SELECT `value` FROM `{pre}config` WHERE `key` = ? LIMIT 1",
            array($key)
        );
        // 不能用 empty() 判断：empty('0') 为 true，会导致开关关闭(0)时错误回落默认值
        if (isset($row[0]['value']) && $row[0]['value'] !== '') {
            return $row[0]['value'];
        }
        return $default;
    }

    /**
     * 检测是否展示登录注册入口
     */
    private function showLoginEntry() {
        return $this->userSwitch('user_show_login', '1') === '1';
    }

    /**
     * 登录页
     */
    public function index(){
        $this->showEntry = $this->showLoginEntry();
        if (isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] !== '') {
            $this->user = obj("index/global", "controller")->findUser("y", $_COOKIE[self::COOKIE_NAME], "cookie");
        } else {
            $this->user = null;
        }
        $this->regCaptcha = $this->userSwitch('user_reg_captcha', '1') === '1';
        $this->display();
    }

    /**
     * 注册页
     */
    public function register(){
        $this->showEntry = $this->showLoginEntry();
        $this->regCaptcha = $this->userSwitch('user_reg_captcha', '1') === '1';
        $this->emailVerify = $this->userSwitch('user_email_verify', '0') === '1';
        $this->display();
    }

    /**
     * 图形验证码输出（GD 库）
     */
    public function captcha(){
        if (!function_exists('gd_info')) {
            header('Content-Type: text/plain; charset=utf-8');
            echo '服务器未启用 GD 库，无法生成图形验证码';
            exit;
        }
        $w = 110; $h = 40;
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 245, 246, 250);
        imagefill($img, 0, 0, $bg);
        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            $c = imagecolorallocate($img, mt_rand(180, 220), mt_rand(180, 220), mt_rand(180, 220));
            imageline($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $c);
        }
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $len = 4;
        $code = '';
        $fg = imagecolorallocate($img, 54, 110, 200);
        for ($i = 0; $i < $len; $i++) {
            $ch = $chars[mt_rand(0, strlen($chars) - 1)];
            $code .= $ch;
            // 使用内置字体（无需 ttf 文件），角度 0，大小 5
            imagechar($img, 5, 12 + $i * 24, mt_rand(8, 16), $ch, $fg);
        }
        // 保存到 session（框架已 session_start）
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION[self::CAPTCHA_KEY] = strtolower($code);

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache');
        imagepng($img);
        imagedestroy($img);
        exit;
    }

    /**
     * 登录提交
     */
    public function doLogin(){
        if (empty($_POST)) exit(json_encode(array('status' => 'n', 'info' => '请使用 POST 提交')));
        $mobile = trim($this->arg('mobile', ''));
        $password = trim($this->arg('password', ''));
        if ($mobile === '' || $password === '') {
            exit(json_encode(array('status' => 'n', 'info' => '请输入手机号和密码')));
        }
        $row = obj("api/ApiData")->thisQuery(
            "SELECT * FROM `{pre}user` WHERE `mobile` = ? LIMIT 1",
            array($mobile)
        );
        if (empty($row[0])) {
            exit(json_encode(array('status' => 'n', 'info' => '该手机号未注册')));
        }
        $u = $row[0];
        if (isset($u['status']) && $u['status'] != 1) {
            exit(json_encode(array('status' => 'n', 'info' => '账号已被禁用')));
        }
        if ($u['password'] !== md5($password . self::SALT)) {
            exit(json_encode(array('status' => 'n', 'info' => '密码错误')));
        }
        // 登录成功：写入 Cookie（手机号作为标识，与 findUser cookie 模式一致）
        setcookie(self::COOKIE_NAME, $u['mobile'], time() + 86400 * 30, '/');
        // 同步 AI 机器人身份（未登录访客的 ai_uid 与登录用户绑定）
        $this->bindAiUid($u['id']);
        // 更新登录信息
        obj("api/ApiData")->executeQuery(
            "UPDATE `{pre}user` SET `login_ip` = ?, `date` = ? WHERE `id` = ?",
            array($this->clientIp(), date('Y-m-d H:i:s'), $u['id'])
        );
        exit(json_encode(array('status' => 'y', 'info' => '登录成功')));
    }

    /**
     * 注册提交
     */
    public function doReg(){
        if (empty($_POST)) exit(json_encode(array('status' => 'n', 'info' => '请使用 POST 提交')));
        $mobile = trim($this->arg('mobile', ''));
        $password = trim($this->arg('password', ''));
        $repassword = trim($this->arg('repassword', ''));
        $email = trim($this->arg('email', ''));
        $captcha = trim($this->arg('captcha', ''));

        if ($this->userSwitch('user_reg_captcha', '1') === '1') {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $real = isset($_SESSION[self::CAPTCHA_KEY]) ? $_SESSION[self::CAPTCHA_KEY] : '';
            if ($real === '' || strtolower($captcha) !== $real) {
                exit(json_encode(array('status' => 'n', 'info' => '图形验证码错误')));
            }
            unset($_SESSION[self::CAPTCHA_KEY]);
        }

        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            exit(json_encode(array('status' => 'n', 'info' => '手机号格式不正确')));
        }
        if (strlen($password) < 6) {
            exit(json_encode(array('status' => 'n', 'info' => '密码至少 6 位')));
        }
        if ($password !== $repassword) {
            exit(json_encode(array('status' => 'n', 'info' => '两次密码不一致')));
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            exit(json_encode(array('status' => 'n', 'info' => '邮箱格式不正确')));
        }

        $exist = obj("api/ApiData")->thisQuery(
            "SELECT `id` FROM `{pre}user` WHERE `mobile` = ? LIMIT 1",
            array($mobile)
        );
        if (!empty($exist[0])) {
            exit(json_encode(array('status' => 'n', 'info' => '该手机号已注册')));
        }
        if ($email !== '') {
            $eExist = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}user` WHERE `email` = ? LIMIT 1",
                array($email)
            );
            if (!empty($eExist[0])) {
                exit(json_encode(array('status' => 'n', 'info' => '该邮箱已被使用')));
            }
        }

        // 邀请归因：从落地页写入的 Cookie(inviter_code) 解析邀请人 uid
        $invitedBy = $this->resolveInvitedBy();

        $data = array(
            'username' => $mobile,
            'password' => md5($password . self::SALT),
            'mobile'   => $mobile,
            'email'    => $email,
            'vest'     => 1,
            'lock'     => 0,
            'status'   => 1,
            'reg_time' => date('Y-m-d H:i:s'),
            'reg_ip'   => $this->clientIp(),
            'login_ip' => $this->clientIp(),
            'date'     => date('Y-m-d H:i:s'),
        );
        if ($invitedBy > 0) {
            $data['invited_by'] = $invitedBy;
        }
        $newId = obj("api/ApiData")->insertData("yun_user", $data);
        if (!$newId) {
            exit(json_encode(array('status' => 'n', 'info' => '注册失败，请稍后重试')));
        }
        // 自动登录
        setcookie(self::COOKIE_NAME, $mobile, time() + 86400 * 30, '/');
        $this->bindAiUid($newId);
        exit(json_encode(array('status' => 'y', 'info' => '注册成功，已自动登录')));
    }

    /**
     * 邀请归因解析：从 Cookie(inviter_code) 取邀请人，返回其 uid 或 0
     *   纯数字 -> 按 uid 查；否则按 invite_code 查；
     *   查不到 / 是本人 -> 返回 0（不计归因）
     */
    private function resolveInvitedBy()
    {
        $raw = isset($_COOKIE['inviter_code']) ? trim($_COOKIE['inviter_code']) : '';
        if ($raw === '' || !preg_match('/^[A-Za-z0-9]+$/', $raw)) {
            return 0;
        }
        if (ctype_digit($raw)) {
            $inviterId = intval($raw);
        } else {
            $row = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}user` WHERE `invite_code` = ? LIMIT 1",
                array($raw)
            );
            $inviterId = isset($row[0]['id']) ? intval($row[0]['id']) : 0;
        }
        if ($inviterId <= 0) {
            return 0;
        }
        return $inviterId;
    }

    /**
     * 登出
     */
    public function logout(){
        setcookie(self::COOKIE_NAME, '', time() - 3600, '/');
        if (isset($_GET['ajax'])) {
            exit(json_encode(array('status' => 'y', 'info' => '已退出登录')));
        }
        $this->redirect('index.php?r=index/login/index');
    }

    /**
     * 将 AI 机器人身份（ai_uid cookie）与登录用户绑定：
     * 把当前访客的 ai_uid 历史文件重命名为 uid 维度的文件，
     * 使登录后 AI 对话历史延续到该用户。
     */
    private function bindAiUid($uid){
        if (empty($_COOKIE['ai_uid'])) return;
        $historyDir = \ROOT_PATH . 'data/ai_chat_history/';
        if (!is_dir($historyDir)) return;
        $visitorFile = $historyDir . md5($_COOKIE['ai_uid']) . '.json';
        if (!is_file($visitorFile)) return;
        $userFile = $historyDir . md5('u_' . $uid) . '.json';
        // 合并：用户已有历史则追加访客历史
        $visitorData = json_decode(file_get_contents($visitorFile), true);
        if (!is_array($visitorData)) $visitorData = array();
        if (is_file($userFile)) {
            $userData = json_decode(file_get_contents($userFile), true);
            if (!is_array($userData)) $userData = array();
        } else {
            $userData = array();
        }
        $merged = array_merge($userData, $visitorData);
        if (count($merged) > 20) $merged = array_slice($merged, -20);
        @file_put_contents($userFile, json_encode($merged, JSON_UNESCAPED_UNICODE));
        @unlink($visitorFile);
        // 把 ai_uid cookie 改写为用户维度标识
        setcookie('ai_uid', 'u_' . $uid, time() + 86400 * 365, '/', '', false, true);
    }

    /**
     * 客户端 IP
     */
    private function clientIp(){
        $ip = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return substr($ip, 0, 64);
    }
}
