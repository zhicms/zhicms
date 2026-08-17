<?php
namespace app\api\controller;

/**
 * 首页好价爆料 Feed（仿什么值得买）
 *
 * 直接读取 ZhiCms 主程序爆料数据表 yun_items，与网站“优惠券商城/什么值得买精选页”同源，
 * 为小程序“首页”提供 SMZDM 风格的信息流。后续若要接入点赞/评论/收藏等互动数据，
 * 只需在此基础上关联 yun_like / yun_comment 等表即可。
 *
 * GET index.php?r=api/feed/index&cid=0&keyword=&page=1
 */
class FeedController extends ApiBaseController {

    // 分类映射（与 CheapsController::getAllCategories 保持一致）
    private $cats = array(
        1  => '女装', 2  => '母婴', 3  => '化妆品', 4  => '居家',
        5  => '鞋包配饰', 6  => '美食', 7  => '文体车品', 8  => '数码家电',
        9  => '男装', 10 => '内衣', 11 => '箱包', 12 => '配饰',
        13 => '户外运动', 14 => '家装家纺',
    );

    /**
     * 首页好价区统一列表入口（按 mode 分三路真实数据源）
     *  mode=article : 文章(yun_article)，按发现分类 navid 筛选（好价推荐）
     *  mode=rank    : 大淘客真实榜单 api/goods/rank?type=（小时榜：实时/全天/热推/综合）
     *  mode=coupon  : 优惠券(yun_items 带券)，按电商分类 cid 筛选（我的降价）
     *  默认(空)     : 商品流 yun_items（首页默认好价流）
     */
    public function index() {
        $this->options();
        $mode = trim($this->raw('mode', ''));
        switch ($mode) {
            case 'article':
                return $this->articlesList();
            case 'rank':
                return $this->rankList();
            case 'coupon':
                return $this->couponList();
            default:
                return $this->goodsList();
        }
    }

    /* ---------- 默认商品流（yun_items） ---------- */
    protected function goodsList() {
        $cid     = intval($this->raw('cid', 0));
        $page    = max(1, intval($this->raw('page', 1)));
        $keyword = trim($this->raw('keyword', ''));
        $sort    = trim($this->raw('sort', ''));
        $hours   = max(1, intval($this->raw('hours', 3)));

        $where = array();
        $where[] = "`del` = 0";
        if ($cid > 0) {
            $where[] = "`cid` = {$cid}";
        }
        if ($keyword !== '') {
            $kw = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $keyword);
            $where[] = "`title` LIKE '%{$kw}%'";
        }

        $orderBy = '`top` DESC, `id` DESC';
        $extraWhere = array();
        switch ($sort) {
            case 'sales3h':
                $extraWhere[] = "`dtime` >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)";
                $orderBy = '`monthSales` DESC, `id` DESC';
                break;
            case 'lowest':
                $orderBy = '`actualPrice` ASC, `id` DESC';
                break;
            case 'discount':
                $orderBy = '`originalPrice` DESC, `actualPrice` ASC, `id` DESC';
                break;
            case 'worth':
                $extraWhere[] = "`choice` = 1";
                $orderBy = '`monthSales` DESC, `id` DESC';
                break;
            case 'abs':
                $orderBy = '`actualPrice` ASC, `id` DESC';
                break;
            case 'latest':
            default:
                $orderBy = '`top` DESC, `id` DESC';
        }
        $where = array_merge($where, $extraWhere);

        $all = obj('api/ApiData')->dataSelect('yun_items', $where, $orderBy);
        if (empty($all)) {
            $all = array();
        }
        $pageSize = 10;
        $list = array_slice($all, ($page - 1) * $pageSize, $pageSize);
        $items = array_map(array($this, 'mapItem'), $list);

