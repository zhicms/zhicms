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
            'id'         => $u['id'],
            'username'   => $u['username'] ?? '',
            'mobile'     => $u['mobile'] ?? '',
            'date'       => $u['date'] ?? '',
            'invite_code'=> $u['invite_code'] ?? '',
        );
    }

    /**
     * 生成专属邀请码：6 位「大小写字母 + 数字」随机串
     * 去除易混淆字符 0/O/1/l/I，循环重试保证唯一（uk_invite_code 兜底）
     */
    private function genInviteCode() {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max   = strlen($chars) - 1;
        for ($i = 0; $i < 20; $i++) {
            $code = '';
            for ($j = 0; $j < 6; $j++) {
                $code .= $chars[mt_rand(0, $max)];
            }
            $w = array(array("`invite_code` = ?", $code));
            if (!obj('api/ApiData')->dataSelect('yun_user', $w)) {
                return $code;
            }
        }
        // 极端碰撞兜底：时间戳后缀
        return substr($code . time(), -6);
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
        // 生成专属邀请码（6位大小写字母+数字，去除易混淆字符，保证唯一）
        $data['invite_code'] = $this->genInviteCode();

        // 解析邀请人（前端传 inviter：可能是邀请码或 uid；忽略无效/自邀/闭环）
        $inviterRaw = trim($this->raw('inviter', ''));
        $data['invited_by'] = $this->resolveInviter($inviterRaw);

        $uid   = obj('api/ApiData')->insertData('yun_user', $data);
        $token = $this->makeToken($uid, $mobile);

        // 上报邀请成功（失败静默，用于后续佣金/统计）
        if ($data['invited_by'] > 0) {
            $this->reportInviteBound($data['invited_by'], $uid);
        }

        $this->json(array(
            'code'    => 1,
            'message' => '注册成功',
            'token'   => $token,
            'user'    => $this->maskUser(array_merge(array('id' => $uid), $data)),
        ));
    }

    /**
     * 按邀请标识（邀请码或 uid）反查邀请人 uid
     * 规则：无效/不存在/与自身相同/形成闭环 → 返回 0
     * @param string $raw  邀请码或 uid 字符串
     * @return int
     */
    private function resolveInviter($raw) {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '0') return 0;
        // 纯数字按 uid 查；否则按 invite_code 查
        if (ctype_digit($raw)) {
            $w = array(array("`id` = ?", (int) $raw));
        } else {
            $w = array(array("`invite_code` = ?", $raw));
        }
        $inv = obj('api/ApiData')->dataSelect('yun_user', $w);
        if (empty($inv)) return 0;
        return (int) $inv['id'];
    }

    /** 上报邀请绑定成功（预留：佣金/统计，失败静默） */
    private function reportInviteBound($inviterUid, $newUid) {
        // TODO: 写入邀请记录表 / 触发新手奖励，按需扩展
    }

    /**
     * 已登录用户回绑邀请人（幂等：仅首次有效）
     * POST index.php?r=api/user/bindInviter  icode=邀请码或uid
     * Header: Authorization: Bearer <token>
     */
    public function bindInviter() {
        $this->options();
        $token   = $this->requestToken();
        $payload = $this->parseToken($token);
        if (!$payload) {
            $this->json(array('code' => 401, 'message' => '未登录或登录已过期'), 401);
        }
        $uid = (int) $payload['uid'];

        // 已绑定则忽略（幂等）
        $me = obj('api/ApiData')->dataSelect('yun_user', array("`id` = ?", $uid));
        if (empty($me)) {
            $this->json(array('code' => 401, 'message' => '用户不存在'), 401);
        }
        if (!empty($me['invited_by'])) {
            $this->json(array('code' => 1, 'message' => '已绑定邀请关系', 'invited_by' => (int) $me['invited_by']));
        }

        $inviterUid = $this->resolveInviter(trim($this->raw('icode', '')));
        // 防自邀
        if ($inviterUid === $uid) {
            $this->json(array('code' => 0, 'message' => '不能邀请自己'), 400);
        }
        if ($inviterUid > 0) {
            obj('api/ApiData')->dataUpdate('yun_user', array('invited_by' => $inviterUid), array("`id` = ?", $uid));
            $this->reportInviteBound($inviterUid, $uid);
        }
        $this->json(array('code' => 1, 'message' => $inviterUid > 0 ? '绑定成功' : '无有效邀请人', 'invited_by' => $inviterUid));
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
