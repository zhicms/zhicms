<?php
namespace app\index\controller;
class IndexController extends \app\base\controller\BaseController
{
    public function index(){
        if (!file_exists(CONFIG_PATH . 'install.lock')) {
            $this->redirect("index.php?r=install");
        }

        $Siteinfo = \app\common\ConfigStore::load('site');

        $where_30m[] = "`date` > DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        $minute = obj("api/ApiData")->dataSelect("yun_article", $where_30m, "`date` DESC LIMIT 0, 9");
        $this->minute = $minute;

        // 加载公共侧边栏数据
        $this->loadCommonSidebar();

        // 侧边栏：最新评论
        $sideComments = obj("api/ApiData")->thisQuery(
            "SELECT c.*, u.username FROM `{pre}comment` c LEFT JOIN `{pre}user` u ON u.id = c.uid WHERE c.`model` = '2' ORDER BY c.`id` DESC LIMIT 6"
        );
        $this->sideComments = $sideComments ? $sideComments : array();

        $where[] = "1";
        $baseUrl = url($route='index/index/index', $params=array());
        $listId = 0;
        $ym = '';

        if ($this->arg("list")) {
            $listId = $this->arg('list');
            if (ctype_digit($listId) && $listId > 0) {
                $where[] = "`cid` = '{$listId}'";
            }
            $baseUrl = url($route='index/index/index/list=<list>', $params=array("list" => $listId));
        }

        if ($this->arg("ym")) {
            $ym = $this->arg('ym');
            if (preg_match('/^\d{6}$/', $ym)) {
                $y = (int)substr($ym, 0, 4);
                $m = (int)substr($ym, 4, 2);
                $m = max(1, min(12, $m));
                $lastDay = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
                // 月份必须两位补零，否则 '2026-7-01' 在 MySQL 中会被判为无效日期而查不到数据
                $where[] = "`date` >= '{$y}-" . sprintf('%02d', $m) . "-01 00:00:00' AND `date` <= '{$y}-" . sprintf('%02d', $m) . "-{$lastDay} 23:59:59'";
                $baseUrl = url($route='index/index/index', $params=array('ym' => $ym));
            }
        }

        $page = obj('api/ApiData')->page("10", "yun_article", $where, "`id` DESC", $baseUrl);
        if ($page && !empty($page['list'])) {
            foreach ($page['list'] as &$item) {
                $item['cateName'] = \app\base\controller\BaseController::getCategoryName($item['cid'] ?? 0);
            }
            unset($item);
        }
        $this->page = $page;

        // 最大 ID（用于前端轮询检查新文章，缓存 60 秒）
        $maxidResult = obj("api/ApiData")->thisQuery("SELECT MAX(id) as maxid FROM `{pre}article`");
        $this->maxid = isset($maxidResult['0']['maxid']) ? $maxidResult['0']['maxid'] : 0;

        // 幻灯片banner（PC）
        $pcBanners = obj('api/ApiData')->dataSelect('yun_huan', ['type'=>0], '`id` DESC LIMIT 0,10');
        $this->banners = $pcBanners ? $pcBanners : [];

        // 友情链接
        $links = obj('api/ApiData')->dataSelect('yun_link', [], '`id` DESC LIMIT 0,10');
        $this->links = $links ? $links : [];

        // ===== SEO：首页/分类/归档页 =====
        $pageNum = max(1, intval($this->arg('page', 1)));
        if (!empty($listId)) {
            // 分类页 SEO
            $cateName = \app\base\controller\BaseController::getCategoryName($listId);
            $siteName = obj('base/Base')->SiteConfig('sitename');
            $this->pageTitle = ($cateName ?: '分类') . ' - 第' . $pageNum . '页 - ' . $siteName;
            $this->pageKeywords = ($cateName ?: '') . ',优惠,折扣';
            $this->pageDescription = ($cateName ?: '') . '分类下的最新折扣信息第' . $pageNum . '页';
            $this->canonicalUrl = obj('base/Base')->SiteConfig('hosturl') . 'index.html?list=' . $listId;
        } elseif (!empty($ym)) {
            // 归档页 SEO
            $y = (int)substr($ym, 0, 4);
            $m = (int)substr($ym, 4, 2);
            $siteName = obj('base/Base')->SiteConfig('sitename');
            $this->pageTitle = $y . '年' . $m . '月 - 文章归档 - ' . $siteName;
            $this->pageKeywords = $y . '年' . $m . '月,文章归档';
            $this->pageDescription = $siteName . ' ' . $y . '年' . $m . '月的文章归档，共' . ($page['count'] ?? 0) . '篇。';
            $this->canonicalUrl = obj('base/Base')->SiteConfig('hosturl') . 'index.html?ym=' . $ym;
        } elseif ($pageNum > 1) {
            // 首页分页
            $siteName = obj('base/Base')->SiteConfig('sitename');
            $this->pageTitle = '首页 - 第' . $pageNum . '页 - ' . $siteName;
            $this->canonicalUrl = obj('base/Base')->SiteConfig('hosturl') . ($pageNum > 1 ? 'index.html?page=' . $pageNum : 'index.html');
        }

        $this->display();
    }	