        $this->json(array(
            'code' => 1, 'message' => 'success',
            'total' => count($all), 'page' => $page, 'page_size' => $pageSize,
            'items' => $items, 'categories' => $this->catList(),
            'sort' => $sort, 'filters' => $this->filterList(),
        ));
    }

    /* ---------- 好价推荐：文章(yun_article) ---------- */
    protected function articlesList() {
        $navid = intval($this->raw('navid', 0));
        $page  = max(1, intval($this->raw('page', 1)));
        $pageSize = 10;

        $where = array("`status` = 1");
        if ($navid > 0) {
            $where[] = "`navid` = {$navid}";
        }
        $all = obj('api/ApiData')->dataSelect('yun_article', $where, '`id` DESC');
        if (empty($all)) {
            $all = array();
        }
        $list = array_slice($all, ($page - 1) * $pageSize, $pageSize);
        $items = array_map(array($this, 'mapArticle'), $list);

        $this->json(array(
            'code' => 1, 'message' => 'success',
            'total' => count($all), 'page' => $page, 'page_size' => $pageSize,
            'items' => $items,
            'nav_categories' => $this->navCategories(),   // 文章分类（发现分类）
        ));
    }

    /* ---------- 小时榜：大淘客真实榜单 ---------- */
    protected function rankList() {
        $type = intval($this->raw('type', 1));   // 1实时 2全天 3热推 7综合
        if (!in_array($type, array(1, 2, 3, 7), true)) {
            $type = 1;
        }
        $page  = max(1, intval($this->raw('page', 1)));
        $pageSize = 10;

        $rows = $this->getHourRank($type, 100);
        if (empty($rows)) {
            $rows = array();
        }
        // 分页（getHourRank 已返回前 100 条并带 rank）
        $list = array_slice($rows, ($page - 1) * $pageSize, $pageSize);
        // 重新编号（按当前页）
        $items = array();
        foreach ($list as $k => $it) {
            $it['rank'] = ($page - 1) * $pageSize + $k + 1;
            $items[] = $it;
        }

        $this->json(array(
            'code' => 1, 'message' => 'success',
            'total' => count($rows), 'page' => $page, 'page_size' => $pageSize,
            'items' => $items,
            'rank_types' => $this->rankTypes(),   // 榜单类型（实时/全天/热推/综合）
            'current_type' => $type,
        ));
    }

    /* ---------- 我的降价：优惠券(yun_items 带券) ---------- */
    protected function couponList() {
        $cid  = intval($this->raw('cid', 0));
        $page = max(1, intval($this->raw('page', 1)));
        $pageSize = 10;

        $where = array("`del` = 0", "`couponPrice` > 0");
        if ($cid > 0) {
            $where[] = "`cid` = {$cid}";
        }
        // 我的降价：按折扣力度（actualPrice/originalPrice）升序
        $all = obj('api/ApiData')->dataSelect('yun_items', $where, '`actualPrice` / IF(`originalPrice`>0,`originalPrice`,1) ASC, `monthSales` DESC');
        if (empty($all)) {
            $all = array();
        }
        $list = array_slice($all, ($page - 1) * $pageSize, $pageSize);
        $items = array_map(array($this, 'mapItem'), $list);

        $this->json(array(
            'code' => 1, 'message' => 'success',
            'total' => count($all), 'page' => $page, 'page_size' => $pageSize,
            'items' => $items,
            'categories' => $this->catList(),   // 优惠券商品分类
        ));
    }

    /**
     * 首页聚合接口（淘客风格）
     * GET index.php?r=api/feed/home&page=1
     * 一次性返回：轮播 banner、金刚区、营销入口、实时热销榜、商品双列流。
     * 数据全部取自 yun_items 淘客商品表 + 站点配置，无需额外表。
     */
    public function home() {
        $this->options();

        $page = max(1, intval($this->raw('page', 1)));

        // 1) 轮播 banner：取精选（choice=1）商品前 5 张主图
        $bannerRows = obj('api/ApiData')->dataSelect('yun_items', array("`del` = 0", "`choice` = 1"), '`id` DESC');
        $banners = array();
        if (!empty($bannerRows)) {
            foreach (array_slice($bannerRows, 0, 5) as $b) {
                if (empty($b['mainPic'])) continue;
                $banners[] = array(
                    'image' => $b['mainPic'],
                    'title' => ($b['dtitle'] ?: $b['title']),
                    'goodsId' => $b['goodsId'],
                    'goodsSign' => $b['goodsSign'],
                    'itemLink' => $b['itemLink'],
                    'couponLink' => $b['couponLink'],
                    'item_from' => (($b['item_from'] === 'dtk' || $b['item_from'] === 'taobao') ? 'tb' : $b['item_from']),
                );
            }
        }
        // 无精选商品时降级：取最新商品前 3 张
        if (empty($banners)) {
            $rows = obj('api/ApiData')->dataSelect('yun_items', array("`del` = 0"), '`id` DESC');
            foreach (array_slice($rows ?: array(), 0, 3) as $b) {
                if (empty($b['mainPic'])) continue;
                $banners[] = array(
                    'image' => $b['mainPic'],
                    'title' => ($b['dtitle'] ?: $b['title']),
                    'goodsId' => $b['goodsId'],
                    'goodsSign' => $b['goodsSign'],
                    'itemLink' => $b['itemLink'],
                    'couponLink' => $b['couponLink'],
                    'item_from' => (($b['item_from'] === 'dtk' || $b['item_from'] === 'taobao') ? 'tb' : $b['item_from']),
                );
            }
        }

        // 2) 金刚区：复用网站真实分类（CheapsController::getAllCategories），作为分类导航入口
        $kingkong = $this->catList();

        // 3) 实时小时热榜：直接对接大淘客真实榜单接口（api/goods/rank?type=1），
        //    取代原来用 yun_items 月销模拟的「限时秒杀」，保证数据真实、实时
        $hourRank = $this->getHourRank(1, 10);

        // 4) 风云榜/编辑精选：取站内 choice=1 + 高月销 商品前 6（真实精选数据）
        $fyRows = obj('api/ApiData')->dataSelect('yun_items', array("`del` = 0", "`choice` = 1"), '`monthSales` DESC, `id` DESC');
        $fengyun = array();
        if (!empty($fyRows)) {
            foreach (array_slice($fyRows, 0, 6) as $k => $r) {
                $fengyun[] = array_merge(
                    $this->mapItem($r),
                    array('rank' => $k + 1)
                );
            }
        }

        // 5) 资讯/文章流（yun_article 已发布）：取最新 5 篇（首页次级入口，不作主推）
        $artRows = obj('api/ApiData')->dataSelect('yun_article', array("`status` = 1"), '`id` DESC');
        $articles = array();
        if (!empty($artRows)) {
            foreach (array_slice($artRows, 0, 5) as $a) {
                $articles[] = array(
                    'id'      => intval($a['id']),
                    'title'   => $a['title'] ?: '',
                    'pic'     => $a['mainPic'] ?: '',
                    'desc'    => $a['dec'] ?: '',
                    'view'    => intval($a['view']),
                    'date'    => $a['date'] ?: '',
                    'catName' => $this->cats[intval($a['cid'])] ?? '',
                );
            }
        }

        // 5) 商品双列流（分页，前端好价推荐主区使用）
        $feedPage = max(1, intval($this->raw('feed_page', $page)));
        $feedRows = obj('api/ApiData')->dataSelect('yun_items', array("`del` = 0"), '`top` DESC, `id` DESC');
        $pageSize = 10;
        $feedList = array_slice($feedRows ?: array(), ($feedPage - 1) * $pageSize, $pageSize);
        $feedItems = array_map(array($this, 'mapItem'), $feedList);

        $this->json(array(
            'code'    => 1,
            'message' => 'success',
            'data'    => array(
                'banners'     => $banners,
                'kingkong'    => $kingkong,
                'hour_rank'   => $hourRank,   // 真实小时热榜（对接大淘客榜单）
                'fengyun'     => $fengyun,    // 站内编辑精选榜
                'articles'    => $articles,   // 资讯（次级入口）
                'feed'        => $feedItems,
                'categories'  => $this->catList(),
                'nav_categories' => $this->navCategories(),  // 文章分类（好价推荐用）
                'rank_types'  => $this->rankTypes(),          // 榜单类型（小时榜用）
                'page'        => $feedPage,
                'page_size'   => $pageSize,
                'has_more'    => count($feedList) >= $pageSize,
            ),
        ));
    }

    /**
     * 文章详情（本地 yun_article，好价推荐点进去的原生详情页数据源）
     * GET index.php?r=api/feed/view&id=218
     */
    public function view() {
        $this->options();
        $id = intval($this->raw('id', 0));
        if ($id <= 0) {
            $this->json(array('code' => 0, 'message' => '缺少文章 ID'));
            return;
        }
        $row = obj('api/ApiData')->dataSelect('yun_article', array("`id` = {$id}"), '`id` DESC');
        if (empty($row) || empty($row[0])) {
            $this->json(array('code' => 0, 'message' => '文章不存在'));
            return;
        }
        $a = $row[0];
        // 阅读量 +1（非关键，异常忽略）
        try {
            obj('api/ApiData')->dataUpdate('yun_article', array('view' => intval($a['view']) + 1), array("`id` = {$id}"));
        } catch (\Throwable $e) {
            unset($e);
        }
        $this->json(array(
            'code'    => 1,
            'message' => 'success',
            'data'    => $this->mapArticle($a, true),
        ));
    }

    /**
     * 对接大淘客真实榜单接口（api/goods/rank），type: 1=实时热销 2=今日爆款 3=昨日榜单 7=小时榜
     */
    protected function getHourRank($type = 1, $limit = 10) {
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $res = $tjk->getRankingList($type, '', $limit, '1');
            if (empty($res) || ($res['code'] ?? 0) != 1 || empty($res['items'])) {
                return array();
            }
            // 复用 GoodsController 的 mapProduct 逻辑（public static）
            $list = array();
            foreach (array_slice($res['items'], 0, $limit) as $k => $it) {
                $mapped = \app\api\controller\GoodsController::mapProduct($it);
                $mapped['rank'] = $k + 1;
                $list[] = $mapped;
            }
            return $list;
        } catch (\Throwable $e) {
            return array();
        }
    }

    /**
     * 映射为小程序 SMZDM 风格字段
     */
    protected function mapItem($it) {
        if (empty($it) || !is_array($it)) {
            return array();
        }
        $title     = $it['dtitle'] ?: $it['title'];
        $price     = floatval($it['actualPrice']);
        $orig      = floatval($it['originalPrice']);
        $discount  = $orig > 0 ? round($price / $orig * 10, 1) : 0;
        $shopType  = intval($it['shopType']);
        $monthSales= intval($it['monthSales']);

        // 值率：根据折扣力度与月销推导一个稳定的展示指标（非真实用户投票）。
        // 后续接入 yun_like 等真实点赞数据后，可替换为真实「值 / 不值」比例。
        $worthRate = 0;
        if ($discount > 0) {
            $worthRate = min(99, max(60, intval(60 + (10 - $discount) * 4 + ($monthSales % 6))));
        }

        return array(
            'id'           => $it['id'],
            'goodsId'      => $it['goodsId'],
            'goodsSign'    => $it['goodsSign'],   // 大淘客商品标识，转链（领取优惠券）必传
            'title'        => $title,
            'pic'          => $it['mainPic'],
            'price'        => $price,
            'originalPrice'=> $orig,
            'couponPrice'  => floatval($it['couponPrice']),
            'discount'     => $discount,        // 几折，0 表示无折扣信息
            'shopType'     => $shopType,
            'shopLabel'    => $shopType === 1 ? '天猫' : '淘宝',
            'shopName'     => $it['shopName'],
            'cid'          => intval($it['cid']),
            'catName'      => $this->cats[intval($it['cid'])] ?? '',
            'monthSales'   => $monthSales,
            'worthRate'    => $worthRate,       // 值率（%）
            'itemLink'     => $it['itemLink'],
            'couponLink'   => $it['couponLink'], // 领券/推广链接，转链失败时的兜底
            'item_from'    => (($it['item_from'] === 'dtk' || $it['item_from'] === 'taobao') ? 'tb' : $it['item_from']),
            'isChoice'     => intval($it['choice']) === 1,
        );
    }

    /**
     * 分类列表（精选 + 各分类），供首页顶部导航使用
     * 统一复用网站前台 CheapsController 的分类来源，避免两处写死不同步
     */
    protected function catList() {
        $list = array(array('id' => 0, 'name' => '精选'));
        try {
            $cheaps = obj('index/Cheaps');
            if (method_exists($cheaps, 'getAllCategories')) {
                $cats = $cheaps->getAllCategories();
                if (!empty($cats)) {
                    foreach ($cats as $c) {
                        $list[] = array('id' => intval($c['id']), 'name' => $c['name']);
                    }
                    return $list;
                }
            }
        } catch (\Throwable $e) {
            // 异常兜底：用本控制器内置映射
            unset($e);
        }
        foreach ($this->cats as $id => $name) {
            $list[] = array('id' => $id, 'name' => $name);
        }
        return $list;
    }

    /**
     * 仿 SMZDM 二级筛选 chip
     */
    protected function filterList() {
        return array(
            array('key' => 'sales3h', 'name' => '3小时最热'),
            array('key' => 'abs',      'name' => '绝对值'),
            array('key' => 'lowest',   'name' => '历史低价'),
            array('key' => 'worth',    'name' => '看精选'),
        );
    }

    /**
     * 文章(yun_article) 映射为小程序字段，catName 来自发现分类 yun_nav
     */
    protected function mapArticle($a, $withContent = false) {
        if (empty($a) || !is_array($a)) {
            return array();
        }
        $navid = intval($a['navid']);
        return array(
            'id'        => intval($a['id']),
            'title'     => $a['title'] ?: '',
            'desc'      => $a['dec'] ?: '',
            'pic'       => $a['mainPic'] ?: '',
            'content'   => $withContent ? ($a['content'] ?: '') : '',
            'view'      => intval($a['view']),
            'date'      => $a['date'] ?: '',
            'navid'     => $navid,
            'catName'   => \app\base\controller\BaseController::getNavName($navid),
            'url'       => '',
        );
    }

    /**
     * 文章分类（发现分类 yun_nav）
     */
    protected function navCategories() {
        $map = \app\base\controller\BaseController::getNavCategories();
        $list = array(array('id' => 0, 'name' => '推荐'));
        foreach ($map as $id => $name) {
            $list[] = array('id' => $id, 'name' => $name);
        }
        return $list;
    }

    /**
     * 榜单类型（与网站 rank.html 一致：实时/全天热销/热推/综合热搜）
     */
    protected function rankTypes() {
        return array(
            array('type' => 1, 'name' => '实时榜'),
            array('type' => 2, 'name' => '全天热销榜'),
            array('type' => 3, 'name' => '热推榜'),
            array('type' => 7, 'name' => '综合热搜榜'),
        );
    }
}
