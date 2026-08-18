<?php

namespace app\common;

/**
 * 网页版右侧边栏模块化管理
 *
 * 参考 emlog 桌面版 side.php 的 widgets 数据驱动模式：
 *   - 侧栏由一组「模块(widget)」按 sort 排序组成；
 *   - 每个模块有独立 type（文章/商品/品牌/热榜/评论/分类/搜索/统计/用户）；
 *   - 后台可设置：是否显示(enabled)、排序(sort)、展示条数(limit)、
 *     图文方向(img_pos：left/right/top/bottom)、是否显示序号(show_no)、
 *     展示样式(style：list/card/grid)、标题(title)。
 *
 * 配置存储：复用 ConfigStore（{pre}config 表，group=cfg_sidebar）。
 * 未配置时回落到 defaultConfig()，保证「视觉零变化」。
 */
class SidebarService {

    /** 可选模块类型 */
    const TYPES = array(
        'user'     => '用户卡片',
        'stats'    => '站内速览',
        'search'   => '搜索',
        'cheaps'   => '近期好券',
        'cats'     => '商品分类',
        'navs'     => '文章分类',
        'articles' => '热门文章',
        'brands'   => '品牌推荐',
        'rank'     => '热榜商品',
        'comments' => '最新评论',
    );

    /**
     * 默认模块配置（与旧版 sidebar.html 写死的 6 区块顺序/内容一致，保证零变化）
     */
    public static function defaultConfig() {
        return array(
            array('type' => 'user',     'title' => '用户中心', 'enabled' => 1, 'sort' => 10, 'limit' => 5,  'img_pos' => 'left',  'show_no' => 0, 'style' => 'card'),
            array('type' => 'stats',    'title' => '站内速览', 'enabled' => 1, 'sort' => 20, 'limit' => 5,  'img_pos' => 'left',  'show_no' => 0, 'style' => 'card'),
            array('type' => 'search',   'title' => '搜索',     'enabled' => 1, 'sort' => 30, 'limit' => 5,  'img_pos' => 'left',  'show_no' => 0, 'style' => 'card'),
            array('type' => 'cheaps',   'title' => '近期好券', 'enabled' => 1, 'sort' => 40, 'limit' => 10, 'img_pos' => 'left',  'show_no' => 0, 'style' => 'list'),
            array('type' => 'cats',     'title' => '商品分类', 'enabled' => 1, 'sort' => 50, 'limit' => 20, 'img_pos' => 'left',  'show_no' => 0, 'style' => 'list'),
            array('type' => 'articles', 'title' => '热门文章', 'enabled' => 1, 'sort' => 60, 'limit' => 10, 'img_pos' => 'left',  'show_no' => 1, 'style' => 'list'),
        );
    }

    /**
     * 读取侧栏配置（已按 sort 升序排列，过滤非法 type）
     */
    public static function loadConfig() {
        $raw = ConfigStore::load('cfg_sidebar', 'widgets');
        if (empty($raw) || !is_array($raw)) {
            return self::defaultConfig();
        }
        $valid = array();
        foreach ($raw as $w) {
            if (!isset($w['type']) || !isset(self::TYPES[$w['type']])) {
                continue;
            }
            $w['enabled'] = isset($w['enabled']) ? (int) $w['enabled'] : 1;
            $w['sort']    = isset($w['sort']) ? (int) $w['sort'] : 99;
            $w['limit']   = isset($w['limit']) ? (int) $w['limit'] : 5;
            $w['limit']   = max(1, min($w['limit'], 50));
            $w['img_pos'] = in_array($w['img_pos'] ?? '', array('left', 'right', 'top', 'bottom')) ? $w['img_pos'] : 'left';
            $w['show_no'] = isset($w['show_no']) ? (int) $w['show_no'] : 0;
            $w['style']   = in_array($w['style'] ?? '', array('list', 'card', 'grid')) ? $w['style'] : 'list';
            $w['title']   = isset($w['title']) && $w['title'] !== '' ? $w['title'] : (self::TYPES[$w['type']] ?? $w['type']);
            $valid[] = $w;
        }
        usort($valid, function ($a, $b) { return $a['sort'] <=> $b['sort']; });
        return $valid;
    }

