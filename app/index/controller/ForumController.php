<?php
namespace app\index\controller;

/**
 * 微社区控制器（v2 微博化版）
 * - 板块（yun_bankuai）= 官方分区（顶级 Tab）
 * - 小组（yun_group）= 兴趣圈（可归属板块，无归属显示在"综合"）
 * - 帖子（yun_forum）= 微博式（300字+多图+电商链接转链）
 * - 回复（yun_forum_reply）= 无限嵌套
 */
class ForumController extends \app\base\controller\BaseController {

    private function getSwitch($key, $default = '1') {
        $row = obj("api/ApiData")->thisQuery(
            "SELECT `value` FROM `{pre}config` WHERE `key` = ?",
            array($key)
        );
        return !empty($row[0]['value']) ? $row[0]['value'] : $default;
    }

    private function getClientIp() {
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

    private function getLoginUser() {
        if (empty($_COOKIE['ZhiCmsUser'])) return null;
        return obj("index/global", "controller")->findUser("y", $_COOKIE['ZhiCmsUser'], "cookie");
    }

    private function getVisitorCookie() {
        if (!empty($_COOKIE['zhicms_visitor'])) return $_COOKIE['zhicms_visitor'];
        $token = 'v_' . substr(md5(uniqid('', true) . microtime()), 0, 24);
        setcookie('zhicms_visitor', $token, time() + 86400 * 30, '/');
        return $token;
    }

    /**
     * 加载侧边栏：热门帖子 + 板块列表（同时继承父级分类/热门文章等）
     */
    protected function loadCommonSidebar() {
        // 先调用父级方法，加载分类目录、热门文章、站点统计等公共数据
        parent::loadCommonSidebar();

        // 再加载论坛特有的侧边栏数据
        $hotForums = obj("api/ApiData")->thisQuery(
            "SELECT `id`,`title`,`pic`,`images`,`view`,`reply_count`,`like`,`date` FROM `{pre}forum` WHERE `status` = 1 ORDER BY `view` DESC LIMIT 5"
        );
        $this->hotForums = $hotForums ? $hotForums : array();

        $boards = obj("api/ApiData")->dataSelect("yun_bankuai", array("1"), "`px` ASC, `id` ASC");
        $this->boards = $boards ? $boards : array();
    }

    /**
     * 解析帖子摘要（用于列表展示）
     */
    private function decorateForum(&$forum) {
        if (empty($forum)) return;
        // 摘要：标题优先，无标题取正文前30字
        $title = isset($forum['title']) ? trim($forum['title']) : '';
        if ($title === '') {
            $content = isset($forum['content']) ? strip_tags($forum['content']) : '';
            $forum['title'] = mb_substr($content, 0, 30, 'UTF-8') . (mb_strlen($content, 'UTF-8') > 30 ? '...' : '');
        }
        // 图片数组
        $forum['imagesArr'] = array();
        if (!empty($forum['images'])) {
            $imgs = json_decode($forum['images'], true);
            if (is_array($imgs)) $forum['imagesArr'] = array_values($imgs);
        }
        // 商品卡片（兼容 v1 单对象 / v2 数组）
        $forum['goodsCards'] = array();
        $forum['goodsCard'] = null; // 兼容旧模板
        if (!empty($forum['goods_data'])) {
            $decoded = json_decode($forum['goods_data'], true);
            if (is_array($decoded)) {
                // v1 单对象（有 platform 字段）
                if (isset($decoded['platform'])) {
                    $forum['goodsCards'][] = $decoded;
                    $forum['goodsCard'] = $decoded;
                } else {
                    // v2 索引数组
                    foreach ($decoded as $c) {
                        if (is_array($c) && !empty($c['platform'])) {
                            $forum['goodsCards'][] = $c;
                        }
                    }
                    if (!empty($forum['goodsCards'])) {
                        $forum['goodsCard'] = $forum['goodsCards'][0];
                    }
                }
            }
        }
    }

    /**
     * 社区首页：支持板块 Tab 筛选
     */
    public function index() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            $this->error('社区功能已关闭');
            return;
        }

        // 板块列表
        $boards = obj("api/ApiData")->dataSelect("yun_bankuai", array("1"), "`px` ASC, `id` ASC");
        $this->boards = $boards ? $boards : array();

        // 当前板块
        $bid = (int)$this->arg("bid");
        $this->currentBid = $bid;

