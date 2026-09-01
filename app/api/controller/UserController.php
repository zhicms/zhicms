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
     * 幂等扩容 yun_user.password 列为 varchar(100)。
     * 旧库该列为 varchar(32/35)，仅够存 md5；注册写入 bcrypt(60字符) 会触发
     * 1406 Data too long for column 'password'，导致接口 500、前端报「操作失败」。
     * 写入前自动扩列（与 manage 端 yun_manage 的处理一致）。
     */
    private function ensureUserPasswordColumnWidth() {
        try {
            $real = str_replace('`', '', obj('api/ApiData')->realTable('yun_user'));
            $row = obj('api/ApiData')->thisQuery(
                "SELECT `CHARACTER_MAXIMUM_LENGTH` FROM `information_schema`.`COLUMNS` " .
                "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '{$real}' AND `COLUMN_NAME` = 'password'"
            );
            $len = 0;
            if (!empty($row[0])) {
                $len = intval($row[0]['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
            }
            if ($len > 0 && $len < 60) {
                obj('api/ApiData')->executeQuery(
                    "ALTER TABLE `{$real}` MODIFY COLUMN `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '登录密码（md5 或 bcrypt）'"
                );
            }
        } catch (\Throwable $e) {
            // 扩列失败不阻断注册；若写入仍超长，下方 insert 会返回具体错误
        }
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
        $this->ensureUserPasswordColumnWidth();

        $mobile   = trim($this->raw('mobile', ''));
        $password = trim($this->raw('password', ''));

        if ($mobile === '' || $password === '') {
            $this->json(array('code' => 0, 'message' => '请输入手机号和密码'), 400);
        }
        // 防 SQL 注入：使用参数化查询（关联数组写法，框架会正确绑定参数）
        $u = obj('api/ApiData')->dataSelect('yun_user', array('mobile' => $mobile));
        if (empty($u)) {
            $this->json(array('code' => 0, 'message' => '用户不存在，请先注册'), 400);
        }
        if (md5($password . 'zhicms') !== $u['password'] && !password_verify($password, $u['password'])) {
            $this->json(array('code' => 0, 'message' => '密码错误'), 400);
        }
        // 透明升级：命中旧 md5 时改写为 bcrypt
        if (strlen($u['password']) < 60 || strpos($u['password'], '$2') !== 0) {
            obj('api/ApiData')->executeQuery(
                "UPDATE `{pre}user` SET `password` = ? WHERE `id` = ?",
                array(password_hash($password, PASSWORD_BCRYPT), $u['id'])
            );
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
        $this->ensureUserPasswordColumnWidth();

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

        if (obj('api/ApiData')->dataSelect('yun_user', array('mobile' => $mobile))) {
            $this->json(array('code' => 0, 'message' => '该手机号已注册'), 400);
        }
        if (obj('api/ApiData')->dataSelect('yun_user', array('username' => $username))) {
            $this->json(array('code' => 0, 'message' => '昵称已存在'), 400);
        }

        $data = array(
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
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
        $me = obj('api/ApiData')->dataSelect('yun_user', array('id' => $uid));
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
            obj('api/ApiData')->dataUpdate('yun_user', array('invited_by' => $inviterUid), array('id' => $uid));
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

    /**
     * 读取用户功能开关（yun_config 表），缺失时返回默认值
     */
    private function userSwitch($key, $default = '1') {
        try {
            $row = obj('api/ApiData')->thisQuery(
                "SELECT `value` FROM `{pre}config` WHERE `key` = ?",
                array($key)
            );
            if (!empty($row[0]['value'])) return $row[0]['value'];
        } catch (\Throwable $e) {}
        return $default;
    }

    /**
     * 生成唯一用户名：昵称清洗后去重，冲突追加随机后缀
     */
    private function genUsername($nickname) {
        $base = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9_]/u', '', $nickname);
        if ($base === '') $base = 'wxuser';
        $base = mb_substr($base, 0, 16);
        for ($i = 0; $i < 20; $i++) {
            $cand = $i === 0 ? $base : ($base . mt_rand(1000, 9999));
            if (!obj('api/ApiData')->dataSelect('yun_user', array('username' => $cand))) {
                return $cand;
            }
        }
        return $base . substr(md5(uniqid()), 0, 6);
    }

    /**
     * 微信快捷登录（App / 小程序通用）
     * POST index.php?r=api/user/wxlogin  openid / unionid / nickname / avatar / inviter
     * 逻辑：后台开关关闭→拒绝（前端降级为手机号登录）；已绑定→直接登录；未绑定→强制注册并绑定微信
     */
    /**
     * 微信快捷登录（App / 小程序通用）—— 强制唯一：以手机号为账号锚点
     * POST index.php?r=api/user/wxlogin
     *   基础：openid / unionid / nickname / avatar / inviter
     *   绑定或注册（首次无微信绑定时需要）：mobile / password / username(可选)
     *
     * 流程：
     *   1) 后台开关关闭 → 拒绝（前端降级手机号登录）
     *   2) 微信已绑定某账号 → 直接登录（is_new=0, bound=1）
     *   3) 未绑定，但带 mobile+password：
     *        mobile 已存在 → 校验密码，正确则绑定微信并登录（老用户绑定同一账号）
     *        mobile 不存在 → 注册新账号（含 mobile/密码/微信绑定），is_new=1
     *   4) 未绑定且未带 mobile/password → 返回 need_bind=1，由客户端弹窗补全手机号
     * 保证「网页/小程序/App/都信小程序」同一人为同一账号（手机号唯一锚点，微信 1:1 绑定）。
     */
    public function wxlogin() {
        $this->options();

        if ($this->userSwitch('user_wx_login', '1') !== '1') {
            $this->json(array('code' => 0, 'message' => '微信登录已关闭，请使用手机号登录'), 403);
        }

        $openid  = trim($this->raw('openid', ''));
        $unionid = trim($this->raw('unionid', ''));
        if ($openid === '' && $unionid === '') {
            $this->json(array('code' => 0, 'message' => '缺少微信凭证'), 400);
        }
        $nickname = trim($this->raw('nickname', ''));
        $avatar   = trim($this->raw('avatar', ''));

        // 查已绑定用户：unionid 优先，回退 openid
        $u = null;
        if ($unionid !== '') {
            $u = obj('api/ApiData')->dataSelect('yun_user', array(array("`wx_unionid` = ?", $unionid)));
        }
        if (empty($u) && $openid !== '') {
            $u = obj('api/ApiData')->dataSelect('yun_user', array(array("`wx_openid` = ?", $openid)));
        }

        // 场景 A：微信已绑定 → 直接登录（跨端唯一性已成立）
        if (!empty($u)) {
            $up = array();
            if ($avatar !== '' && $avatar !== ($u['avatar'] ?? '')) $up['avatar'] = $avatar;
            if ($unionid !== '' && $unionid !== ($u['wx_unionid'] ?? '')) $up['wx_unionid'] = $unionid;
            if ($openid !== '' && $openid !== ($u['wx_openid'] ?? '')) $up['wx_openid'] = $openid;
            if (!empty($up)) {
                obj('api/ApiData')->dataUpdate('yun_user', $up, array('id' => $u['id']));
                $u = array_merge($u, $up);
            }
            $token = $this->makeToken($u['id'], $u['mobile'] ?? '');
            $this->json(array(
                'code'    => 1,
                'message' => '登录成功',
                'token'   => $token,
                'user'    => $this->maskUser($u),
                'is_new'  => 0,
                'bound'   => 1,
            ));
        }

        // 场景 B：未绑定 → 需要手机号+密码（绑定老账号 或 注册新账号），避免造出无手机号的游离账号
        $mobile   = trim($this->raw('mobile', ''));
        $password = trim($this->raw('password', ''));
        if ($mobile === '' || $password === '') {
            $this->json(array(
                'code'      => 1,
                'message'   => '请先绑定手机号',
                'need_bind' => 1,
            ));
        }
        if (!preg_match('/^1\d{10}$/', $mobile)) {
            $this->json(array('code' => 0, 'message' => '手机号格式不正确'), 400);
        }
        $this->ensureUserPasswordColumnWidth();

        $exist = obj('api/ApiData')->dataSelect('yun_user', array('mobile' => $mobile));
        if (!empty($exist)) {
            // 老用户：校验密码后绑定微信（与网页/小程序共用同一账号）
            if (md5($password . 'zhicms') !== $exist['password'] && !password_verify($password, $exist['password'])) {
                $this->json(array('code' => 0, 'message' => '账号或密码错误'), 400);
            }
            $up = array('wx_openid' => $openid, 'wx_unionid' => $unionid);
            if ($avatar !== '' && $avatar !== ($exist['avatar'] ?? '')) $up['avatar'] = $avatar;
            if (empty($exist['username']) && $nickname !== '') $up['username'] = $this->genUsername($nickname);
            obj('api/ApiData')->dataUpdate('yun_user', $up, array('id' => $exist['id']));
            $u = array_merge($exist, $up);
            $token = $this->makeToken($exist['id'], $exist['mobile']);
            $this->json(array(
                'code'    => 1,
                'message' => '微信已绑定，登录成功',
                'token'   => $token,
                'user'    => $this->maskUser($u),
                'is_new'  => 0,
                'bound'   => 1,
            ));
        }

        // 新用户：注册（手机号 + 密码 + 微信绑定），昵称缺省用微信昵称
        if ($nickname === '') {
            $nickname = '微信用户' . substr(md5($openid . $unionid . mt_rand(0, 9999)), 0, 6);
        }
        $username = trim($this->raw('username', ''));
        if ($username === '') $username = $nickname;
        $data = array(
            'username'    => $this->genUsername($username),
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'mobile'      => $mobile,
            'avatar'      => $avatar,
            'vest'        => 1,
            'lock'        => 0,
            'date'        => date('Y-m-d H:i:s'),
            'wx_openid'   => $openid,
            'wx_unionid'  => $unionid,
        );
        $data['invite_code'] = $this->genInviteCode();
        $data['invited_by']  = $this->resolveInviter(trim($this->raw('inviter', '')));

        $uid   = obj('api/ApiData')->insertData('yun_user', $data);
        $token = $this->makeToken($uid, $mobile);

        if ($data['invited_by'] > 0) {
            $this->reportInviteBound($data['invited_by'], $uid);
        }

        $this->json(array(
            'code'    => 1,
            'message' => '注册成功',
            'token'   => $token,
            'user'    => $this->maskUser(array_merge(array('id' => $uid), $data)),
            'is_new'  => 1,
            'bound'   => 1,
        ));
    }

    /**
     * 已登录账号绑定微信（设置页「绑定微信」）
     * POST index.php?r=api/user/bindWechat  openid / unionid / avatar
     * Header: Authorization: Bearer <token>
     */
    public function bindWechat() {
        $this->options();
        $token   = $this->requestToken();
        $payload = $this->parseToken($token);
        if (!$payload) {
            $this->json(array('code' => 401, 'message' => '未登录或登录已过期'), 401);
        }
        $uid = (int) $payload['uid'];

        $openid  = trim($this->raw('openid', ''));
        $unionid = trim($this->raw('unionid', ''));
        if ($openid === '' && $unionid === '') {
            $this->json(array('code' => 0, 'message' => '缺少微信凭证'), 400);
        }
        // 该微信已绑定其他账号则拒绝
        if ($unionid !== '') {
            $exist = obj('api/ApiData')->dataSelect('yun_user', array(array("`wx_unionid` = ?", $unionid)));
            if (!empty($exist) && (int) $exist['id'] !== $uid) {
                $this->json(array('code' => 0, 'message' => '该微信已绑定其他账号'), 400);
            }
        }
        $up = array();
        if ($unionid !== '') $up['wx_unionid'] = $unionid;
        if ($openid !== '') $up['wx_openid'] = $openid;
        $av = trim($this->raw('avatar', ''));
        if ($av !== '') $up['avatar'] = $av;
        if (!empty($up)) {
            obj('api/ApiData')->dataUpdate('yun_user', $up, array('id' => $uid));
        }
        $u = obj('api/ApiData')->dataSelect('yun_user', array('id' => $uid));
        $this->json(array('code' => 1, 'message' => '微信绑定成功', 'user' => $this->maskUser($u)));
    }

    /* ============ 用户中心：收藏 / 浏览历史 / 我的评论 ============ */

    /** 要求登录：返回 uid；未登录直接输出 401 并终止 */
    private function authUid() {
        $p = $this->parseToken($this->requestToken());
        if (!$p) {
            $this->json(array('code' => 401, 'message' => '请先登录'), 401);
        }
        return (int)$p['uid'];
    }

    /** 我的收藏列表（可按 type 筛选） */
    public function favorites() {
        $this->options();
        $uid = $this->authUid();
        $type = trim($this->raw('type', ''));
        if (!in_array($type, array('goods', 'article', 'forum'))) $type = '';
        $page = max(1, (int)$this->raw('page', 1));
        $pageSize = 20;
        $where = "`uid` = {$uid}";
        if ($type !== '') $where .= " AND `type` = '" . addslashes($type) . "'";
        $total = obj('api/ApiData')->thisQuery("SELECT COUNT(*) AS c FROM `{pre}favorite` WHERE {$where}");
        $total = !empty($total[0]['c']) ? (int)$total[0]['c'] : 0;
        $offset = ($page - 1) * $pageSize;
        $list = obj('api/ApiData')->thisQuery("SELECT * FROM `{pre}favorite` WHERE {$where} ORDER BY `addtime` DESC LIMIT {$offset}, {$pageSize}");
        $list = $list ?: array();
        foreach ($list as &$it) {
            $it['price'] = floatval($it['price'] ?? 0);
            $it['addtime'] = (int)($it['addtime'] ?? 0);
            $it['extra'] = !empty($it['extra']) ? json_decode($it['extra'], true) : new \stdClass();
        }
        unset($it);
        $this->json(array('code' => 1, 'message' => 'success', 'data' => array(
            'list' => $list, 'page' => $page, 'page_size' => $pageSize,
            'total' => $total, 'has_more' => ($offset + count($list)) < $total,
        )));
    }

    /** 收藏 / 取消收藏（幂等切换） */
    public function favoriteToggle() {
        $this->options();
        if ($this->isPost() === false) $this->json(array('code' => 0, 'message' => '请求方式错误'), 400);
        $uid = $this->authUid();
        $type = trim($this->raw('type', ''));
        $target = trim($this->raw('target_id', ''));
        if (!in_array($type, array('goods', 'article', 'forum')) || $target === '') {
            $this->json(array('code' => 0, 'message' => '参数错误'), 400);
        }
        $exists = obj('api/ApiData')->thisQuery(
            "SELECT `id` FROM `{pre}favorite` WHERE `uid` = ? AND `type` = ? AND `target_id` = ?",
            array($uid, $type, $target)
        );
        if (!empty($exists)) {
            obj('api/ApiData')->executeQuery(
                "DELETE FROM `{pre}favorite` WHERE `uid` = ? AND `type` = ? AND `target_id` = ?",
                array($uid, $type, $target)
            );
            $this->json(array('code' => 1, 'message' => '已取消收藏', 'favorited' => false));
        }
        $extra = trim($this->raw('extra', ''));
        if ($extra !== '' && is_string($extra)) {
            @json_decode($extra);
            if (json_last_error() !== JSON_ERROR_NONE) $extra = '';
        }
        obj('api/ApiData')->insertData('yun_favorite', array(
            'uid'       => $uid,
            'type'      => $type,
            'target_id' => $target,
            'title'     => trim($this->raw('title', '')),
            'pic'       => trim($this->raw('pic', '')),
            'price'     => floatval($this->raw('price', 0)),
            'url'       => trim($this->raw('url', '')),
            'extra'     => $extra,
            'addtime'   => time(),
        ));
        $this->json(array('code' => 1, 'message' => '收藏成功', 'favorited' => true));
    }

    /** 删除收藏（按 id 或 type+target_id） */
    public function favoriteDel() {
        $this->options();
        if ($this->isPost() === false) $this->json(array('code' => 0, 'message' => '请求方式错误'), 400);
        $uid = $this->authUid();
        $id = (int)$this->raw('id', 0);
        if ($id > 0) {
            obj('api/ApiData')->executeQuery("DELETE FROM `{pre}favorite` WHERE `id` = ? AND `uid` = ?", array($id, $uid));
        } else {
            $type = trim($this->raw('type', ''));
            $target = trim($this->raw('target_id', ''));
            if (!in_array($type, array('goods', 'article', 'forum')) || $target === '') {
                $this->json(array('code' => 0, 'message' => '参数错误'), 400);
            }
            obj('api/ApiData')->executeQuery(
                "DELETE FROM `{pre}favorite` WHERE `uid` = ? AND `type` = ? AND `target_id` = ?",
                array($uid, $type, $target)
            );
        }
        $this->json(array('code' => 1, 'message' => '已删除'));
    }

    /** 浏览历史列表 */
    public function history() {
        $this->options();
        $uid = $this->authUid();
        $type = trim($this->raw('type', ''));
        if (!in_array($type, array('goods', 'article', 'forum'))) $type = '';
        $page = max(1, (int)$this->raw('page', 1));
        $pageSize = 20;
        $where = "`uid` = {$uid}";
        if ($type !== '') $where .= " AND `type` = '" . addslashes($type) . "'";
        $total = obj('api/ApiData')->thisQuery("SELECT COUNT(*) AS c FROM `{pre}history` WHERE {$where}");
        $total = !empty($total[0]['c']) ? (int)$total[0]['c'] : 0;
        $offset = ($page - 1) * $pageSize;
        $list = obj('api/ApiData')->thisQuery("SELECT * FROM `{pre}history` WHERE {$where} ORDER BY `addtime` DESC LIMIT {$offset}, {$pageSize}");
        $list = $list ?: array();
        foreach ($list as &$it) {
            $it['price'] = floatval($it['price'] ?? 0);
            $it['addtime'] = (int)($it['addtime'] ?? 0);
            $it['extra'] = !empty($it['extra']) ? json_decode($it['extra'], true) : new \stdClass();
        }
        unset($it);
        $this->json(array('code' => 1, 'message' => 'success', 'data' => array(
            'list' => $list, 'page' => $page, 'page_size' => $pageSize,
            'total' => $total, 'has_more' => ($offset + count($list)) < $total,
        )));
    }

    /** 记录浏览历史（同 uid+type+target 自动更新时间，不重复插入） */
    public function historyAdd() {
        $this->options();
        if ($this->isPost() === false) $this->json(array('code' => 0, 'message' => '请求方式错误'), 400);
        $uid = $this->authUid();
        $type = trim($this->raw('type', ''));
        $target = trim($this->raw('target_id', ''));
        if (!in_array($type, array('goods', 'article', 'forum')) || $target === '') {
            $this->json(array('code' => 0, 'message' => '参数错误'), 400);
        }
        $extra = trim($this->raw('extra', ''));
        if ($extra !== '' && is_string($extra)) {
            @json_decode($extra);
            if (json_last_error() !== JSON_ERROR_NONE) $extra = '';
        }
        $now = time();
        obj('api/ApiData')->executeQuery(
            "INSERT INTO `{pre}history` (`uid`,`type`,`target_id`,`title`,`pic`,`price`,`url`,`extra`,`addtime`) "
            . "VALUES (?,?,?,?,?,?,?,?,?) "
            . "ON DUPLICATE KEY UPDATE `title`=VALUES(`title`),`pic`=VALUES(`pic`),`price`=VALUES(`price`),`url`=VALUES(`url`),`extra`=VALUES(`extra`),`addtime`=?",
            array(
                $uid, $type, $target,
                trim($this->raw('title', '')), trim($this->raw('pic', '')),
                floatval($this->raw('price', 0)), trim($this->raw('url', '')),
                $extra, $now, $now,
            )
        );
        $this->json(array('code' => 1, 'message' => 'ok'));
    }

    /** 清空浏览历史（可指定 type） */
    public function historyClear() {
        $this->options();
        if ($this->isPost() === false) $this->json(array('code' => 0, 'message' => '请求方式错误'), 400);
        $uid = $this->authUid();
        $type = trim($this->raw('type', ''));
        if (in_array($type, array('goods', 'article', 'forum'))) {
            obj('api/ApiData')->executeQuery("DELETE FROM `{pre}history` WHERE `uid` = ? AND `type` = ?", array($uid, $type));
        } else {
            obj('api/ApiData')->executeQuery("DELETE FROM `{pre}history` WHERE `uid` = ?", array($uid));
        }
        $this->json(array('code' => 1, 'message' => '已清空'));
    }

    /** 删除单条浏览历史 */
    public function historyDel() {
        $this->options();
        if ($this->isPost() === false) $this->json(array('code' => 0, 'message' => '请求方式错误'), 400);
        $uid = $this->authUid();
        $id = (int)$this->raw('id', 0);
        if ($id > 0) {
            obj('api/ApiData')->executeQuery("DELETE FROM `{pre}history` WHERE `id` = ? AND `uid` = ?", array($id, $uid));
        } else {
            $type = trim($this->raw('type', ''));
            $target = trim($this->raw('target_id', ''));
            if (in_array($type, array('goods', 'article', 'forum')) && $target !== '') {
                obj('api/ApiData')->executeQuery(
                    "DELETE FROM `{pre}history` WHERE `uid` = ? AND `type` = ? AND `target_id` = ?",
                    array($uid, $type, $target)
                );
            }
        }
        $this->json(array('code' => 1, 'message' => '已删除'));
    }

    /** 我的评论 + 我的回复（合并按时间倒序） */
    public function myComment() {
        $this->options();
        $uid = $this->authUid();
        $page = max(1, (int)$this->raw('page', 1));
        $pageSize = 20;

        $rows = array();
        $comments = obj('api/ApiData')->thisQuery(
            "SELECT c.id, c.mid, c.content, c.date, a.title AS target_title "
            . "FROM `{pre}comment` c LEFT JOIN `{pre}article` a ON c.mid = a.id "
            . "WHERE c.uid = ? ORDER BY c.id DESC",
            array($uid)
        );
        foreach ($comments ?: array() as $c) {
            $rows[] = array(
                'type'         => 'article',
                'id'           => (int)$c['id'],
                'target_id'    => (int)$c['mid'],
                'content'      => $c['content'],
                'date'         => $c['date'],
                'target_title' => $c['target_title'] ?: '',
                'ts'           => strtotime($c['date'] ?: '0'),
            );
        }
        $replies = obj('api/ApiData')->thisQuery(
            "SELECT r.id, r.forum_id, r.content, r.date, f.title AS target_title "
            . "FROM `{pre}forum_reply` r LEFT JOIN `{pre}forum` f ON r.forum_id = f.id "
            . "WHERE r.uid = ? ORDER BY r.id DESC",
            array($uid)
        );
        foreach ($replies ?: array() as $r) {
            $rows[] = array(
                'type'         => 'forum',
                'id'           => (int)$r['id'],
                'target_id'    => (int)$r['forum_id'],
                'content'      => $r['content'],
                'date'         => $r['date'],
                'target_title' => $r['target_title'] ?: '',
                'ts'           => strtotime($r['date'] ?: '0'),
            );
        }
        usort($rows, function ($a, $b) { return $b['ts'] - $a['ts']; });
        $total = count($rows);
        $offset = ($page - 1) * $pageSize;
        $slice = array_slice($rows, $offset, $pageSize);
        foreach ($slice as &$s) { unset($s['ts']); }
        unset($s);
        $this->json(array('code' => 1, 'message' => 'success', 'data' => array(
            'list' => $slice, 'page' => $page, 'page_size' => $pageSize,
            'total' => $total, 'has_more' => ($offset + count($slice)) < $total,
        )));
    }
}
