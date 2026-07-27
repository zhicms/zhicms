<?php
namespace app\api\controller;

/**
 * 微社区 API（供微信小程序 / App 复用，与网站 forum 模块共用同一套数据表）
 *
 * 路由：index.php?r=api/forum/<action>
 * 数据格式：{ code:1, message:'', data:{} }（与小程序其它接口一致）
 *
 * 鉴权：发帖/回复/点赞支持 Bearer Token（同 UserController 解析），
 *       未登录时以昵称（poster/mail）或设备 visitor 标识（用于点赞去重）。
 *
 * 开关：forum_on（yun_config）为社区总开关，关闭时所有读写接口返回 code:0。
 */
class ForumController extends ApiBaseController {

    /* ============ 通用辅助 ============ */

    /** 读取站点配置开关，默认开启 */
    private function getSwitch($key, $default = '1') {
        $row = $this->q("SELECT `value` FROM `{pre}config` WHERE `key` = ?", array($key));
        return !empty($row[0]['value']) ? $row[0]['value'] : $default;
    }

    /** 站点根 URL（含结尾 /） */
    private function base() {
        return rtrim($this->siteUrl(), '/');
    }

    /** 把相对 /data/... 路径补全为绝对 URL */
    private function fixUrl($u) {
        if (empty($u)) return '';
        if (strpos($u, 'http') === 0) return $u;
        if ($u[0] === '/') return $this->base() . $u;
        return $u;
    }

    /** 简易查询 */
    private function q($sql, $params = array()) {
        return obj('api/ApiData')->thisQuery($sql, $params);
    }

    /** 解析 Bearer Token（与 UserController 同款） */
    private function parseToken($token) {
        if (!$token || strpos($token, '.') === false) return null;
        list($b, $sign) = explode('.', $token, 2);
        if (md5($b . $this->secret()) !== $sign) return null;
        $payload = json_decode(base64_decode($b), true);
        if (!$payload || empty($payload['uid'])) return null;
        if (!empty($payload['exp']) && $payload['exp'] < time()) return null;
        return $payload;
    }

    private function secret() {
        static $s = null;
        if ($s === null) {
            $cfg = array();
            if (file_exists(CONFIG_PATH . 'apiset.php')) {
                $cfg = include CONFIG_PATH . 'apiset.php';
            }
            $s = isset($cfg['secretkey']) && $cfg['secretkey'] !== '' ? $cfg['secretkey'] : 'zhangyuan';
        }
        return $s;
    }

    /** 当前登录用户（Token 解析），失败返回 null */
    private function loginUser() {
        $token = $this->requestToken();
        if (!$token) return null;
        $p = $this->parseToken($token);
        if (!$p) return null;
        $u = obj('api/ApiData')->dataSelect('yun_user', array("`id` = {$p['uid']}"));
        return $u ? $u : null;
    }

    /** 设备级访客标识（小程序无 Cookie，用 storage 中的 visitor） */
    private function visitorToken() {
        $v = $this->raw('visitor', '');
        if ($v === '') $v = $this->raw('device', '');
        return substr(preg_replace('/[^a-zA-Z0-9_]/', '', $v), 0, 64);
    }

    /** 获取客户端 IP */
    private function clientIp() {
        $ip = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return substr($ip, 0, 64);
    }

    /** 装饰列表/详情帖子：摘要、图片数组、商品卡片数组 */
    private function decorate(&$f) {
        if (empty($f)) return;
        $title = isset($f['title']) ? trim($f['title']) : '';
        if ($title === '') {
            $content = isset($f['content']) ? strip_tags($f['content']) : '';
            $f['title'] = mb_substr($content, 0, 30, 'UTF-8') . (mb_strlen($content, 'UTF-8') > 30 ? '...' : '');
        }
        $f['images'] = array();
        if (!empty($f['images'])) {
            $imgs = json_decode($f['images'], true);
            if (is_array($imgs)) {
                foreach (array_values($imgs) as $img) {
                    $f['images'][] = $this->fixUrl($img);
                }
            }
        }
        $f['pic'] = !empty($f['pic']) ? $this->fixUrl($f['pic']) : '';
        $f['goods'] = array();
        if (!empty($f['goods_data'])) {
            $dec = json_decode($f['goods_data'], true);
            if (is_array($dec)) {
                if (isset($dec['platform'])) {
                    $f['goods'][] = $this->cleanCard($dec);
                } else {
                    foreach ($dec as $c) {
                        if (is_array($c)) $f['goods'][] = $this->cleanCard($c);
                    }
                }
            }
        }
        // 发布者首字母（头像用）
        $poster = isset($f['poster']) ? $f['poster'] : '';
        if (empty($poster) && !empty($f['uid'])) $poster = 'U' . $f['uid'];
        $f['poster'] = $poster ?: '访客';
        $f['initial'] = mb_substr($f['poster'], 0, 1, 'UTF-8');
    }

