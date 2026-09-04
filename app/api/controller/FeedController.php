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
            // 先转义引号（防 SQL 注入闭合），再转义 LIKE 通配符 % _ \（防全表扫描/ReDoS）
            $kw = addslashes($keyword);
            $kw = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $kw);
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

        // 5) 商品双列流（分页，前端好价推荐主区使用，保留时间流以兼容旧 UI）
        $feedPage = max(1, intval($this->raw('feed_page', $page)));
        $feedRows = obj('api/ApiData')->dataSelect('yun_items', array("`del` = 0"), '`top` DESC, `id` DESC');
        $pageSize = 10;
        $feedList = array_slice($feedRows ?: array(), ($feedPage - 1) * $pageSize, $pageSize);
        $feedItems = array_map(array($this, 'mapItem'), $feedList);

        // 6) KB 智能混合流：普通文章 + 文章商品(带货文) + 电商产品 三类混合，按好货率/ROI 排序（非时间）
        //    这是「AI 智能电商导购聚合搜索」在 App 端的程序印记体现，竞品无法靠抄代码复制。
        $built = $this->buildMixed($feedPage, $pageSize);

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
                'mixed_feed'  => $built['items'],   // 新增：KB 智能混合流（sort_by=smart）
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
     * 商品详情（读 yun_items 数据库，与优惠券列表同源）
     * GET index.php?r=api/feed/detail&goodsId=xxx  或  &id=xxx（yun_items.id）
     *
     * 返回 mapItem 统一字段 + 图集 images（主图 + 详情图）+ 预估返利 estimateAmount。
     * 这样「优惠券/数据库商品」与「小时榜/API商品」展示字段完全一致；
     * 转链（api/goods/open）仅在用户点击购买时按需调用，不阻塞详情渲染。
     */
    public function detail() {
        $this->options();
        $goodsId = trim($this->raw('goodsId', ''));
        $id      = intval($this->raw('id', 0));

        $w = array("`del` = 0");
        if ($goodsId !== '') {
            $gid = addslashes($goodsId);
            $w[] = "`goodsId` = '{$gid}'";
        } elseif ($id > 0) {
            $w[] = "`id` = {$id}";
        } else {
            $this->json(array('code' => 0, 'message' => '缺少商品标识'));
            return;
        }

        // —— 主逻辑：用 yun_items 记录的 goodsId 查询淘客实时详情，数据以实时为准 ——
        // 优惠券页 / 小时榜商品均用其 goodsId（或 goodsSign）查淘客详情接口，返回实时数据渲染。
        // yun_items 仅作兜底：实时查询失败时用库内静态字段，避免页面空白。
        $item  = null;
        $images = array();
        $detailPicsArr = array();

        if ($goodsId !== '' && class_exists('\\ZhiCms\\ext\\Tjk')) {
            try {
                $tjk = new \ZhiCms\ext\Tjk();
                $dtl = $tjk->getGoodsDetail($goodsId, 'dtk');   // 大淘客 get-goods-details 以 goodsId 查询
                if (($dtl['code'] ?? 0) == 1 && !empty($dtl['data'])) {
                    $d = $dtl['data'];
                    // 实时数据统一映射（字段与大淘客 standardizeItem 一致）
                    $item = array(
                        'id'            => null,
                        'goodsId'       => $goodsId,
                        'goodsSign'     => $d['goodsSign'] ?? $goodsId,
                        'title'         => $d['title'] ?? $d['dtitle'] ?? '',
                        'pic'           => $d['mainPic'] ?? '',
                        'price'         => floatval($d['actualPrice'] ?? 0),     // 券后价
                        'originalPrice' => floatval($d['originalPrice'] ?? 0),
                        'couponPrice'   => floatval($d['couponPrice'] ?? 0),
                        'discount'      => (isset($d['originalPrice']) && $d['originalPrice'] > 0 && isset($d['actualPrice']))
                                            ? round(floatval($d['actualPrice']) / floatval($d['originalPrice']) * 10, 1) : 0,
                        'shopType'      => intval($d['shopType'] ?? 0),
                        'shopLabel'     => (intval($d['shopType'] ?? 0) === 1) ? '天猫' : '淘宝',
                        'shopName'      => $d['shopName'] ?? '',
                        'cid'           => 0,
                        'catName'       => '',
                        'monthSales'    => intval($d['monthSales'] ?? 0),
                        'worthRate'     => 0,
                        'itemLink'      => $d['itemLink'] ?? '',
                        'couponLink'    => $d['couponLink'] ?? '',
                        'item_from'     => 'tb',
                        'isChoice'      => false,
                        // V2 新增字段：推广文案 + 淘宝详情切图
                        'desc'          => $d['desc'] ?? $d['content'] ?? '',   // 推广文案（V2 返回 desc）
                        'detailPics'    => array(),                              // 淘宝详情切图（下面填充）
                    );
                    // 图集：主图 + 详情图
                    if (!empty($d['mainPic'])) $images[] = $d['mainPic'];
                    if (!empty($d['images']) && is_array($d['images'])) {
                        foreach ($d['images'] as $u) { if (!empty($u)) $images[] = $u; }
                    }
                    if (!empty($d['detailPics'])) {
                        $dp = is_array($d['detailPics']) ? $d['detailPics'] : json_decode($d['detailPics'], true);
                        if (is_array($dp)) {
                            foreach ($dp as $u) { if (!empty($u)) { $images[] = $u; $detailPicsArr[] = $u; } }
                        } elseif (is_string($d['detailPics']) && strpos($d['detailPics'], ',') !== false) {
                            foreach (explode(',', $d['detailPics']) as $u) { $u = trim($u); if ($u) { $images[] = $u; $detailPicsArr[] = $u; } }
                        } elseif (!empty($d['detailPics'])) {
                            $images[] = $d['detailPics'];
                            $detailPicsArr[] = $d['detailPics'];
                        }
                    }
                    // 淘宝详情切图（detailPics）单独保留，供前端「商品详情」区块逐张拼接展示
                    $item['detailPics'] = isset($detailPicsArr) ? array_values(array_unique($detailPicsArr)) : array();
                    $item['images'] = array_values(array_unique($images));
                    // 预估返利（元）：优先实时 estimateAmount，否则 佣金比例 × 券后价
                    if (isset($d['estimateAmount']) && $d['estimateAmount'] !== '' && $d['estimateAmount'] > 0) {
                        $item['estimateAmount'] = floatval($d['estimateAmount']);
                    } else {
                        $rate = floatval($d['commissionRate'] ?? 0);
                        $item['estimateAmount'] = ($rate > 0 && $item['price'] > 0) ? round($item['price'] * $rate / 100, 2) : 0;
                    }
                    $item['commissionRate'] = floatval($d['commissionRate'] ?? 0);
                }
            } catch (\Throwable $e) {
                $item = null;   // 实时失败，下面回退查库
            }
        }

        // 实时查询无结果：回退读 yun_items（库内静态字段兜底）
        if (empty($item)) {
            $row = obj('api/ApiData')->dataSelect('yun_items', $w);
            if (empty($row)) {
                $this->json(array('code' => 0, 'message' => '商品不存在或已下架'));
                return;
            }
            $item = $this->mapItem($row);
            if (empty($item)) {
                $this->json(array('code' => 0, 'message' => '商品数据异常'));
                return;
            }
            $fbImages = array();
            if (!empty($row['mainPic'])) $fbImages[] = $row['mainPic'];
            if (!empty($row['detailPics'])) {
                $dp = json_decode($row['detailPics'], true);
                if (is_array($dp)) {
                    foreach ($dp as $u) { if (!empty($u)) $fbImages[] = $u; }
                } elseif (strpos($row['detailPics'], ',') !== false) {
                    foreach (explode(',', $row['detailPics']) as $u) { $u = trim($u); if ($u && !in_array($u, $fbImages)) $fbImages[] = $u; }
                } elseif (!in_array($row['detailPics'], $fbImages)) {
                    $fbImages[] = $row['detailPics'];
                }
            }
            $item['images'] = array_values(array_unique($fbImages));
            $rate = floatval($row['commissionRate'] ?? 0);
            $item['estimateAmount'] = ($rate > 0 && $item['price'] > 0) ? round($item['price'] * $rate / 100, 2) : floatval($row['estimateAmount'] ?? 0);
            $item['commissionRate'] = $rate;
        }

        $this->json(array(
            'code'    => 1,
            'message' => 'success',
            'item'    => $item,
        ));
    }

    /**
     * 热门搜索词（大淘客 get-top100），随机榜 + 随机抽取若干词
     * GET/POST: type(可选,1买家2淘客,默认随机); limit(可选,默认随机6~12)
     */
    public function topWords() {
        $type  = intval($this->raw('type', 0));
        $limit = intval($this->raw('limit', 0));
        if ($limit <= 0) $limit = rand(6, 12);   // 随机展示几个

        $words = array();
        if (class_exists('\\ZhiCms\\ext\\Tjk')) {
            try {
                $tjk = new \ZhiCms\ext\Tjk();
                $res = $tjk->getTopWords($type);
                if (($res['code'] ?? 0) == 1 && !empty($res['data'])) {
                    $words = $res['data'];
                }
            } catch (\Throwable $e) {
                // 忽略，回退默认词
            }
        }

        // 随机抽取 limit 个（不重复）
        if (count($words) > $limit) {
            shuffle($words);
            $words = array_slice($words, 0, $limit);
        }

        $this->json(array(
            'code'  => 1,
            'message' => 'success',
            'data'  => array_values($words),
        ));
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

    // ==================== KB 智能混合流（文章 + 文章商品 + 电商产品 混合排序） ====================
    //
    // 把「普通文章 / 文章商品(带货文, article.goodsId 非空) / 电商产品(yun_items)」三类内容混成一个信息流，
    // 排序依据是「好货率/ROI」——来自我们自有知识库 data/ai_kb（AI 导购每次请求累积的语义/行为资产）
    // 与 data/ai_feedback（用户点击/接受/下单反馈），而非按 id DESC 的时间顺序。
    // 这是「AI 智能电商导购聚合搜索」独有的程序印记：算法可抄，持续累积的“用户到底要什么”抄不走。

    /**
     * 聚合知识库 + 反馈，构建排序信号（带 10 分钟文件缓存，避免每次请求扫描 jsonl）。
     * 返回：hotWords[kw]={q,avgRoi,matchRate,brands[],features[]}，fbAccept/fbClick[goodsId]=count
     */
    private function getKbAggregate($ttl = 600) {
        $cacheFile = \ROOT_PATH . 'data/ai_kb/_aggregate.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
            $c = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($c)) return $c;
        }
        $kbDir = \ROOT_PATH . 'data/ai_kb/';
        $fbDir = \ROOT_PATH . 'data/ai_feedback/';
        $hot = array();
        if (is_dir($kbDir)) {
            foreach (glob($kbDir . '*.jsonl') as $f) {
                if (basename($f) === '_aggregate.json') continue;
                $h = @fopen($f, 'r'); if (!$h) continue;
                while (($line = fgets($h)) !== false) {
                    $line = trim($line); if ($line === '') continue;
                    $e = json_decode($line, true); if (!is_array($e)) continue;
                    $kw = trim(mb_strtolower($e['keyword'] ?? ''));
                    if ($kw === '') continue;
                    if (!isset($hot[$kw])) $hot[$kw] = array('q' => 0, 'roi' => 0, 'mi' => 0, 'ti' => 0, 'brands' => array(), 'features' => array());
                    $hot[$kw]['q']++;
                    $roi = isset($e['roi']['avg']) ? (int)$e['roi']['avg'] : 0;
                    $hot[$kw]['roi'] += $roi;
                    $hot[$kw]['mi']  += (int)($e['roi']['matchCount'] ?? 0);
                    $hot[$kw]['ti']  += (int)($e['roi']['total'] ?? 0);
                    $fl = $e['filters'] ?? array();
                    if ($roi >= 80) { // 仅从“高符合度”请求里学习好品牌/好特性
                        if (!empty($fl['brand']))    { $b = mb_strtolower($fl['brand']);    $hot[$kw]['brands'][$b]    = ($hot[$kw]['brands'][$b] ?? 0) + 1; }
                        if (!empty($fl['feature']))  { $b = mb_strtolower($fl['feature']);  $hot[$kw]['features'][$b]  = ($hot[$kw]['features'][$b] ?? 0) + 1; }
                    }
                }
                fclose($h);
            }
        }
        $fbAccept = array(); $fbClick = array();
        if (is_dir($fbDir)) {
            foreach (glob($fbDir . '*.log') as $f) {
                $h = @fopen($f, 'r'); if (!$h) continue;
                while (($line = fgets($h)) !== false) {
                    $line = trim($line); if ($line === '') continue;
                    $e = json_decode($line, true); if (!is_array($e)) continue;
                    $gid = (string)($e['goodsId'] ?? ''); if ($gid === '') continue;
                    $act = $e['action'] ?? '';
                    if ($act === 'accept' || $act === 'order') $fbAccept[$gid] = ($fbAccept[$gid] ?? 0) + 1;
                    elseif ($act === 'click') $fbClick[$gid] = ($fbClick[$gid] ?? 0) + 1;
                }
                fclose($h);
            }
        }
        $hotWords = array();
        foreach ($hot as $kw => $v) {
            arsort($v['brands']); arsort($v['features']);
            $hotWords[$kw] = array(
                'q'         => $v['q'],
                'avgRoi'    => $v['q'] ? round($v['roi'] / $v['q']) : 0,
                'matchRate' => $v['ti'] ? round($v['mi'] / $v['ti'], 2) : 0,
                'brands'    => array_keys(array_slice($v['brands'], 0, 5)),
                'features'  => array_keys(array_slice($v['features'], 0, 5)),
            );
        }
        // 按“查询量 × 符合度”排序，越被需要且越准的词权重越高
        uasort($hotWords, function ($a, $b) { return ($b['q'] * $b['avgRoi']) <=> ($a['q'] * $a['avgRoi']); });
        $agg = array('hotWords' => $hotWords, 'fbAccept' => $fbAccept, 'fbClick' => $fbClick, 'built' => time());
        @file_put_contents($cacheFile, json_encode($agg, JSON_UNESCAPED_UNICODE));
        return $agg;
    }

    /**
     * 单条智能打分（0~约120）。基础分=价值/互动信号；叠加 KB 热词命中、好品牌、用户反馈。
     */
    private function smartScore($row, $type, $kb) {
        $title = mb_strtolower(strip_tags(($row['title'] ?? '') . ' ' . ($row['dtitle'] ?? '') . ' ' . ($row['keywords'] ?? '')));
        $s = 0;
        if ($type === 'product' || $type === 'article_product') {
            $price = (float)($row['actualPrice'] ?? 0);
            $orig  = (float)($row['originalPrice'] ?? 0);
            $coupon= (float)($row['couponPrice'] ?? 0);
            $sales = (int)($row['monthSales'] ?? 0);
            if ($orig > 0 && $price > 0) $s += min(25, (1 - $price / $orig) * 100);   // 折扣力度
            if ($sales > 0) $s += min(20, log10($sales + 1) * 4);                     // 销量热度
            if ($coupon > 0 && $price > 0) $s += min(8, $coupon / $price * 10);       // 券力度
            if (($row['choice'] ?? 0) == 1) $s += 10;                                 // 编辑精选
            if (($row['top'] ?? 0) == 1)   $s += 5;                                   // 置顶
        }
        if ($type === 'article' || $type === 'article_product') {
            $s += min(30, ((int)($row['view'] ?? 0)) / 50);                           // 阅读
            $s += min(20, ((int)($row['like'] ?? 0)) * 2);                            // 点赞
            if (($row['featured'] ?? 0) == 1) $s += 8;                                // 推荐
            $d = $row['date'] ?? '';
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $d, $m)) {
                $days = (time() - strtotime($m[0])) / 86400;
                if ($days <= 7) $s += 5; elseif ($days <= 30) $s += 2;               // 轻微时效性
            }
        }
        // KB 热词命中：标题含高符合度热词 → 加分（至多叠加 3 个，避免堆爆）
        $i = 0;
        foreach (($kb['hotWords'] ?? array()) as $kw => $v) {
            if ($kw === '' || mb_stripos($title, $kw) === false) continue;
            $s += max(0, min(15, ($v['avgRoi'] - 60) / 3));
            if (++$i >= 3) break;
        }
        // 好品牌加成（从高分请求学习到的品牌）
        $brand = mb_strtolower($row['brandName'] ?? '');
        if ($brand !== '') {
            foreach (($kb['hotWords'] ?? array()) as $v) {
                if (in_array($brand, $v['brands'] ?? array(), true)) { $s += 8; break; }
            }
        }
        // 用户反馈加成（点击/接受/下单）
        $gid = (string)($row['goodsId'] ?? '');
        if ($gid !== '') {
            if (isset($kb['fbAccept'][$gid])) $s += 15 + min(20, $kb['fbAccept'][$gid] * 3);
            elseif (isset($kb['fbClick'][$gid])) $s += min(10, $kb['fbClick'][$gid] * 2);
        }
        return round($s, 1);
    }

    /**
     * 从 AI 接口（大淘客实时榜单，即 AI 导购聚合搜索的同源数据源）拉取一批产品，带文件缓存。
     * 缓存目的：方便二次展示 —— 混合流每次被 App 拉取时直接命中缓存，不重复请求外部 API；
     * 超过 TTL 才回源刷新。不是每次都有：API 失败/无结果时返回空数组（不混入）。
     *
     * @return array 标准化产品数组（字段与 yun_items 一致：goodsId/title/mainPic/actualPrice/...），
     *               每个带 _type='product'、from_api=true，可直接进入混合池打分排序
     */
    private function getAiProducts($limit = 8, $ttl = 1800) {
        $cacheFile = \ROOT_PATH . 'data/ai_kb/_ai_products.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
            $c = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($c) && isset($c['items']) && is_array($c['items'])) {
                return array_slice($c['items'], 0, $limit);
            }
        }
        if (!class_exists('\\ZhiCms\\ext\\Tjk')) {
            return array();
        }
        $items = array();
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            // 多榜单聚合，增加“AI 接口返回”的多样性（实时/全天热销/热推/综合热搜）
            $seen = array();
            foreach (array(1, 2, 3, 7) as $type) {
                $res = $tjk->getRankingList($type, '', 20, '1');
                if (empty($res) || ($res['code'] ?? 0) != 1 || empty($res['items'])) continue;
                foreach ($res['items'] as $it) {
                    $gid = (string)($it['goodsId'] ?? '');
                    if ($gid === '' || isset($seen[$gid])) continue;
                    $seen[$gid] = true;
                    $it['_type']    = 'product';
                    $it['from_api'] = true;
                    $it['id']       = 0;   // 非库内商品，以 goodsId 标识
                    $it['dec']      = '';  // mapMixed 读 dec，AI 项置空
                    $items[] = $it;
                    if (count($items) >= 60) break 2;
                }
            }
        } catch (\Throwable $e) {
            return array();   // 异常：本次无 AI 产品可混入
        }
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($cacheFile, json_encode(array('built' => time(), 'items' => $items), JSON_UNESCAPED_UNICODE));
        return array_slice($items, 0, $limit);
    }

    /**
     * 取混合内容池：AI 接口产品 + 电商产品 + 文章（普通/文章商品）。
     * 各取近期窗口再打分重排，控制内存。AI 产品来自缓存（方便二次展示），非每次都有。
     */
    private function fetchMixedPool($window = 250) {
        $pool = array();
        $prods = obj('api/ApiData')->dataSelect('yun_items', array("`del` = 0"), '`id` DESC');
        $dbGoodsIds = array();
        if (!empty($prods)) {
            foreach (array_slice($prods, 0, $window) as $p) {
                $p['_type'] = 'product';
                $pool[] = $p;
                if (!empty($p['goodsId'])) $dbGoodsIds[strtolower($p['goodsId'])] = true;
            }
        }
        // 混入 AI 接口返回的产品（带缓存，方便二次展示；非每次都有，失败则跳过）
        $ai = $this->getAiProducts(8, 1800);
        if (!empty($ai)) {
            foreach ($ai as $a) {
                $gid = strtolower((string)($a['goodsId'] ?? ''));
                if ($gid !== '' && isset($dbGoodsIds[$gid])) continue;   // 去重：避免与库内商品重复卡片
                $pool[] = $a;
            }
        }
        $arts = obj('api/ApiData')->dataSelect('yun_article', array("`status` = 1"), '`id` DESC');
        if (!empty($arts)) {
            foreach (array_slice($arts, 0, $window) as $a) {
                $a['_type'] = (!empty($a['goodsId']) && $a['goodsId'] !== '') ? 'article_product' : 'article';
                $pool[] = $a;
            }
        }
        return $pool;
    }

    /**
     * 构建 KB 智能混合流（核心：按好货率/ROI 排序，非时间）。home() 与 mixed() 共用。
     */
    private function buildMixed($page, $pageSize, $window = 250) {
        $kb = $this->getKbAggregate();
        $pool = $this->fetchMixedPool($window);
        $scored = array();
        foreach ($pool as $row) {
            $scored[] = array('row' => $row, 's' => $this->smartScore($row, $row['_type'], $kb));
        }
        usort($scored, function ($a, $b) { return $b['s'] <=> $a['s']; });
        $total = count($scored);
        $slice = array_slice($scored, ($page - 1) * $pageSize, $pageSize);
        $items = array();
        foreach ($slice as $x) { $items[] = $this->mapMixed($x['row'], $x['s']); }
        return array('items' => $items, 'total' => $total);
    }

    /**
     * 统一映射为混合流字段（type 标记内容类型，供 App 差异化渲染）。
     */
    private function mapMixed($row, $score) {
        $type = $row['_type'];
        $base = array(
            'type'     => $type,
            'id'       => intval($row['id']),
            'title'    => $row['title'] ?? '',
            'pic'      => $row['mainPic'] ?? '',
            'desc'     => $row['dec'] ?? '',
            'score'    => $score,
            'from_api' => !empty($row['from_api']),   // AI 接口混入产品标记，App 可显示“AI 推荐”角标
        );
        if ($type === 'product' || $type === 'article_product') {
            $base = array_merge($base, array(
                'goodsId'       => $row['goodsId'] ?? '',
                'goodsSign'     => $row['goodsSign'] ?? '',
                'price'         => floatval($row['actualPrice'] ?? 0),
                'originalPrice' => floatval($row['originalPrice'] ?? 0),
                'couponPrice'   => floatval($row['couponPrice'] ?? 0),
                'monthSales'    => intval($row['monthSales'] ?? 0),
                'shopName'      => $row['shopName'] ?? '',
                'brandName'     => $row['brandName'] ?? '',
                'catName'       => $this->cats[intval($row['cid'] ?? 0)] ?? '',
                'item_from'     => (($row['item_from'] === 'dtk' || $row['item_from'] === 'taobao') ? 'tb' : ($row['item_from'] ?? 'tb')),
                'isChoice'      => intval($row['choice'] ?? 0) === 1,
            ));
        }
        if ($type === 'article' || $type === 'article_product') {
            $base['view'] = intval($row['view'] ?? 0);
            $base['like'] = intval($row['like'] ?? 0);
            $base['navid'] = intval($row['navid'] ?? 0);
            $base['date'] = $row['date'] ?? '';
            $base['url']  = '';
            if ($type === 'article') {
                $base['catName'] = \app\base\controller\BaseController::getNavName(intval($row['navid'] ?? 0));
            }
        }
        return $base;
    }

    /**
     * 混合信息流接口（App 主信息流可用此替代单纯时间流）
     * GET index.php?r=api/feed/mixed&page=1
     */
    public function mixed() {
        $this->options();
        $page = max(1, intval($this->raw('page', 1)));
        $pageSize = 10;
        $built = $this->buildMixed($page, $pageSize);
        $this->json(array(
            'code'      => 1,
            'message'   => 'success',
            'total'     => $built['total'],
            'page'      => $page,
            'page_size' => $pageSize,
            'items'     => $built['items'],
            'sort_by'   => 'smart',   // 标记：按 KB/好货率 智能排序，非时间
        ));
    }

    /**
     * 热词好货率看板（AI 智能电商导购聚合搜索 · 程序印记可视化）
     * GET index.php?r=api/feed/kbStats  （直接浏览器打开即可看）
     */
    public function kbStats() {
        $kb = $this->getKbAggregate(60);
        header('Content-Type: text/html; charset=utf-8');
        $rows = '';
        $i = 0;
        foreach ($kb['hotWords'] as $kw => $v) {
            $i++;
            $rows .= '<tr><td>' . $i . '</td><td>' . htmlspecialchars($kw) . '</td><td>' . $v['q'] . '</td>'
                . '<td>' . $v['avgRoi'] . '%</td><td>' . round($v['matchRate'] * 100) . '%</td><td>'
                . htmlspecialchars(implode(' / ', $v['brands'])) . '</td></tr>';
            if ($i >= 50) break;
        }
        if ($rows === '') $rows = '<tr><td colspan="6">暂无数据，先通过 AI 导购产生一些请求即可累积</td></tr>';
        echo '<!doctype html><html lang="zh"><head><meta charset="utf-8">'
            . '<title>热词好货率看板</title>'
            . '<style>body{font-family:system-ui,-apple-system,"PingFang SC",sans-serif;padding:24px;color:#222}'
            . 'h1{font-size:20px}table{border-collapse:collapse;width:100%;margin-top:12px}'
            . 'th,td{border:1px solid #e3e3e3;padding:7px 10px;font-size:13px;text-align:left}'
            . 'th{background:#f6ffed;color:#389e0d}caption{font-size:16px;margin-bottom:8px;text-align:left}'
            . 'p{color:#666;font-size:13px}</style></head><body>'
            . '<h1>🔥 热词好货率看板</h1>'
            . '<p>数据来源：<code>data/ai_kb/</code>（AI 导购每次请求累积的自有语义/行为资产，竞品抄不走的程序印记）。'
            . '「平均符合度」= 小淘自评 ROI 评分均值，越高代表用户诉求被满足得越好。</p>'
            . '<table><caption>热词榜（按 查询量 × 符合度 排序）</caption>'
            . '<tr><th>#</th><th>热词</th><th>查询次数</th><th>平均符合度</th><th>精准命中率</th><th>代表好货品牌</th></tr>'
            . $rows . '</table>'
            . '<p style="margin-top:16px">反馈信号：被接受/下单商品 <b>' . count($kb['fbAccept']) . '</b> 个；被点击商品 <b>' . count($kb['fbClick']) . '</b> 个。</p>'
            . '</body></html>';
    }
}