    public  function welcomeCookie(){

    	setcookie("welcome", "zhicms", time()+6600);
    }

    public function log(){

        echo "callback({ data:\"ok\"})";
    }

    public function checkNum(){

       $maxid=$this->arg("maxid");
       if(!is_numeric($maxid)){
        exit("erorr");
       }

        $maxidSql="SELECT MAX( id ) as maxid FROM  `{pre}article`";
        $mysqlMaxid=obj("api/ApiData")->thisQuery($maxidSql);
        $newMaxid=$mysqlMaxid['0']['maxid']-$maxid;
        echo $newMaxid;


    }


    public function estimate(){
        $lastRet = obj("api/ApiData")->thisQuery("SELECT count(*) as lastdaycount FROM {pre}article WHERE `date` >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $todayRet = obj("api/ApiData")->thisQuery("SELECT count(*) as todaycount FROM {pre}article WHERE `date` >= CURDATE() AND `date` < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");

        $lastDayCount = isset($lastRet['0']['lastdaycount']) ? $lastRet['0']['lastdaycount'] : 0;
        $todayCount = isset($todayRet['0']['todaycount']) ? $todayRet['0']['todaycount'] : 0;

        $newCount = $lastDayCount + $todayCount;

        if ($newCount <= 0) {
            $newCount = 0;
            $bfb = 0;
        } else {
            $bfb = $lastDayCount > 0 ? round($todayCount / $lastDayCount * 100) : 0;
        }

        return array("bfb" => $bfb, "count" => $newCount);
    }
        public function view(){
       $id=$this->arg("id");
       if(!is_numeric($id)){
        exit("error");
       }

       $where[] = "`id` = {$id}";
       $view = obj("api/ApiData")->dataSelect("yun_article", $where);
       // 触发文章内容钩子（插件可改写 $view 或过滤正文）
       \ZhiCms\base\Hook::listen('article_view', array(&$view));
       if (isset($view['content'])) {
           $view['content'] = zhi_apply('article_content', $view['content']);
       }
       $newBody = preg_replace_callback('/\[ZhiCmsUrl](.+?)\[\/ZhiCmsUrl]/', [$this, 'findItems'], urldecode($view['content']));
      $this->newBody = $newBody;
      $view['cateName'] = \app\base\controller\BaseController::getCategoryName($view['cid'] ?? 0);
      // 作者名（无作者时回退为站方小编）+ 首字母头像（需在赋值 $this->view 之前计算）
      $authorName = trim(isset($view['author']) ? $view['author'] : '');
      if ($authorName === '') { $authorName = '值得买小编'; }
      $view['authorName'] = $authorName;
      $view['authorInitial'] = mb_substr($authorName, 0, 1, 'UTF-8');
      $this->view = $view;

      // ===== SEO 优化：文章详情页面标题/关键词/描述 =====
      $siteName = obj('base/Base')->SiteConfig('sitename');
      $articleTitle = isset($view['title']) ? trim($view['title']) : '';
      if ($articleTitle) {
          $this->pageTitle = $articleTitle . ' - ' . $siteName;
      } else {
          $this->pageTitle = obj('base/Base')->SEO('view_title') ?: ('文章详情 - ' . $siteName);
      }
      $this->pageKeywords = $articleTitle ? ($articleTitle . ',' . obj('base/Base')->SEO('view_keywords') ?: obj('base/Base')->SiteConfig('sitekeywords')) : obj('base/Base')->SEO('view_keywords');
      $rawDesc = isset($view['content']) ? strip_tags($view['content']) : ($articleTitle ?: '');
      $this->pageDescription = mb_substr($rawDesc, 0, 180, 'UTF-8') ?: (obj('base/Base')->SEO('view_dec') ?: obj('base/Base')->SiteConfig('sitedescription'));
      // 规范链接
      $this->canonicalUrl = url($route='index/index/view/id=<id>', $params=array('id'=>$id));
      // Open Graph 图片：取文章第一张图
      if (!empty($view['pic'])) {
          $this->ogImage = $view['pic'];
      }

        // 加载公共侧边栏（含热门文章、分类目录、站内速览）
        $this->loadCommonSidebar();

        $cid = isset($view['cid']) ? intval($view['cid']) : 0;
        $tWhere = array("`cid` = '{$cid}'");
        $mallTWhere = array("1");

        $this->tRet = $this->getRandomArticles($tWhere, 5);
        $this->mallTRet = $this->getRandomArticles($mallTWhere, 5);

        // 评论区（支持无限嵌套回复）
        $commentList = obj("api/ApiData")->thisQuery(
            "SELECT * FROM `{pre}comment` WHERE `mid` = ? AND `model` = '2' AND `hide` = 'n' ORDER BY `top` DESC, `id` ASC LIMIT 200",
            array($id)
        );
        $commentList = $commentList ? $commentList : array();
        // 构建嵌套树
        $commentTree = $this->buildCommentTree($commentList);
        $this->comments = $commentTree;
        // 递归渲染评论 HTML
        $this->commentHtml = $this->renderCommentTree($commentTree, 0);
        $commentWhere = array("`mid` = '{$id}' AND `model` = '2' AND `hide` = 'n'");
        $this->commentCount = obj("api/ApiData")->dataCount("yun_comment", $commentWhere);

        // 登录态（评论 / 点赞需要）
        $loginUser = null;
        if (!empty($_COOKIE['ZhiCmsUser'])) {
            $loginUser = obj("index/global", "controller")->findUser("y", $_COOKIE['ZhiCmsUser'], "cookie");
        }
        $this->loginUser = $loginUser;

        // 未登录访客信息（cookie 记住昵称邮箱）
        $this->visitorName = isset($_COOKIE['zhicms_comment_name']) ? $_COOKIE['zhicms_comment_name'] : '';
        $this->visitorMail = isset($_COOKIE['zhicms_comment_mail']) ? $_COOKIE['zhicms_comment_mail'] : '';

        // 文章点赞数 + 当前用户是否已赞
        $likeCount = isset($view['like']) ? (int)$view['like'] : 0;
        $this->likeCount = $likeCount;
        $this->hasLiked = false;
        if (!empty($loginUser)) {
            $likedRow = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'article' AND `uid` = ? LIMIT 1",
                array($id, (int)$loginUser['id'])
            );
            $this->hasLiked = !empty($likedRow);
        } elseif (!empty($_COOKIE['zhicms_visitor'])) {
            $likedRow = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'article' AND `cookie` = ? LIMIT 1",
                array($id, $_COOKIE['zhicms_visitor'])
            );
            $this->hasLiked = !empty($likedRow);
        }