    /**
     * 保存侧栏配置
     */
    public static function saveConfig(array $widgets) {
        return ConfigStore::save('cfg_sidebar', array('widgets' => $widgets));
    }

    /**
     * 计算单个模块所需的数据（由各 controller 的 loadCommonSidebar 调用）
     * 返回渲染所需的标准化数据数组。
     * @param string $type  模块类型
     * @param int    $limit 条数
     * @return array
     */
    public static function buildWidgetData($type, $limit) {
        $limit = max(1, (int) $limit);
        switch ($type) {
            case 'cheaps':
                return self::dataCheaps($limit);
            case 'brands':
                return self::dataBrands($limit);
            case 'rank':
                return self::dataRank($limit);
            case 'articles':
                return self::dataArticles($limit);
            case 'comments':
                return self::dataComments($limit);
            case 'cats':
                return \app\base\controller\BaseController::getCategories();
            case 'navs':
                return self::dataNavs();
            default:
                return array();
        }
    }

    /* ---------------------- 各模块数据来源 ---------------------- */

    /**
     * 取得大淘客 API 实例（Dtk）。
     * 侧栏品牌/热榜/好券本应走 API 而非直查数据库（yun_brand / yun_items），
     * 旧逻辑直查会导致表缺失/字段不符时整页 SQL 报错。
     * 这里按需 new 一个 Tjk（无参时自动读取本地 api 配置），失败返回 null。
     */
    private static function getDtk() {
        static $dtk = null;
        static $tried = false;
        if ($tried) {
            return $dtk;
        }
        $tried = true;
        try {
            if (class_exists('\\ZhiCms\\ext\\Tjk')) {
                $tjk = new \ZhiCms\ext\Tjk();
                $dtk = method_exists($tjk, 'getDtk') ? $tjk->getDtk() : null;
            }
        } catch (\Throwable $e) {
            $dtk = null;
        }
        return $dtk;
    }

    public static function dataCheaps($limit) {
        $cache = CacheService::instance();
        return $cache->remember('sidebar_cheaps', function () use ($limit) {
            $dtk = self::getDtk();
            // 优先走大淘客「朋友圈好券」接口
            if ($dtk && method_exists($dtk, 'FriendsCircleList')) {
                $resp = $dtk->FriendsCircleList('', (int) $limit, 0, 0);
                if (!empty($resp['code']) && !empty($resp['items'])) {
                    $items = $resp['items'];
                    // 补平台标记后走统一商品结构
                    foreach ($items as &$it) {
                        if (empty($it['item_from'])) {
                            $it['item_from'] = 'taobao';
                        }
                    }
                    unset($it);
                    return self::normalizeGoods($items);
                }
            }
            // 回落：选品库随机好券（仅在 API 未配置/失败时）
            $rows = obj("api/ApiData")->thisQuery(
                "SELECT * FROM `yun_items` WHERE `del` = 0 AND `id` >= (SELECT MAX(`id`) - 300 FROM `yun_items`) ORDER BY RAND() LIMIT " . (int) $limit
            );
            if (empty($rows)) {
                $rows = obj("api/ApiData")->thisQuery("SELECT * FROM `yun_items` WHERE `del` = 0 ORDER BY RAND() LIMIT " . (int) $limit);
            }
            return self::normalizeGoods($rows ?: []);
        }, 300);
    }