        // 小组列表（按板块筛选，bid=0 表示"综合"）
        if ($bid > 0) {
            $groups = obj("api/ApiData")->dataSelect("yun_group", array("`bankuai_id` = {$bid}"), "`px` ASC, `id` ASC");
        } else {
            $groups = obj("api/ApiData")->dataSelect("yun_group", array("1"), "`px` ASC, `id` ASC");
        }
        $this->groups = $groups ? $groups : array();

        // 帖子列表
        if ($bid > 0) {
            $where = array("`bankuai_id` = {$bid} AND `status` = 1");
        } else {
            $where = array("`status` = 1");
        }
        $baseUrl = $bid > 0
            ? url($route='index/forum/index/bid=<bid>', $params=array('bid' => $bid))
            : url($route='index/forum/index', $params=array());
        $page = obj('api/ApiData')->page("20", "yun_forum", $where, "`id` DESC", $baseUrl);
        // 装饰帖子数据
        if (!empty($page['list'])) {
            foreach ($page['list'] as &$f) {
                $this->decorateForum($f);
            }
            unset($f);
        }
        $this->page = $page;

        $this->loginUser = $this->getLoginUser();
        $this->maxChars = (int)$this->getSwitch('forum_max_chars', '300');
        $this->maxImages = (int)$this->getSwitch('forum_max_images', '6');
        $this->maxLinks = (int)$this->getSwitch('forum_max_links', '3');
        $this->loadCommonSidebar();

        // SEO：社区首页
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $boardName = '';
        foreach ($boards ?: [] as $b) { if ((int)$b['id'] === $bid) { $boardName = $b['name']; break; } }
        $this->pageTitle = ($bid > 0 && $boardName ? $boardName . ' - ' : '') . '社区 - ' . $siteName;
        $this->pageKeywords = '社区,好物分享,种草' . ($boardName ? ',' . $boardName : '');
        $this->pageDescription = '好物分享社区，发现全网高性价比商品' . ($boardName ? '，' . $boardName . '专区' : '');
        $this->canonicalUrl = $bid > 0
            ? url($route='index/forum/index/bid=<bid>', $params=array('bid' => $bid))
            : url($route='index/forum/index', $params=array());