    private function cleanCard($card) {
        if (!is_array($card) || empty($card['platform']) || empty($card['goodsId'])) {
            return null;
        }
        $allowed = array('tb', 'jd', 'pdd', 'vip');
        if (!in_array($card['platform'], $allowed, true)) return null;
        return array(
            'platform'    => $card['platform'],
            'platformName'=> isset($card['platformName']) ? $card['platformName'] : '',
            'goodsId'     => (string)$card['goodsId'],
            'title'       => isset($card['title']) ? $card['title'] : '',
            'pic'         => $this->fixUrl(isset($card['pic']) ? $card['pic'] : ''),
            'origPrice'   => floatval($card['origPrice'] ?? 0),
            'actPrice'    => floatval($card['actPrice'] ?? 0),
            'coupon'      => floatval($card['coupon'] ?? 0),
            'sales'       => intval($card['sales'] ?? 0),
            'shopName'    => isset($card['shopName']) ? $card['shopName'] : '',
        );
    }

    /** 过滤 HTML（保留 a/img/br/p，去脚本与事件） */
    private function sanitize($html) {
        $html = preg_replace('#<(script|style|iframe|object|embed|link|meta)[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#\s+on[a-z]+\s*=\s*("|\')[^"\']*\1#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*("|\')javascript:[^"\']*\2#i', '', $html);
        return trim($html);
    }

    /** 统一返回（含 data 包裹） */
    private function ok($data = array(), $message = 'success') {
        $this->json(array('code' => 1, 'message' => $message, 'data' => $data));
    }
    private function fail($message = '操作失败', $http = 400) {
        $this->json(array('code' => 0, 'message' => $message), $http);
    }

