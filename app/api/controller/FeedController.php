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
}