        $this->display();
    }

    /**
     * 小组帖子列表
     */
    public function group() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            $this->error('社区功能已关闭');
            return;
        }
        $gid = (int)$this->arg("gid");
        if ($gid <= 0) { $this->redirect(url($route='index/forum/index', $params=array())); return; }

        $groupWhere = array("`id` = {$gid}");
        $group = obj("api/ApiData")->dataSelect("yun_group", $groupWhere);
        if (empty($group)) { $this->redirect(url($route='index/forum/index', $params=array())); return; }
        $this->group = $group[0];

        $where = array("`groupid` = {$gid} AND `status` = 1");
        $baseUrl = url($route='index/forum/group/gid=<gid>', $params=array('gid' => $gid));
        $page = obj('api/ApiData')->page("20", "yun_forum", $where, "`id` DESC", $baseUrl);
        if (!empty($page['list'])) {
            foreach ($page['list'] as &$f) {
                $this->decorateForum($f);
            }
            unset($f);
        }
        $this->page = $page;

        $this->loginUser = $this->getLoginUser();
        $this->maxChars = (int)$this->getSwitch('forum_max_chars', '300');
        $this->maxImages = (int)$this->getSwitch('forum_max_images', '6');
        $this->maxLinks = (int)$this->getSwitch('forum_max_links', '3');
        $this->loadCommonSidebar();

        // SEO：小组页
        $groupName = $group[0]['name'] ?? '小组';
        $groupDesc = $group[0]['des'] ?? '';
        $this->pageTitle = $groupName . ' - 社区 - ' . obj('base/Base')->SiteConfig('sitename');
        $this->pageKeywords = $groupName . ',社区,好物分享';
        $this->pageDescription = $groupDesc ? mb_substr(strip_tags($groupDesc), 0, 180, 'UTF-8') : ($groupName . '小组，发现好物分享');
        $this->canonicalUrl = url($route='index/forum/group/gid=<gid>', $params=array('gid'=>$gid));

        $this->display();
    }

    /**
     * 帖子详情
     */
    public function view() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            $this->error('社区功能已关闭');
            return;
        }
        $id = (int)$this->arg("id");
        if ($id <= 0) { $this->redirect(url($route='index/forum/index', $params=array())); return; }

        $where = array("`id` = {$id} AND `status` = 1");
        $forum = obj("api/ApiData")->dataSelect("yun_forum", $where);
        if (empty($forum)) { $this->redirect(url($route='index/forum/index', $params=array())); return; }
        $forum = $forum[0];
        $this->decorateForum($forum);

        // 浏览量 +1
        obj("api/ApiData")->executeQuery(
            "UPDATE `{pre}forum` SET `view` = `view` + 1 WHERE `id` = ?",
            array($id)
        );
        $forum['view'] = (int)$forum['view'] + 1;
        $this->forum = $forum;

        // 小组信息
        $groupWhere = array("`id` = " . (int)$forum['groupid']);
        $group = obj("api/ApiData")->dataSelect("yun_group", $groupWhere);
        $this->group = !empty($group[0]) ? $group[0] : null;

        // 板块信息
        if (!empty($forum['bankuai_id'])) {
            $bk = obj("api/ApiData")->thisQuery(
                "SELECT `id`,`name` FROM `{pre}bankuai` WHERE `id` = ? LIMIT 1",
                array((int)$forum['bankuai_id'])
            );
            $this->board = !empty($bk[0]) ? $bk[0] : null;
        } else {
            $this->board = null;
        }

        // 回复列表（嵌套）
        $replies = obj("api/ApiData")->thisQuery(
            "SELECT * FROM `{pre}forum_reply` WHERE `forum_id` = ? AND `hide` = 'n' ORDER BY `id` ASC LIMIT 200",
            array($id)
        );
        $replies = $replies ? $replies : array();
        $replyTree = $this->buildReplyTree($replies);
        $this->replyHtml = $this->renderReplyTree($replyTree, 0);
        $this->replyCount = count($replies);

        // 当前用户是否已点赞
        $loginUser = $this->getLoginUser();
        $this->loginUser = $loginUser;
        $this->hasLiked = false;
        $ip = $this->getClientIp();
        $cookie = $this->getVisitorCookie();
        if (!empty($loginUser)) {
            $liked = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'forum' AND `uid` = ? LIMIT 1",
                array($id, (int)$loginUser['id'])
            );
            $this->hasLiked = !empty($liked);
        } else {
            $liked = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'forum' AND `cookie` = ? LIMIT 1",
                array($id, $cookie)
            );
            $this->hasLiked = !empty($liked);
        }

        $this->visitorName = isset($_COOKIE['zhicms_comment_name']) ? $_COOKIE['zhicms_comment_name'] : '';
        $this->visitorMail = isset($_COOKIE['zhicms_comment_mail']) ? $_COOKIE['zhicms_comment_mail'] : '';

        $this->loadCommonSidebar();

        // SEO：帖子详情页
        $postTitle = isset($forum['title']) ? trim($forum['title']) : '';
        $postContent = isset($forum['content']) ? strip_tags($forum['content']) : '';
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $groupName = isset($this->group['name']) ? $this->group['name'] : '';
        $this->pageTitle = ($postTitle ?: '帖子详情') . ' - ' . ($groupName ? $groupName . ' - ' : '') . '社区 - ' . $siteName;
        $this->pageKeywords = $postTitle . ',社区,好物分享' . ($groupName ? ',' . $groupName : '');
        $this->pageDescription = mb_substr($postContent, 0, 180, 'UTF-8') ?: ($postTitle ?: '社区好物分享帖子');
        $this->canonicalUrl = url($route='index/forum/view/id=<id>', $params=array('id'=>$id));
        // Open Graph 图片：帖子第一张图
        if (!empty($forum['pic'])) {
            $this->ogImage = $forum['pic'];
        }

        $this->display();
    }

    /**
     * 发帖（v2 微博化：300字 + 多图 + 商品卡片）
     */
    public function postForum() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            exit(json_encode(array("info" => "社区功能已关闭", "status" => "n")));
        }
        if (!\IS_POST) {
            exit(json_encode(array("info" => "请求方式错误", "status" => "n")));
        }

        $gid = (int)$this->arg("gid");
        $bid = (int)$this->arg("bid");
        if ($gid <= 0) {
            exit(json_encode(array("info" => "请选择小组", "status" => "n")));
        }

        // 校验小组存在并自动获取所属板块
        $groupRow = obj("api/ApiData")->thisQuery(
            "SELECT `id`,`bankuai_id` FROM `{pre}group` WHERE `id` = ? LIMIT 1",
            array($gid)
        );
        if (empty($groupRow)) {
            exit(json_encode(array("info" => "小组不存在", "status" => "n")));
        }
        // 若用户未传板块，使用小组的板块
        if ($bid <= 0) $bid = (int)$groupRow[0]['bankuai_id'];

        // 内容（微博化：300字限制）
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        // 允许部分 HTML（链接、图片），但脚本和事件过滤
        $content = $this->sanitizeHtml($content);
        if ($content === '') {
            exit(json_encode(array("info" => "请填写内容", "status" => "n")));
        }
        $maxChars = (int)$this->getSwitch('forum_max_chars', '300');
        if (mb_strlen(strip_tags($content), 'UTF-8') > $maxChars) {
            exit(json_encode(array("info" => "内容不能超过 {$maxChars} 字", "status" => "n")));
        }

        // 标题（可选，不填用正文前 30 字）
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        // 图片列表（JSON 数组）
        $imagesJson = '';
        if (!empty($_POST['images'])) {
            $imgs = is_array($_POST['images']) ? $_POST['images'] : json_decode($_POST['images'], true);
            if (is_array($imgs)) {
                $maxImages = (int)$this->getSwitch('forum_max_images', '6');
                $imgs = array_slice($imgs, 0, $maxImages);
                $clean = array();
                foreach ($imgs as $url) {
                    $url = trim($url);
                    if ($url && strpos($url, 'upload/forum/') !== false) {
                        $clean[] = $url;
                    }
                }
                if (!empty($clean)) $imagesJson = json_encode($clean);
            }
        }

        // 商品卡片数据（支持数组：v2 多链接，v1 单对象）
        $goodsJson = '';
        if (!empty($_POST['goods_data'])) {
            $raw = is_array($_POST['goods_data']) ? $_POST['goods_data'] : json_decode($_POST['goods_data'], true);
            $cards = array();
            if (is_array($raw)) {
                // 兼容：v1 单对象（关联数组） vs v2 数组（索引数组）
                if (isset($raw['platform'])) {
                    // 单个对象
                    $c = $this->cleanGoodsCard($raw);
                    if ($c) $cards[] = $c;
                } else {
                    // 索引数组（多个对象）
                    $maxLinks = (int)$this->getSwitch('forum_max_links', '3');
                    $raw = array_slice($raw, 0, $maxLinks);
                    foreach ($raw as $card) {
                        $c = $this->cleanGoodsCard($card);
                        if ($c) $cards[] = $c;
                    }
                }
            }
            if (!empty($cards)) $goodsJson = json_encode($cards);
        }

        // 用户身份
        $loginUser = $this->getLoginUser();
        $ip = $this->getClientIp();
        $uid = 0;
        $poster = '';
        $mail = '';

        if (!empty($loginUser)) {
            $uid = (int)$loginUser['id'];
            $poster = $loginUser['username'];
        } else {
            $poster = isset($_POST['poster']) ? trim($_POST['poster']) : '';
            $mail = isset($_POST['mail']) ? trim($_POST['mail']) : '';
            if (mb_strlen($poster, 'UTF-8') < 1 || mb_strlen($poster, 'UTF-8') > 30) {
                exit(json_encode(array("info" => "请填写昵称（1-30字）", "status" => "n")));
            }
            if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                exit(json_encode(array("info" => "邮箱格式不正确", "status" => "n")));
            }
            $poster = htmlspecialchars($poster, ENT_QUOTES, 'UTF-8');
            setcookie('zhicms_comment_name', $poster, time() + 86400 * 30, '/');
            setcookie('zhicms_comment_mail', $mail, time() + 86400 * 30, '/');
            $this->getVisitorCookie();
        }

        // 若无标题，自动取正文前30字
        if ($title === '') {
            $plainText = strip_tags($content);
            $title = mb_substr($plainText, 0, 30, 'UTF-8');
        }

        $data = array(
            'groupid' => $gid,
            'bankuai_id' => $bid,
            'uid' => $uid,
            'poster' => $poster,
            'mail' => $mail,
            'title' => $title,
            'pic' => !empty($imagesJson) ? json_decode($imagesJson, true)[0] : '',
            'images' => $imagesJson,
            'goods_data' => $goodsJson,
            'content' => $content,
            'view' => 0,
            'reply_count' => 0,
            'like' => 0,
            'ip' => $ip,
            'status' => 1,
            'date' => date("Y-m-d H:i:s", time())
        );
        $newId = obj("api/ApiData")->insertData("yun_forum", $data);

        exit(json_encode(array(
            "info" => "发布成功",
            "status" => "y",
            "url" => url($route='index/forum/view/id=<id>', $params=array('id' => $newId))
        )));
    }

    /**
     * 简易 HTML 过滤：保留 a/img/br/p，过滤脚本和事件
     */
    private function sanitizeHtml($html) {
        // 移除 script/style/iframe 标签
        $html = preg_replace('#<(script|style|iframe|object|embed|link|meta)[^>]*>.*?</\1>#is', '', $html);
        // 移除事件属性 on*
        $html = preg_replace('#\s+on[a-z]+\s*=\s*("|\')[^"\']*\1#i', '', $html);
        // 移除 javascript: 协议
        $html = preg_replace('#(href|src)\s*=\s*("|\')javascript:[^"\']*\2#i', '', $html);
        return trim($html);
    }

    /**
     * 清洗单个商品卡片数据
     */
    private function cleanGoodsCard($card) {
        if (!is_array($card) || empty($card['platform']) || empty($card['goodsId'])) {
            return null;
        }
        $allowedPlatforms = array('tb', 'jd', 'pdd', 'vip');
        if (!in_array($card['platform'], $allowedPlatforms, true)) {
            return null;
        }
        return array(
            'platform'   => $card['platform'],
            'goodsId'    => (string)$card['goodsId'],
            'title'      => isset($card['title']) ? mb_substr($card['title'], 0, 100, 'UTF-8') : '',
            'pic'        => isset($card['pic']) ? $card['pic'] : '',
            'origPrice'  => floatval($card['origPrice'] ?? 0),
            'actPrice'   => floatval($card['actPrice'] ?? 0),
            'coupon'     => floatval($card['coupon'] ?? 0),
            'sales'      => intval($card['sales'] ?? 0),
            'shopName'   => isset($card['shopName']) ? $card['shopName'] : '',
            'platformName' => isset($card['platformName']) ? $card['platformName'] : '',
        );
    }

    /**
     * 图片转 WebP 格式
     */
    private function convertToWebP($sourcePath, $targetPath) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        $mime = $imageInfo['mime'];
        $srcImage = false;

        switch ($mime) {
            case 'image/jpeg':
                $srcImage = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $srcImage = @imagecreatefrompng($sourcePath);
                if ($srcImage) {
                    imagealphablending($srcImage, true);
                    imagesavealpha($srcImage, true);
                }
                break;
            case 'image/gif':
                $srcImage = @imagecreatefromgif($sourcePath);
                break;
            case 'image/bmp':
                $srcImage = @imagecreatefrombmp($sourcePath);
                break;
            case 'image/webp':
                @copy($sourcePath, $targetPath);
                return true;
            default:
                return false;
        }

        if (!$srcImage) {
            return false;
        }

        $result = @imagewebp($srcImage, $targetPath, 85);
        imagedestroy($srcImage);

        return $result;
    }

    /**
     * AJAX：图片上传（统一接口 + WebP 转换）
     * 接收 multipart/form-data，存到 upload/forum/{dateDir}/，自动转 WebP
     */
    public function uploadImage() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            exit(json_encode(array("info" => "社区功能已关闭", "status" => "n")));
        }
        if (empty($_FILES['file'])) {
            exit(json_encode(array("info" => "请选择文件", "status" => "n")));
        }
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            exit(json_encode(array("info" => "上传失败错误码：" . $file['error'], "status" => "n")));
        }
        // 大小限制 10MB
        if ($file['size'] > 10485760) {
            exit(json_encode(array("info" => "图片不能超过 10MB", "status" => "n")));
        }
        // 后缀白名单
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'))) {
            exit(json_encode(array("info" => "仅支持 jpg/png/gif/webp/bmp", "status" => "n")));
        }
        // 检查真实图片类型
        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo) {
            exit(json_encode(array("info" => "图片格式不正确", "status" => "n")));
        }

        // 生成目录路径（按日期组织，统一到 upload/forum/{dateDir}/）
        $dateDir = date('Ymd');
        $dir = \ROOT_PATH . 'upload/forum/' . $dateDir;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        // 生成文件名
        $fileName = substr(md5($file['name']), 0, 4) . time();

        // 先保存为临时文件
        $tempPath = $dir . '/' . $fileName . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
            exit(json_encode(array("info" => "保存失败", "status" => "n")));
        }

        // 转换为 WebP
        $webpPath = $dir . '/' . $fileName . '.webp';
        if ($this->convertToWebP($tempPath, $webpPath)) {
            @unlink($tempPath); // 删除临时文件
            $url = '/upload/forum/' . $dateDir . '/' . $fileName . '.webp';
        } else {
            // 转换失败，保留原格式
            $url = '/upload/forum/' . $dateDir . '/' . $fileName . '.' . $ext;
        }

        exit(json_encode(array(
            "info" => "上传成功",
            "status" => "y",
            "url" => $url
        )));
    }

    /**
     * AJAX：解析电商链接 → 返回商品卡片数据
     * 接收 POST url，识别平台（tb/jd/pdd/vip），调用 Tjk 解析
     */
    public function parseLink() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            exit(json_encode(array("info" => "社区功能已关闭", "status" => "n")));
        }
        if ($this->getSwitch('forum_link_card', '1') !== '1') {
            exit(json_encode(array("info" => "链接转链功能已关闭", "status" => "n")));
        }
        $url = isset($_POST['url']) ? trim($_POST['url']) : '';
        if ($url === '') {
            exit(json_encode(array("info" => "请输入链接", "status" => "n")));
        }
        // 简单校验 URL
        if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 'http') !== 0) {
            // 允许裸域名，尝试补协议
            $url = 'https://' . ltrim($url, '/');
        }

        // 识别平台
        $platform = '';
        $name = '';
        if (preg_match('#(taobao|tmall|liangxinyao)\.com#i', $url)) {
            $platform = 'tb'; $name = '淘宝/天猫';
        } elseif (preg_match('#jd\.com#i', $url)) {
            $platform = 'jd'; $name = '京东';
        } elseif (preg_match('#(pinduoduo|yangkeduo)\.com#i', $url)) {
            $platform = 'pdd'; $name = '拼多多';
        } elseif (preg_match('#(vip|vipshop)\.com#i', $url)) {
            $platform = 'vip'; $name = '唯品会';
        } else {
            exit(json_encode(array("info" => "暂不支持该链接，仅支持淘宝/京东/拼多多/唯品会", "status" => "n")));
        }

        try {
            $api = \app\common\ConfigStore::load('api');
            $tjk = new \ZhiCms\ext\Tjk(array(
                'DtkappKey' => $api['dtk_appkey'] ?? '',
                'DtkappSecret' => $api['dtk_appsecret'] ?? '',
                'HdkApiKey' => $api['hdk_appkey'] ?? '',
            ));

            // 提取商品 ID
            $goodsId = $this->extractGoodsId($url, $platform);
            if (empty($goodsId)) {
                // 淘宝尝试用 ParseContent 解析短链
                if ($platform === 'tb') {
                    $parsed = $tjk->parseContent($url, 'dtk');
                    if (!empty($parsed['data']['goodsId'])) {
                        $goodsId = $parsed['data']['goodsId'];
                    }
                }
            }
            if (empty($goodsId)) {
                exit(json_encode(array(
                    "info" => "无法识别商品ID，请粘贴商品详情页完整链接",
                    "status" => "n"
                )));
            }

            // 获取详情
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

            // 统一字段
            $card = array(
                'platform' => $platform,
                'platformName' => $name,
                'goodsId' => (string)$goodsId,
                'title' => isset($detail['title']) ? $detail['title'] : ($detail['itemshorttitle'] ?? ''),
                'pic' => isset($detail['mainPic']) ? $detail['mainPic'] : ($detail['itempic'] ?? ''),
                'origPrice' => floatval($detail['originalPrice'] ?? $detail['itemendprice'] ?? 0),
                'actPrice' => floatval($detail['actualPrice'] ?? $detail['itemendprice'] ?? 0),
                'coupon' => floatval($detail['couponPrice'] ?? $detail['couponmoney'] ?? 0),
                'sales' => intval($detail['monthSales'] ?? $detail['itemsale'] ?? 0),
                'shopName' => isset($detail['shopName']) ? $detail['shopName'] : '',
            );

            exit(json_encode(array(
                "info" => "解析成功",
                "status" => "y",
                "card" => $card
            )));
        } catch (\Throwable $e) {
            exit(json_encode(array(
                "info" => "解析失败：" . $e->getMessage(),
                "status" => "n"
            )));
        }
    }

    /**
     * 从 URL 中提取商品 ID
     */
    private function extractGoodsId($url, $platform) {
        $id = '';
        if ($platform === 'tb') {
            // item.taobao.com/item.htm?id=123 / detail.tmall.com/item.htm?id=123
            if (preg_match('/[?&]id=(\d+)/', $url, $m)) $id = $m[1];
        } elseif ($platform === 'jd') {
            // item.jd.com/123.html
            if (preg_match('#jd\.com/(\d+)#i', $url, $m)) $id = $m[1];
            elseif (preg_match('/[?&]id=(\d+)/', $url, $m)) $id = $m[1];
        } elseif ($platform === 'pdd') {
            // mobile.yangkeduo.com/goods.html?goods_id=123
            if (preg_match('/goods_id=(\d+)/', $url, $m)) $id = $m[1];
        } elseif ($platform === 'vip') {
            // detail.vip.com/detail-123.html
            if (preg_match('#detail-(\d+)#i', $url, $m)) $id = $m[1];
        }
        return $id;
    }

    /**
     * 占位：图片安全检测（后期接入微信 img_sec_check）
     */
    private function checkImageSafety($filePath) {
        // TODO: 接入微信内容安全 API
        // $url = 'https://api.weixin.qq.com/wxa/img_sec_check?access_token=xxx';
        // $data = array('media' => new CURLFile($filePath));
        // $result = httpPost($url, $data);
        // return isset($result['errcode']) && $result['errcode'] === 0;
        return true;
    }

    /**
     * 回复帖子
     */
    public function replyForum() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            exit(json_encode(array("info" => "社区功能已关闭", "status" => "n")));
        }
        if (!\IS_POST) {
            exit(json_encode(array("info" => "请求方式错误", "status" => "n")));
        }

        $forumId = (int)$this->arg("id");
        if ($forumId <= 0) {
            exit(json_encode(array("info" => "参数错误", "status" => "n")));
        }
        $pid = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;

        $rawBody = isset($_POST['mybody']) ? $_POST['mybody'] : '';
        $newBody = htmlspecialchars(trim($rawBody), ENT_QUOTES, 'UTF-8');
        if ($newBody === '') {
            exit(json_encode(array("info" => "请填写回复", "status" => "n")));
        }
        if (mb_strlen($newBody, 'UTF-8') > 1000) {
            exit(json_encode(array("info" => "回复内容过长", "status" => "n")));
        }

        $loginUser = $this->getLoginUser();
        $ip = $this->getClientIp();
        $uid = 0;
        $poster = '';
        $mail = '';

        if (!empty($loginUser)) {
            $uid = (int)$loginUser['id'];
            $poster = $loginUser['username'];
        } else {
            $poster = isset($_POST['poster']) ? trim($_POST['poster']) : '';
            $mail = isset($_POST['mail']) ? trim($_POST['mail']) : '';
            if (mb_strlen($poster, 'UTF-8') < 1 || mb_strlen($poster, 'UTF-8') > 30) {
                exit(json_encode(array("info" => "请填写昵称", "status" => "n")));
            }
            $poster = htmlspecialchars($poster, ENT_QUOTES, 'UTF-8');
            setcookie('zhicms_comment_name', $poster, time() + 86400 * 30, '/');
            setcookie('zhicms_comment_mail', $mail, time() + 86400 * 30, '/');
            $this->getVisitorCookie();
        }

        $hide = $this->getSwitch('comment_check', '0') === '1' ? 'y' : 'n';

        if ($pid > 0) {
            $parent = obj("api/ApiData")->thisQuery(
                "SELECT `poster` FROM `{pre}forum_reply` WHERE `id` = ? LIMIT 1",
                array($pid)
            );
            if (!empty($parent[0]['poster'])) {
                $newBody = '@' . $parent[0]['poster'] . '：' . $newBody;
            }
        }

        $data = array(
            'forum_id' => $forumId,
            'pid' => $pid,
            'uid' => $uid,
            'poster' => $poster,
            'mail' => $mail,
            'content' => $newBody,
            'ip' => $ip,
            'like_count' => 0,
            'hide' => $hide,
            'date' => date("Y-m-d H:i:s", time())
        );
        $newId = obj("api/ApiData")->insertData("yun_forum_reply", $data);

        // 帖子回复数 +1
        if ($hide === 'n') {
            obj("api/ApiData")->executeQuery(
                "UPDATE `{pre}forum` SET `reply_count` = `reply_count` + 1 WHERE `id` = ?",
                array($forumId)
            );
        }

        $initial = mb_substr($poster ?: '访客', 0, 1, 'UTF-8');

        exit(json_encode(array(
            "info" => $hide === 'y' ? "回复已提交，审核后显示" : "回复成功",
            "status" => "y",
            "rid" => $newId,
            "username" => $poster ?: '访客',
            "initial" => $initial,
            "content" => $newBody,
            "date" => $data['date'],
            "hide" => $hide
        )));
    }

    /**
     * 帖子点赞
     */
    public function likeForum() {
        if ($this->getSwitch('forum_on', '1') !== '1') {
            exit(json_encode(array("info" => "社区功能已关闭", "status" => "n")));
        }
        $id = (int)$this->arg("id");
        if ($id <= 0) {
            exit(json_encode(array("info" => "参数错误", "status" => "n")));
        }

        $loginUser = $this->getLoginUser();
        $ip = $this->getClientIp();
        $cookie = $this->getVisitorCookie();
        $uid = !empty($loginUser) ? (int)$loginUser['id'] : 0;

        if ($uid > 0) {
            $exists = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'forum' AND `uid` = ? LIMIT 1",
                array($id, $uid)
            );
        } else {
            $exists = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'forum' AND `cookie` = ? LIMIT 1",
                array($id, $cookie)
            );
        }
        if (!empty($exists)) {
            exit(json_encode(array("info" => "已点过赞", "status" => "n", "liked" => true)));
        }

        obj("api/ApiData")->executeQuery(
            "UPDATE `{pre}forum` SET `like` = `like` + 1 WHERE `id` = ?",
            array($id)
        );
        obj("api/ApiData")->executeQuery(
            "INSERT INTO `{pre}like` (`fid`, `uid`, `model`, `ip`, `cookie`, `date`) VALUES (?, ?, 'forum', ?, ?, ?)",
            array($id, $uid, $ip, $cookie, date("Y-m-d H:i:s", time()))
        );

        $row = obj("api/ApiData")->thisQuery(
            "SELECT `like` FROM `{pre}forum` WHERE `id` = ? LIMIT 1",
            array($id)
        );
        $count = !empty($row[0]['like']) ? (int)$row[0]['like'] : 0;

        exit(json_encode(array("info" => "点赞成功", "status" => "y", "count" => $count)));
    }

    /**
     * 构建回复嵌套树
     */
    private function buildReplyTree($replies) {
        if (empty($replies)) return array();
        $tree = array();
        $map = array();
        foreach ($replies as &$r) {
            $r['children'] = array();
            $r['displayName'] = !empty($r['poster']) ? $r['poster'] : '访客';
            $r['initial'] = mb_substr($r['displayName'], 0, 1, 'UTF-8');
            $r['like_count'] = isset($r['like_count']) ? (int)$r['like_count'] : 0;
            $map[(int)$r['id']] = $r;
        }
        unset($r);
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

    /**
     * 递归渲染回复树为 HTML
     */
    private function renderReplyTree($tree, $depth = 0) {
        if (empty($tree)) return '';
        $html = '';
        $indent = $depth * 24;
        foreach ($tree as $node) {
            $rid = (int)$node['id'];
            $name = htmlspecialchars($node['displayName'], ENT_QUOTES, 'UTF-8');
            $initial = htmlspecialchars($node['initial'], ENT_QUOTES, 'UTF-8');
            $content = htmlspecialchars($node['content'], ENT_QUOTES, 'UTF-8');
            $date = htmlspecialchars($node['date'], ENT_QUOTES, 'UTF-8');
            $likes = (int)$node['like_count'];
            $html .= '<li class="comment-item" id="reply-' . $rid . '" data-depth="' . $depth . '">';
            $html .= '<span class="c-avatar">' . $initial . '</span>';
            $html .= '<div class="c-body">';
            $html .= '<div class="c-head"><span class="c-name">' . $name . '</span><span class="c-time">' . $date . '</span></div>';
            $html .= '<div class="c-text">' . $content . '</div>';
            $html .= '<div class="c-actions">';
            $html .= '<a href="javascript:;" class="r-reply" data-rid="' . $rid . '" data-name="' . $name . '">回复</a>';
            $html .= '<a href="javascript:;" class="r-like" data-rid="' . $rid . '"><span class="like-icon">♡</span><span class="like-num">' . ($likes > 0 ? $likes : '') . '</span></a>';
            $html .= '</div>';
            if (!empty($node['children'])) {
                $html .= '<ul class="comment-list comment-children" style="margin-left:' . $indent . 'px">';
                $html .= $this->renderReplyTree($node['children'], $depth + 1);
                $html .= '</ul>';
            }
            $html .= '</div></li>';
        }
        return $html;
    }
}
