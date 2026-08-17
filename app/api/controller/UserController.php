<?php
namespace app\api\controller;

/**
 * 用户接口（与 ZhiCms 主程序共用 yun_user 用户体系）
 *
 * 小程序/App 无法共享 Web 端 Cookie（ZhiCmsUser），故采用无状态 Token 鉴权：
 *   - 登录/注册成功后下发 Token（uid|mobile|exp 的 base64 + 站点密钥签名）
 *   - 后续请求在 Header 携带 Authorization: Bearer <token>，info 接口据此解析出同一套用户
 * 身份与密码均与主程序一致：密码 = md5(明文 . 'zhicms')，存于 yun_user 表。
 * 后续对接“我的收藏 / 我的爆料 / 评论”等数据时，只需用 Token 解析出的 uid 关联 yun_* 表即可。
 */
class UserController extends ApiBaseController {

    /**
     * 站点密钥（用于 Token 签名，复用 apiset.secretkey）
     */
    private function secret() {
        static $s = null;
        if ($s === null) {
            $cfg = \app\common\ConfigStore::load('api');
            $s = isset($cfg['secretkey']) && $cfg['secretkey'] !== '' ? $cfg['secretkey'] : 'zhangyuan';
        }
        return $s;
    }

    /**
     * 生成 Token
     */
    private function makeToken($uid, $mobile) {
        $payload = json_encode(array(
            'uid'   => $uid,
            'mobile'=> $mobile,
            'exp'   => time() + 86400 * 30,
        ));
        $b    = base64_encode($payload);
        $sign = md5($b . $this->secret());
        return $b . '.' . $sign;
    }

    /**
     * 解析 Token，失败返回 null
     */
    private function parseToken($token) {
        if (!$token || strpos($token, '.') === false) {
            return null;
        }
        list($b, $sign) = explode('.', $token, 2);
        if (md5($b . $this->secret()) !== $sign) {
            return null;
        }
        $payload = json_decode(base64_decode($b), true);
        if (!$payload || empty($payload['uid'])) {
            return null;
        }
        if (!empty($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        return $payload;
    }

    /**
     * 脱敏返回用户信息（绝不带 password）
     */
    private function maskUser($u) {
        return array(
            'id'       => $u['id'],
            'username' => $u['username'] ?? '',
            'mobile'   => $u['mobile'] ?? '',
            'date'     => $u['date'] ?? '',
        );
    }

    /**
     * 登录
     * POST index.php?r=api/user/login  mobile=手机号&password=密码
     */
    public function login() {
        $this->options();

        $mobile   = trim($this->raw('mobile', ''));
        $password = trim($this->raw('password', ''));

        if ($mobile === '' || $password === '') {
            $this->json(array('code' => 0, 'message' => '请输入手机号和密码'), 400);
        }
        // 防 SQL 注入：使用参数化查询（? 占位符）
        $u = obj('api/ApiData')->dataSelect('yun_user', array("`mobile` = ?", $mobile));
        if (empty($u)) {
            $this->json(array('code' => 0, 'message' => '用户不存在，请先注册'), 400);
        }
        if (md5($password . 'zhicms') !== $u['password']) {
            $this->json(array('code' => 0, 'message' => '密码错误'), 400);
        }
        if (!empty($u['lock'])) {
            $this->json(array('code' => 0, 'message' => '账号已被冻结'), 403);
        }

        $token = $this->makeToken($u['id'], $u['mobile']);
        $this->json(array(
            'code'    => 1,
            'message' => '登录成功',
            'token'   => $token,
            'user'    => $this->maskUser($u),
        ));
    }

    /**
     * 注册
     * POST index.php?r=api/user/register  mobile=手机号&username=昵称&password=密码
     */
    public function register() {
        $this->options();

        $mobile   = trim($this->raw('mobile', ''));
        $username = trim($this->raw('username', ''));
        $password = trim($this->raw('password', ''));

        if (!preg_match('/^1\d{10}$/', $mobile)) {
            $this->json(array('code' => 0, 'message' => '手机号格式不正确'), 400);
        }
        if ($username === '') {
            $this->json(array('code' => 0, 'message' => '请输入昵称'), 400);
        }
        if (strlen($password) < 6) {
            $this->json(array('code' => 0, 'message' => '密码至少 6 位'), 400);
        }

        $wMobile[] = array("`mobile` = ?", $mobile);
        if (obj('api/ApiData')->dataSelect('yun_user', $wMobile)) {
            $this->json(array('code' => 0, 'message' => '该手机号已注册'), 400);
        }
        $wName[] = array("`username` = ?", $username);
        if (obj('api/ApiData')->dataSelect('yun_user', $wName)) {
            $this->json(array('code' => 0, 'message' => '昵称已存在'), 400);
        }

        $data = array(
            'username' => $username,
            'password' => md5($password . 'zhicms'),
            'mobile'   => $mobile,
            'vest'     => 1,
            'lock'     => 0,
            'date'     => date('Y-m-d H:i:s'),
        );
        $uid   = obj('api/ApiData')->insertData('yun_user', $data);
        $token = $this->makeToken($uid, $mobile);

        $this->json(array(
            'code'    => 1,
            'message' => '注册成功',
            'token'   => $token,
            'user'    => $this->maskUser(array_merge(array('id' => $uid), $data)),
        ));
    }

    /**
     * 获取当前用户信息（Bearer Token 鉴权）
     * GET index.php?r=api/user/info   Header: Authorization: Bearer <token>
     */
    public function info() {
        $this->options();

        $token   = $this->requestToken();
        $payload = $this->parseToken($token);
        if (!$payload) {
            $this->json(array('code' => 401, 'message' => '未登录或登录已过期'), 401);
        }

        $where[] = "`id` = {$payload['uid']}";
        $u = obj('api/ApiData')->dataSelect('yun_user', $where);
        if (empty($u)) {
            $this->json(array('code' => 401, 'message' => '用户不存在'), 401);
        }

        $this->json(array(
            'code'    => 1,
            'message' => 'success',
            'user'    => $this->maskUser($u),
        ));
    }
}