    private static function dataBrands($limit) {
        $limit = max(1, (int) $limit);
        $cache = CacheService::instance();
        return $cache->remember('sidebar_brands', function () use ($limit) {
            $dtk = self::getDtk();
            $out = array();
            // 走大淘客「品牌栏目榜」接口（delanys/brand/get-column-list）
            // 注意：该接口返回数量可能不受 pageSize 控制，故统一在下方随机抽取 $limit 条。
            if ($dtk && method_exists($dtk, 'GetBrandColumnList')) {
                $resp = $dtk->GetBrandColumnList(50, '1', '');
                if (!empty($resp['code']) && !empty($resp['brands'])) {
                    foreach ($resp['brands'] as $b) {
                        $brandId = $b['brandId'] ?? 0;
                        $out[] = array(
                            'id'    => $brandId,
                            'title' => $b['brandName'] ?? '',
                            'pic'   => $b['brandLogo'] ?? '',
                            'url'   => url('index/brand/view', array('id' => $brandId)),
                        );
                    }
                }
            }
            // 回落：品牌表（仅在 API 未配置/失败/无数据时）
            if (empty($out)) {
                $rows = obj("api/ApiData")->thisQuery(
                    "SELECT * FROM `yun_brand` WHERE `state` = 1 ORDER BY `px` ASC, `id` DESC"
                );
                if (!empty($rows)) {
                    foreach ($rows as $r) {
                        $out[] = array(
                            'id'    => $r['id'] ?? 0,
                            'title' => $r['name'] ?? '',
                            'pic'   => $r['logo'] ?? ($r['pic'] ?? ''),
                            'url'   => url('index/brand/view', array('id' => $r['id'] ?? 0)),
                        );
                    }
                }
            }
            // 随机打乱后只取 $limit 条（保证「后台控制的数量」严格生效）
            if (count($out) > $limit) {
                shuffle($out);
                $out = array_slice($out, 0, $limit);
            }
            return $out;
        }, 600);
    }

    private static function dataRank($limit) {
        $cache = CacheService::instance();
        return $cache->remember('sidebar_rank', function () use ($limit) {
            $dtk = self::getDtk();
            // 走大淘客「各大榜单」接口（goods/get-ranking-list，rankType=2 全天热销榜）
            if ($dtk && method_exists($dtk, 'GetRankingList')) {
                $resp = $dtk->GetRankingList(2, '', (int) $limit, '1');
                if (!empty($resp['code']) && !empty($resp['items'])) {
                    // GetRankingList 返回的 items 已是 standardized 商品结构，直接套用通用渲染
                    return self::normalizeGoods($resp['items']);
                }
            }
            // 回落：选品库销量榜（仅在 API 未配置/失败时）
            $rows = obj("api/ApiData")->thisQuery(
                "SELECT * FROM `yun_items` WHERE `del` = 0 ORDER BY `sales` DESC, `id` DESC LIMIT " . (int) $limit
            );
            return self::normalizeGoods($rows ?: []);
        }, 300);
    }

    private static function dataArticles($limit) {
        $cache = CacheService::instance();
        return $cache->remember('sidebar_articles', function () use ($limit) {
            $rows = obj("api/ApiData")->thisQuery(
                "SELECT * FROM `yun_article` ORDER BY `view` DESC LIMIT 0, " . (int) $limit
            );
            $out = array();
            if (!empty($rows)) {
                foreach ($rows as $i => $a) {
                    $out[] = array(
                        'rank'   => $i + 1,
                        'id'     => $a['id'] ?? 0,
                        'title'  => $a['title'] ?? '',
                        'pic'    => !empty($a['litpic']) ? $a['litpic'] : (zc_get_first_image($a['content'] ?? '') ?? ''),
                        'url'    => url('index/index/view', array('id' => $a['id'] ?? 0)),
                        'time'   => isset($a['date']) ? $a['date'] : '',
                    );
                }
            }
            return $out;
        }, 600);
    }