    private function needOn() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            $this->fail('社区功能已关闭', 403);
        }
    }

    /* ============ 列表页 ============ */

    public function index() {
        $this->options();
        $this->needOn();

        $bid = (int)$this->raw('bid', 0);
        $page = max(1, (int)$this->raw('page', 1));
        $pageSize = 20;

        $boards = $this->q("SELECT `id`,`name` FROM `{pre}bankuai` ORDER BY `px` ASC, `id` ASC");
        $boards = $boards ? $boards : array();

        if ($bid > 0) {
            $groups = $this->q("SELECT `id`,`groupname`,`bankuai_id`,`icon`,`desc`,`member_count` FROM `{pre}group` WHERE `bankuai_id` = {$bid} ORDER BY `px` ASC, `id` ASC");
            $where = "`bankuai_id` = {$bid} AND `status` = 1";
        } else {
            $groups = $this->q("SELECT `id`,`groupname`,`bankuai_id`,`icon`,`desc`,`member_count` FROM `{pre}group` ORDER BY `px` ASC, `id` ASC");
            $where = "`status` = 1";
        }
        $groups = $groups ? $groups : array();
        foreach ($groups as &$g) {
            $g['icon'] = $this->fixUrl($g['icon']);
        }
        unset($g);

        $total = $this->q("SELECT COUNT(*) AS c FROM `{pre}forum` WHERE {$where}");
        $total = !empty($total[0]['c']) ? (int)$total[0]['c'] : 0;

        $offset = ($page - 1) * $pageSize;
        $list = $this->q("SELECT * FROM `{pre}forum` WHERE {$where} ORDER BY `id` DESC LIMIT {$offset}, {$pageSize}");
        $list = $list ? $list : array();
        foreach ($list as &$f) {
            $this->decorate($f);
        }
        unset($f);

        $this->ok(array(
            'forum_on'   => $this->getSwitch('forum_on', '1'),
            'max_chars'  => (int)$this->getSwitch('forum_max_chars', '300'),
            'max_images' => (int)$this->getSwitch('forum_max_images', '6'),
            'max_links'  => (int)$this->getSwitch('forum_max_links', '3'),
            'boards'     => $boards,
            'groups'     => $groups,
            'list'       => $list,
            'current_bid'=> $bid,
            'page'       => $page,
            'page_size'  => $pageSize,
            'total'      => $total,
            'has_more'   => ($offset + count($list)) < $total,
        ));
    }

    /* ============ 详情页 ============ */

    public function view() {
        $this->options();
        $this->needOn();

        $id = (int)$this->raw('id', 0);
        if ($id <= 0) $this->fail('参数错误');

        $forum = $this->q("SELECT * FROM `{pre}forum` WHERE `id` = ? AND `status` = 1", array($id));
        if (empty($forum)) $this->fail('帖子不存在或已隐藏');
        $forum = $forum[0];
        $this->decorate($forum);

        // 浏览 +1
        $this->q("UPDATE `{pre}forum` SET `view` = `view` + 1 WHERE `id` = ?", array($id));
        $forum['view'] = (int)$forum['view'] + 1;

        // 小组 / 板块信息
        $group = $this->q("SELECT `id`,`groupname`,`icon`,`desc` FROM `{pre}group` WHERE `id` = ?", array((int)$forum['groupid']));
        $forum['group'] = !empty($group[0]) ? array_merge($group[0], array('icon' => $this->fixUrl($group[0]['icon']))) : null;
        if (!empty($forum['bankuai_id'])) {
            $bk = $this->q("SELECT `id`,`name` FROM `{pre}bankuai` WHERE `id` = ?", array((int)$forum['bankuai_id']));
            $forum['board'] = !empty($bk[0]) ? $bk[0] : null;
        } else {
            $forum['board'] = null;
        }

        // 回复（嵌套）
        $replies = $this->q("SELECT * FROM `{pre}forum_reply` WHERE `forum_id` = ? AND `hide` = 'n' ORDER BY `id` ASC LIMIT 200", array($id));
        $replies = $replies ? $replies : array();
        $tree = $this->buildTree($replies);

        // 点赞状态
        $hasLiked = false;
        $user = $this->loginUser();
        if (!empty($user)) {
            $liked = $this->q("SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'forum' AND `uid` = ? LIMIT 1", array($id, (int)$user['id']));
            $hasLiked = !empty($liked);
        } else {
            $v = $this->visitorToken();
            if ($v !== '') {
                $liked = $this->q("SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'forum' AND `cookie` = ? LIMIT 1", array($id, $v));
                $hasLiked = !empty($liked);
            }
        }

        $this->ok(array(
            'forum'     => $forum,
            'replies'   => $tree,
            'reply_count' => count($replies),
            'has_liked' => $hasLiked,
        ));
    }

    private function buildTree($replies) {
        if (empty($replies)) return array();
        $map = array();
        foreach ($replies as &$r) {
            $r['children'] = array();
            $r['poster'] = !empty($r['poster']) ? $r['poster'] : '访客';
            $r['initial'] = mb_substr($r['poster'], 0, 1, 'UTF-8');
            $r['like_count'] = isset($r['like_count']) ? (int)$r['like_count'] : 0;
            $map[(int)$r['id']] = $r;
        }
        unset($r);
        $tree = array();
        foreach ($map as $rid => $node) {
            $pid = (int)$node['pid'];
            if ($pid > 0 && isset($map[$pid])) {
                $map[$pid]['children'][] = &$map[$rid];
            } else {
                $tree[] = &$map[$rid];
            }
        }
        return $tree;
    }

    /* ============ 发帖 ============ */

    public function post() {
        $this->options();
        $this->needOn();
        if (!$this->isPost()) $this->fail('请求方式错误');

        $gid = (int)$this->raw('gid', 0);
        $bid = (int)$this->raw('bid', 0);
        if ($gid <= 0) $this->fail('请选择小组');

        $groupRow = $this->q("SELECT `id`,`bankuai_id` FROM `{pre}group` WHERE `id` = ? LIMIT 1", array($gid));
        if (empty($groupRow)) $this->fail('小组不存在');
        if ($bid <= 0) $bid = (int)$groupRow[0]['bankuai_id'];

        $content = $this->sanitize(trim($this->raw('content', '')));
        if ($content === '') $this->fail('请填写内容');
        $maxChars = (int)$this->getSwitch('forum_max_chars', '300');
        if (mb_strlen(strip_tags($content), 'UTF-8') > $maxChars) $this->fail("内容不能超过 {$maxChars} 字");

        $title = htmlspecialchars(trim($this->raw('title', '')), ENT_QUOTES, 'UTF-8');

        // 图片
        $imagesJson = '';
        $rawImages = $this->raw('images', '');
        if (!empty($rawImages)) {
            $imgs = is_array($rawImages) ? $rawImages : json_decode($rawImages, true);
            if (is_array($imgs)) {
                $maxImages = (int)$this->getSwitch('forum_max_images', '6');
                $imgs = array_slice($imgs, 0, $maxImages);
                $clean = array();
                foreach ($imgs as $url) {
                    $url = filter_var($url, FILTER_VALIDATE_URL);
                    if ($url && strpos($url, $this->base() . '/data/uploadfile/') === 0) {
                        $clean[] = parse_url($url, PHP_URL_PATH);
                    }
                }
                if (!empty($clean)) $imagesJson = json_encode($clean);
            }
        }

        // 商品卡片
        $goodsJson = '';
        $rawGoods = $this->raw('goods_data', '');
        if (!empty($rawGoods)) {
            $cards = is_array($rawGoods) ? $rawGoods : json_decode($rawGoods, true);
            if (is_array($cards)) {
                $maxLinks = (int)$this->getSwitch('forum_max_links', '3');
                if (isset($cards['platform'])) $cards = array($cards);
                $cards = array_slice($cards, 0, $maxLinks);
                $out = array();
                foreach ($cards as $c) {
                    $cc = $this->cleanCard($c);
                    if ($cc) $out[] = $cc;
                }
                if (!empty($out)) $goodsJson = json_encode($out);
            }
        }

        $user = $this->loginUser();
        $ip = $this->clientIp();
        $uid = 0;
        $poster = '';
        $mail = '';
        if (!empty($user)) {
            $uid = (int)$user['id'];
            $poster = $user['username'];
        } else {
            $poster = trim($this->raw('poster', ''));
            $mail = trim($this->raw('mail', ''));
            if (mb_strlen($poster, 'UTF-8') < 1 || mb_strlen($poster, 'UTF-8') > 30) $this->fail('请填写昵称（1-30字）');
            if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) $this->fail('邮箱格式不正确');
            $poster = htmlspecialchars($poster, ENT_QUOTES, 'UTF-8');
            $this->visitorToken();
        }

        if ($title === '') {
            $title = mb_substr(strip_tags($content), 0, 30, 'UTF-8');
        }

        $data = array(
            'groupid'     => $gid,
            'bankuai_id'  => $bid,
            'uid'         => $uid,
            'poster'      => $poster,
            'mail'        => $mail,
            'title'       => $title,
            'pic'         => !empty($imagesJson) ? json_decode($imagesJson, true)[0] : '',
            'images'      => $imagesJson,
            'goods_data'  => $goodsJson,
            'content'     => $content,
            'view'        => 0,
            'reply_count' => 0,
            'like'        => 0,
            'ip'          => $ip,
            'status'      => 1,
            'date'        => date('Y-m-d H:i:s'),
        );
        $newId = obj('api/ApiData')->insertData('yun_forum', $data);
        $this->ok(array('id' => $newId), '发布成功');
    }

    /* ============ 回复 ============ */

    public function reply() {
        $this->options();
        $this->needOn();
        if (!$this->isPost()) $this->fail('请求方式错误');

        $forumId = (int)$this->raw('id', 0);
        if ($forumId <= 0) $this->fail('参数错误');
        $pid = (int)$this->raw('pid', 0);
        $body = htmlspecialchars(trim($this->raw('mybody', '')), ENT_QUOTES, 'UTF-8');
        if ($body === '') $this->fail('请填写回复');
        if (mb_strlen($body, 'UTF-8') > 1000) $this->fail('回复内容过长');

        $user = $this->loginUser();
        $ip = $this->clientIp();
        $uid = 0;
        $poster = '';
        $mail = '';
        if (!empty($user)) {
            $uid = (int)$user['id'];
            $poster = $user['username'];
        } else {
            $poster = trim($this->raw('poster', ''));
            $mail = trim($this->raw('mail', ''));
            if (mb_strlen($poster, 'UTF-8') < 1 || mb_strlen($poster, 'UTF-8') > 30) $this->fail('请填写昵称');
            $poster = htmlspecialchars($poster, ENT_QUOTES, 'UTF-8');
            $this->visitorToken();
        }

        $hide = $this->getSwitch('comment_check', '0') === '1' ? 'y' : 'n';
        if ($pid > 0) {
            $parent = $this->q("SELECT `poster` FROM `{pre}forum_reply` WHERE `id` = ? LIMIT 1", array($pid));
            if (!empty($parent[0]['poster'])) {
                $body = '@' . $parent[0]['poster'] . '：' . $body;
            }
        }

        $data = array(
            'forum_id' => $forumId,
            'pid'      => $pid,
            'uid'      => $uid,
            'poster'   => $poster,
            'mail'     => $mail,
            'content'  => $body,
            'ip'       => $ip,
            'like_count' => 0,
            'hide'     => $hide,
            'date'     => date('Y-m-d H:i:s'),
        );
        $newId = obj('api/ApiData')->insertData('yun_forum_reply', $data);
        if ($hide === 'n') {
            $this->q("UPDATE `{pre}forum` SET `reply_count` = `reply_count` + 1 WHERE `id` = ?", array($forumId));
        }

        $this->ok(array(
            'rid'      => $newId,
            'username' => $poster ?: '访客',
            'initial'  => mb_substr($poster ?: '访客', 0, 1, 'UTF-8'),
            'content'  => $body,
            'date'     => $data['date'],
            'hide'     => $hide,
        ), $hide === 'y' ? '回复已提交，审核后显示' : '回复成功');
    }

    /* ============ 点赞 ============ */

    public function like() {
        $this->options();
        $this->needOn();

        $id = (int)$this->raw('id', 0);
        if ($id <= 0) $this->fail('参数错误');

        $user = $this->loginUser();
        $ip = $this->clientIp();
        $cookie = $this->visitorToken();
        $uid = !empty($user) ? (int)$user['id'] : 0;

        if ($uid > 0) {
            $exists = $this->q("SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'forum' AND `uid` = ? LIMIT 1", array($id, $uid));
        } else {
            if ($cookie === '') $this->fail('请先登录后再点赞');
            $exists = $this->q("SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'forum' AND `cookie` = ? LIMIT 1", array($id, $cookie));
        }
        if (!empty($exists)) $this->ok(array('count' => (int)$this->forumLikeCount($id), 'liked' => true), '已点过赞');

        $this->q("UPDATE `{pre}forum` SET `like` = `like` + 1 WHERE `id` = ?", array($id));
        obj('api/ApiData')->executeQuery(
            "INSERT INTO `{pre}like` (`fid`, `uid`, `model`, `ip`, `cookie`, `date`) VALUES (?, ?, 'forum', ?, ?, ?)",
            array($id, $uid, $ip, $cookie, date('Y-m-d H:i:s'))
        );
        $this->ok(array('count' => (int)$this->forumLikeCount($id), 'liked' => true), '点赞成功');
    }

    private function forumLikeCount($id) {
        $row = $this->q("SELECT `like` FROM `{pre}forum` WHERE `id` = ? LIMIT 1", array($id));
        return !empty($row[0]['like']) ? (int)$row[0]['like'] : 0;
    }

    /* ============ 图片上传 ============ */

    public function upload() {
        $this->options();
        $this->needOn();
        if (empty($_FILES['file'])) $this->fail('请选择文件');

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) $this->fail('上传失败错误码：' . $file['error']);
        if ($file['size'] > 5 * 1024 * 1024) $this->fail('图片不能超过 5MB');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) $this->fail('仅支持 jpg/png/gif/webp');
        if (!@getimagesize($file['tmp_name'])) $this->fail('图片格式不正确');

        $dir = ROOT_PATH . 'data/uploadfile/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $filename = 'forum_' . date('Ymd') . '_' . substr(md5(uniqid('', true)), 0, 16) . '.' . $ext;
        $target = $dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) $this->fail('保存失败');

        $url = '/data/uploadfile/' . $filename;
        $this->ok(array('url' => $url), '上传成功');
    }

    /* ============ 商品链接解析 ============ */

    public function parse() {
        $this->options();
        $this->needOn();
        if ($this->getSwitch('forum_link_card', '1') !== '1') $this->fail('链接转链功能已关闭');

        $url = trim($this->raw('url', ''));
        if ($url === '') $this->fail('请输入链接');
        if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 'http') !== 0) {
            $url = 'https://' . ltrim($url, '/');
        }

        $platform = '';
        $name = '';
        if (preg_match('#(taobao|tmall|liangxinyao)\.com#i', $url)) { $platform = 'tb'; $name = '淘宝/天猫'; }
        elseif (preg_match('#jd\.com#i', $url)) { $platform = 'jd'; $name = '京东'; }
        elseif (preg_match('#(pinduoduo|yangkeduo)\.com#i', $url)) { $platform = 'pdd'; $name = '拼多多'; }
        elseif (preg_match('#(vip|vipshop)\.com#i', $url)) { $platform = 'vip'; $name = '唯品会'; }
        else $this->fail('暂不支持该链接，仅支持淘宝/京东/拼多多/唯品会');

        try {
            include CONFIG_PATH . 'apiset.php';
            $tjk = new \ZhiCms\ext\Tjk(array(
                'DtkappKey' => $api['dtk_appkey'] ?? '',
                'DtkappSecret' => $api['dtk_appsecret'] ?? '',
                'HdkApiKey' => $api['hdk_appkey'] ?? '',
            ));

            $goodsId = $this->extractGoodsId($url, $platform);
            if (empty($goodsId) && $platform === 'tb') {
                $parsed = $tjk->parseContent($url, 'dtk');
                if (!empty($parsed['data']['goodsId'])) $goodsId = $parsed['data']['goodsId'];
            }
            if (empty($goodsId)) $this->fail('无法识别商品ID，请粘贴商品详情页完整链接');

            $detail = array();
            if ($platform === 'tb') {
                $dtk = $tjk->getDtk();
                if ($dtk) {
                    $r = $dtk->GetGoodsDetails($goodsId);
                    if (!empty($r['data'])) $detail = $r['data'];
                }
            } else {
                $hdk = $tjk->getHdk();
                if ($hdk) {
                    $r = $hdk->GetGoodsDetails($goodsId);
                    if (!empty($r['data'])) $detail = $r['data'];
                }
            }

            $card = array(
                'platform'    => $platform,
                'platformName'=> $name,
                'goodsId'     => (string)$goodsId,
                'title'       => isset($detail['title']) ? $detail['title'] : ($detail['itemshorttitle'] ?? ''),
                'pic'         => isset($detail['mainPic']) ? $detail['mainPic'] : ($detail['itempic'] ?? ''),
                'origPrice'   => floatval($detail['originalPrice'] ?? $detail['itemendprice'] ?? 0),
                'actPrice'    => floatval($detail['actualPrice'] ?? $detail['itemendprice'] ?? 0),
                'coupon'      => floatval($detail['couponPrice'] ?? $detail['couponmoney'] ?? 0),
                'sales'       => intval($detail['monthSales'] ?? $detail['itemsale'] ?? 0),
                'shopName'    => isset($detail['shopName']) ? $detail['shopName'] : '',
            );
            $this->ok(array('card' => $card), '解析成功');
        } catch (\Throwable $e) {
            $this->fail('解析失败：' . $e->getMessage());
        }
    }

    private function extractGoodsId($url, $platform) {
        $id = '';
        if ($platform === 'tb') {
            if (preg_match('/[?&]id=(\d+)/', $url, $m)) $id = $m[1];
        } elseif ($platform === 'jd') {
            if (preg_match('#jd\.com/(\d+)#i', $url, $m)) $id = $m[1];
            elseif (preg_match('/[?&]id=(\d+)/', $url, $m)) $id = $m[1];
        } elseif ($platform === 'pdd') {
            if (preg_match('/goods_id=(\d+)/', $url, $m)) $id = $m[1];
        } elseif ($platform === 'vip') {
            if (preg_match('#detail-(\d+)#i', $url, $m)) $id = $m[1];
        }
        return $id;
    }
}