        // 上下篇导航
        $prev = obj("api/ApiData")->thisQuery("SELECT `id`,`title` FROM `{pre}article` WHERE `id` < {$id} ORDER BY `id` DESC LIMIT 1");
        $next = obj("api/ApiData")->thisQuery("SELECT `id`,`title` FROM `{pre}article` WHERE `id` > {$id} ORDER BY `id` ASC LIMIT 1");
        $this->neighbor = array(
            'prev' => (!empty($prev[0])) ? $prev[0] : null,
            'next' => (!empty($next[0])) ? $next[0] : null,
        );
        $this->showBack = true;

       $this->display();
    }

    /**
     * 构建评论嵌套树（支持无限层级）
     * @param array $comments 扁平评论列表
     * @return array 嵌套树
     */
    private function buildCommentTree($comments) {
        if (empty($comments)) return array();
        $tree = array();
        $map = array();
        // 第一遍：建立 id => 节点 映射
        foreach ($comments as &$cm) {
            $cm['children'] = array();
            $displayName = !empty($cm['poster']) ? $cm['poster'] : '访客';
            $cm['displayName'] = $displayName;
            $cm['initial'] = mb_substr($displayName, 0, 1, 'UTF-8');
            $cm['like_count'] = isset($cm['like_count']) ? (int)$cm['like_count'] : 0;
            $map[(int)$cm['id']] = $cm;
        }
        unset($cm);
        // 第二遍：构建树
        foreach ($map as $cid => $node) {
            $pid = (int)$node['pid'];
            if ($pid > 0 && isset($map[$pid])) {
                $map[$pid]['children'][] = &$map[$cid];
            } else {
                $tree[] = &$map[$cid];
            }
        }
        return $tree;
    }

    /**
     * 递归渲染评论树为 HTML
     * @param array $tree 评论树
     * @param int $depth 当前深度
     * @return string HTML
     */
    private function renderCommentTree($tree, $depth = 0) {
        if (empty($tree)) return '';
        $html = '';
        $indent = $depth * 24;
        foreach ($tree as $node) {
            $cid = (int)$node['id'];
            $name = htmlspecialchars($node['displayName'], ENT_QUOTES, 'UTF-8');
            $initial = htmlspecialchars($node['initial'], ENT_QUOTES, 'UTF-8');
            $content = htmlspecialchars($node['content'], ENT_QUOTES, 'UTF-8');
            $date = htmlspecialchars($node['date'], ENT_QUOTES, 'UTF-8');
            $likes = (int)$node['like_count'];
            $topBadge = ($node['top'] === 'y') ? '<span class="c-top">置顶</span>' : '';
            $html .= '<li class="comment-item" id="comment-' . $cid . '" data-depth="' . $depth . '">';
            $html .= '<span class="c-avatar">' . $initial . '</span>';
            $html .= '<div class="c-body">';
            $html .= '<div class="c-head"><span class="c-name">' . $name . '</span>' . $topBadge . '<span class="c-time">' . $date . '</span></div>';
            $html .= '<div class="c-text">' . $content . '</div>';
            $html .= '<div class="c-actions">';
            $html .= '<a href="javascript:;" class="c-reply" data-cid="' . $cid . '" data-name="' . $name . '">回复</a>';
            $html .= '<a href="javascript:;" class="c-like" data-cid="' . $cid . '"><span class="like-icon">♡</span><span class="like-num">' . ($likes > 0 ? $likes : '') . '</span></a>';
            $html .= '</div>';
            // 递归子评论
            if (!empty($node['children'])) {
                $html .= '<ul class="comment-list comment-children" style="margin-left:' . $indent . 'px">';
                $html .= $this->renderCommentTree($node['children'], $depth + 1);
                $html .= '</ul>';
            }
            $html .= '</div></li>';
        }
        return $html;
    }

    private function getRandomArticles($where, $limit = 5) {
        $count = obj("api/ApiData")->dataCount("yun_article", $where);
        if ($count <= 0) {
            return array();
        }
        $offset = mt_rand(0, max(0, $count - $limit));
        return obj("api/ApiData")->table("yun_article", true)
            ->where($where)
            ->order("`id` DESC")
            ->limit("{$offset}, {$limit}")
            ->select();
    }
    
    
       public function findItems($id){
        error_reporting(0);
        if(!$id){
           exit;
        }
        foreach ($id as $value) {
        preg_match_all('/http[s]{0,1}:\/\/([\w.]+\/?)\S*/', $value, $itemsId);
        $itemsUrl= $itemsId['0']['0'];
        $itemsUrl=preg_replace('/\[\/ZhiCmsUrl]/','',$itemsUrl);
        $content=urldecode($itemsUrl);
        // 用本地 Tjk 接口替代已废弃的 App.Search.zfy 远程 API
        $card = $this->resolveLinkCard($content);
        if ($card !== null) return $card;
        // 解析失败时，渲染兜底「去购买」按钮
        return $this->buildFallbackBtn($content);
        }
    }

    /**
     * 使用 Tjk 本地接口解析 [ZhiCmsUrl] 短链接并生成 SMZDM 风格商品卡片
     * 淘宝走 DTK ParseContent + GetGoodsDetails；
     * 其他平台不做短链解析，统一走兜底卡片
     */
    private function resolveLinkCard($url) {
        $url = trim($url);
        if (empty($url)) return null;

        // 仅淘宝/天猫走 DTK 详情解析
        if (strpos($url, 'taobao.com') === false && strpos($url, 'tmall.com') === false) return null;

        $cacheKey = 'pc_card_v2_' . md5($url);
        return tcache($cacheKey, function() use ($url) {
            try {
                $api = \app\common\ConfigStore::load('api');
                if (empty($api['dtk_appkey']) || empty($api['dtk_appsecret'])) return null;

                $tjk = new \ZhiCms\ext\Tjk();
                $dtk = $tjk->getDtk();
                if (!$dtk) return null;

                // 解析短链接 → goodsId
                $parsed = $dtk->ParseContent($url);
                if ($parsed['code'] != 1 || empty($parsed['data']['goodsId'])) {
                    $twd = $dtk->TwdToTwd($url);
                    if ($twd['code'] == 1 && !empty($twd['data']['goodsId'])) {
                        $goodsId = $twd['data']['goodsId'];
                    } else {
                        return null;
                    }
                } else {
                    $goodsId = $parsed['data']['goodsId'];
                }

                // 获取商品详情
                $detail = $dtk->GetGoodsDetails($goodsId);
                if ($detail['code'] != 1 || empty($detail['data'])) return null;

                $item = $detail['data'];
                $buyUrl = url($route='index/redirect/jump/platform=<platform>/id=<id>', $params=array('platform'=>'tb', 'id'=>$goodsId));

                return $this->renderProductCard(array(
                    'title'      => $item['title'] ?? '',
                    'pic'        => $item['mainPic'] ?? '',
                    'origPrice'  => floatval($item['originalPrice'] ?? 0),
                    'actPrice'   => floatval($item['actualPrice'] ?? 0),
                    'coupon'     => floatval($item['couponPrice'] ?? 0),
                    'sales'      => intval($item['monthSales'] ?? 0),
                    'shopName'   => $item['shopName'] ?? '',
                    'platform'   => 'tb',
                    'goodsId'    => $goodsId,
                    'buyUrl'     => $buyUrl,
                ));
            } catch (\Exception $e) {
                return null;
            }
        }, 600);
    }

    /**
     * 兜底商品卡片：无法解析短链时，根据 URL 域名识别平台生成统一卡片
     */
    private function buildFallbackBtn($url) {
        $url = trim($url);
        if (empty($url)) return '';

        if (strpos($url, 'pinduoduo.com') !== false || strpos($url, 'yangkeduo.com') !== false) {
            $platform = 'pdd'; $name = '拼多多';
        } elseif (strpos($url, 'jd.com') !== false) {
            $platform = 'jd'; $name = '京东';
        } elseif (strpos($url, 'vip.com') !== false || strpos($url, 'vipshop.com') !== false) {
            $platform = 'vip'; $name = '唯品会';
        } elseif (strpos($url, 'taobao.com') !== false || strpos($url, 'tmall.com') !== false) {
            $platform = 'tb'; $name = '淘宝 / 天猫';
        } else {
            $platform = ''; $name = '';
        }

        if ($platform && $name) {
            // 尝试提取商品 ID（各类链接的常见 param）
            $goodsId = $this->extractGoodsId($url, $platform);
            $buyUrl = url($route='index/redirect/jump/platform=<platform>/id=<id>', $params=array('platform'=>$platform, 'id'=>$goodsId ?: urlencode($url)));
            $args = array(
                'title'      => $name . '好物推荐',
                'pic'        => '',
                'origPrice'  => 0,
                'actPrice'   => 0,
                'coupon'     => 0,
                'sales'      => 0,
                'shopName'   => '',
                'platform'   => $platform,
                'goodsId'    => $goodsId ?: '',
                'buyUrl'     => $buyUrl,
            );
        } else {
            // 完全不认识的链接，直接跳原地址
            $args = array(
                'title'   => '外部好物推荐',
                'pic'     => '',
                'origPrice' => 0, 'actPrice' => 0, 'coupon' => 0, 'sales' => 0,
                'shopName' => '', 'platform' => '', 'goodsId' => '',
                'buyUrl' => $url,
            );
        }
        return $this->renderProductCard($args);
    }

    /**
     * 从 URL 里尝试提取商品 ID
     */
    private function extractGoodsId($url, $platform) {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) return '';
        parse_str($query, $params);
        if ($platform === 'tb') {
            return $params['id'] ?? '';
        } elseif ($platform === 'jd') {
            return $params['sku'] ?? $params['wareId'] ?? '';
        } elseif ($platform === 'pdd') {
            return $params['goods_id'] ?? $params['goodsId'] ?? '';
        } elseif ($platform === 'vip') {
            return $params['goods_id'] ?? $params['productId'] ?? '';
        }
        return '';
    }

    /**
     * 渲染 SMZDM 风格商品卡片
     */
    private function renderProductCard($args) {
        $title    = htmlspecialchars($args['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $pic      = htmlspecialchars($args['pic'] ?? '', ENT_QUOTES, 'UTF-8');
        $origPrice = floatval($args['origPrice'] ?? 0);
        $actPrice  = floatval($args['actPrice'] ?? 0);
        $coupon    = floatval($args['coupon'] ?? 0);
        $sales     = intval($args['sales'] ?? 0);
        $shopName  = htmlspecialchars($args['shopName'] ?? '', ENT_QUOTES, 'UTF-8');
        $buyUrl    = htmlspecialchars($args['buyUrl'] ?? '', ENT_QUOTES, 'UTF-8');
        $platform  = $args['platform'] ?? '';
        $hasPrice  = ($actPrice > 0);

        // 平台标签
        $platLabel = '';
        $platClass = '';
        if ($platform === 'tb')  { $platLabel = '淘宝'; $platClass = 'pc-plat-tb'; }
        elseif ($platform === 'jd')  { $platLabel = '京东'; $platClass = 'pc-plat-jd'; }
        elseif ($platform === 'pdd') { $platLabel = '拼多多'; $platClass = 'pc-plat-pdd'; }
        elseif ($platform === 'vip') { $platLabel = '唯品会'; $platClass = 'pc-plat-vip'; }

        $html  = '<div class="product-card">';
        if ($pic) {
            $html .= '<a class="pc-cover" href="' . $buyUrl . '" target="_blank" rel="nofollow">';
            $html .= '<img src="' . $pic . '" alt="' . $title . '" loading="lazy" referrerpolicy="no-referrer">';
            if ($platLabel) $html .= '<span class="pc-plat ' . $platClass . '">' . $platLabel . '</span>';
            $html .= '</a>';
        }
        $html .= '<div class="pc-body">';
        $html .= '<a class="pc-title" href="' . $buyUrl . '" target="_blank" rel="nofollow">' . $title . '</a>';
        if ($hasPrice) {
            $html .= '<div class="pc-prices">';
            $html .= '<span class="pc-cur">¥<b>' . number_format($actPrice, 2) . '</b></span>';
            if ($origPrice > 0 && $origPrice > $actPrice) {
                $html .= '<span class="pc-old">¥' . number_format($origPrice, 2) . '</span>';
            }
            if ($coupon > 0) {
                $html .= '<span class="pc-coupon">券 ¥' . intval($coupon) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '<div class="pc-meta">';
        if ($shopName) $html .= '<span class="pc-shop">' . $shopName . '</span>';
        if ($sales > 0)  $html .= '<span class="pc-sales">月销 ' . ($sales >= 10000 ? round($sales/10000, 1).'万+' : $sales) . '</span>';
        $html .= '</div>';
        $html .= '<div class="pc-actions">';
        $html .= '<a class="pc-btn-buy" href="' . $buyUrl . '" target="_blank" rel="nofollow" style="color:#fff !important;">立即去看看</a>';
        $html .= '</div>';
        $html .= '</div></div>';

        return $html;
    }
    
    public function lists($cid, $lock = "n") {
        if ($lock == "y") {
            $name = obj("api/Api")->cid($cid);
            return $name . "," . $cid;
        }
    }

  }