    private static function dataComments($limit) {
        $cache = CacheService::instance();
        return $cache->remember('sidebar_comments', function () use ($limit) {
            $sql = "SELECT c.*, a.`title` AS art_title, a.`id` AS art_id "
                 . "FROM `{pre}comment` c LEFT JOIN `{pre}article` a ON c.`mid` = a.`id` "
                 . "WHERE c.`hide` = 'n' AND c.`model` = '2' ORDER BY c.`id` DESC LIMIT " . (int) $limit;
            $rows = obj("api/ApiData")->thisQuery($sql);
            $out = array();
            if (!empty($rows)) {
                foreach ($rows as $c) {
                    $out[] = array(
                        'id'      => $c['id'] ?? 0,
                        'author'  => $c['poster'] ?? '匿名',
                        'avatar'  => zc_get_gravatar($c['mail'] ?? ($c['poster'] ?? '')),
                        'content' => zc_extract_html_data($c['comment'] ?? '', 60),
                        'time'    => isset($c['date']) ? $c['date'] : '',
                        'art_title' => $c['art_title'] ?? '',
                        'url'     => url('index/index/view', array('id' => $c['art_id'] ?? 0)),
                    );
                }
            }
            return $out;
        }, 300);
    }

    private static function dataNavs() {
        $map = \app\base\controller\BaseController::getNavCategories();
        $out = array();
        foreach ($map as $id => $name) {
            $out[$id] = $name;
        }
        // 兜底：文章分类(yun_nav)为空时，回退到商品一级分类(yun_group)，
        // 避免右侧导航模块只显示标题而无任何链接（表现为"导航没加载"）。
        if (empty($out)) {
            $groups = obj("api/ApiData")->dataSelect("yun_group", array("1"), "`px` ASC, `id` ASC");
            if (!empty($groups)) {
                foreach ($groups as $g) {
                    $out[(int)($g['id'] ?? 0)] = $g['name'] ?? '';
                }
            }
        }
        return $out;
    }

    /**
     * 将商品原始行标准化为侧栏通用结构（含平台/图片/价格/链接）
     */
    public static function normalizeGoods($rows) {
        $out = array();
        if (empty($rows)) return $out;
        foreach ($rows as $i => $c) {
            $id      = !empty($c['id']) ? $c['id'] : 0;
            $title   = !empty($c['title']) ? $c['title'] : (!empty($c['goods_name']) ? $c['goods_name'] : '优惠商品');
            $from    = !empty($c['item_from']) ? $c['item_from'] : (!empty($c['laiyuan']) ? $c['laiyuan'] : 'tb');
            $platform = is_numeric($from) ? zc_laiyuan_to_platform($from) : $from;
            // 归一化平台标识为短码（tb/jd/pdd/vip），避免 taobao/tmall 等别名导致详情页链接不一致
            $platformMap = array('taobao' => 'tb', 'tmall' => 'tb', 'tb' => 'tb', 'jd' => 'jd', 'pdd' => 'pdd', 'vip' => 'vip');
            $platform = isset($platformMap[$platform]) ? $platformMap[$platform] : 'tb';
            $pic     = !empty($c['mainPic']) ? $c['mainPic'] : (!empty($c['main_pic']) ? $c['main_pic'] : (!empty($c['image']) ? $c['image'] : ''));
            $actual  = !empty($c['actualPrice']) ? $c['actualPrice'] : '';
            $original= !empty($c['originalPrice']) ? $c['originalPrice'] : '';
            $out[] = array(
                'rank'     => $i + 1,
                'id'       => $id,
                'goods_id' => !empty($c['goodsId']) ? $c['goodsId'] : (!empty($c['goods_id']) ? $c['goods_id'] : $id),
                'title'    => $title,
                'platform' => $platform,
                'pic'      => $pic,
                'actual'   => $actual,
                'original' => $original,
                'url'      => url('index/cheaps/detail', array('id' => (!empty($c['goodsId']) ? $c['goodsId'] : (!empty($c['goods_id']) ? $c['goods_id'] : $id)), 'type' => $platform)),
                'coupon'   => url('index/redirect/jump', array('platform' => $platform, 'id' => (!empty($c['goodsId']) ? $c['goodsId'] : (!empty($c['goods_id']) ? $c['goods_id'] : $id)))),
            );
        }
        return $out;
    }
}
