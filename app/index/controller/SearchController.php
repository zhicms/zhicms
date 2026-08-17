<?php
namespace app\index\controller;
class SearchController extends \app\base\controller\BaseController
{
    public $platforms = [
        'local'  => ['name' => '本地', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><rect x="3" y="3" width="18" height="18" rx="4" fill="#546e7a"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold">本</text></svg>'],
        'taobao' => ['name' => '淘宝', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><circle cx="12" cy="12" r="10" fill="#ff5000"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="10" font-weight="bold">淘</text></svg>'],
        'jd'     => ['name' => '京东', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" fill="#e4393c"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="10" font-weight="bold">京</text></svg>'],
        'pdd'    => ['name' => '拼多多', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><circle cx="12" cy="12" r="10" fill="#e02e24"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold">拼</text></svg>'],
        'vip'    => ['name' => '唯品会', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><rect x="3" y="3" width="18" height="18" rx="4" fill="#e60012"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold">V</text></svg>'],
    ];

    /**
     * 主流商品分类（对应 yun_items.cid，与 BaseController::$categoryMap 保持一致）
     * key = cid，用于「分类 → 品牌联动」以及本地商品分类筛选
     */
    public static $mainCats = [
        13 => '户外运动',
        8  => '数码家电',
        3  => '化妆品',
        1  => '女装',
        9  => '男装',
        5  => '鞋包配饰',
        2  => '母婴',
        6  => '美食',
        4  => '居家',
        14 => '家装家纺',
        7  => '文体车品',
        10 => '内衣',
        11 => '箱包',
        12 => '配饰',
    ];

    /**
     * 品牌库：分类(cid) => [品牌key => 品牌显示名/搜索词]
     * 品牌 key 会作为关键词拼入搜索（API 与本地库通用）
     * cid = 0 表示「全部分类」时展示的热门品牌
     */
    public static $brandLib = [
        0 => [
            'huawei'     => '华为', 'xiaomi' => '小米', 'apple' => 'Apple/苹果',
            'nike'       => 'NIKE/耐克', 'adidas' => 'adidas/阿迪', 'lining' => 'LI-NING/李宁',
            'anta'       => 'ANTA/安踏', 'uniqlo' => 'UNIQLO/优衣库', 'midea' => '美的',
            'haier'      => '海尔', 'gree' => '格力', 'loreal' => '欧莱雅',
        ],
        13 => [
            'nike' => 'NIKE/耐克', 'adidas' => 'adidas/阿迪', 'lining' => 'LI-NING/李宁',
            'anta' => 'ANTA/安踏', 'xtep' => 'XTEP/特步', '361' => '361°',
            'asics' => 'ASICS/亚瑟士', 'newbalance' => 'New Balance', 'puma' => 'PUMA/彪马',
            'skechers' => 'Skechers/斯凯奇', 'decathlon' => '迪卡侬', 'camel' => 'CAMEL/骆驼',
            'columbia' => 'Columbia/哥伦比亚', 'thenorthface' => 'The North Face',
        ],
        8 => [
            'huawei' => '华为', 'xiaomi' => '小米', 'apple' => 'Apple/苹果',
            'honor' => '荣耀', 'oppo' => 'OPPO', 'vivo' => 'vivo',
            'samsung' => '三星', 'lenovo' => '联想', 'midea' => '美的',
            'haier' => '海尔', 'gree' => '格力', 'hisense' => '海信',
            'tcl' => 'TCL', 'philips' => '飞利浦', 'sony' => '索尼', 'dyson' => '戴森',
        ],
        3 => [
            'loreal' => '欧莱雅', 'lancome' => '兰蔻', 'estee' => '雅诗兰黛',
            'sk2' => 'SK-II', 'olay' => 'OLAY/玉兰油', 'perfectdiary' => '完美日记',
            'winona' => '薇诺娜', 'proya' => '珀莱雅', 'chando' => '自然堂',
            'shiseido' => '资生堂', 'kans' => '韩束', 'hfp' => 'HomeFacialPro',
        ],
        1 => [
            'uniqlo' => 'UNIQLO/优衣库', 'zara' => 'ZARA', 'hm' => 'H&M',
            'onlyfashion' => 'ONLY', 'vero' => 'VERO MODA', 'urbanrevivo' => 'UR',
            'peacebird' => '太平鸟', 'semir' => '森马', 'metersbonwe' => '美特斯邦威',
            'lagogo' => 'lagogo', 'teenie' => 'Teenie Weenie',
        ],
        9 => [
            'uniqlo' => 'UNIQLO/优衣库', 'hla' => 'HLA/海澜之家', 'semir' => '森马',
            'jackjones' => 'JackJones', 'gxg' => 'GXG', 'markfairwhale' => '马克华菲',
            'septwolves' => '七匹狼', 'youngor' => '雅戈尔', 'peacebird' => '太平鸟男装',
        ],
        5 => [
            'nike' => 'NIKE/耐克', 'adidas' => 'adidas/阿迪', 'skechers' => 'Skechers/斯凯奇',
            'belle' => 'BELLE/百丽', 'teenmix' => 'Teenmix/天美意', 'redragonfly' => '红蜻蜓',
            'coach' => 'COACH/蔻驰', 'michaelkors' => 'MICHAEL KORS', 'crocs' => 'crocs/卡骆驰',
        ],
        2 => [
            'beingmate' => '贝因美', 'aptamil' => 'Aptamil/爱他美', 'wyeth' => '惠氏',
            'abbott' => '雅培', 'feihe' => '飞鹤', 'pampers' => '帮宝适',
            'huggies' => '好奇', 'babycare' => 'babycare', 'goodbaby' => 'gb好孩子',
            'yili' => '伊利', 'junlebao' => '君乐宝',
        ],
        6 => [
            'sanzhisongshu' => '三只松鼠', 'baicaowei' => '百草味', 'liangpinpuzi' => '良品铺子',
            'yili' => '伊利', 'mengniu' => '蒙牛', 'wanglaoji' => '王老吉',
            'nongfu' => '农夫山泉', 'haoxiangni' => '好想你', 'weilong' => '卫龙', 'qiaqia' => '洽洽',
        ],
        4 => [
            'midea' => '美的', 'supor' => '苏泊尔', 'joyoung' => '九阳',
            'bear' => '小熊电器', 'lock' => '乐扣乐扣', 'haers' => '哈尔斯',
            'yunhong' => '云鸿', 'kingclean' => '小狗电器', 'deerma' => '德尔玛',
        ],
        14 => [
            'luolai' => '罗莱', 'mercury' => '水星家纺', 'fuanna' => '富安娜',
            'boyang' => '博洋家纺', 'mendale' => '梦洁', 'ikea' => 'IKEA/宜家',
            'linshimuye' => '林氏木业', 'quanyou' => '全友家居',
        ],
        7 => [
            'deli' => '得力', 'mg' => '晨光', 'chenguang' => 'M&G晨光',
            'michelin' => '米其林', 'bosch' => '博世', '3m' => '3M',
            'decathlon' => '迪卡侬', 'lego' => 'LEGO/乐高',
        ],
        10 => [
            'aimer' => '爱慕', 'ubras' => 'Ubras', 'neiwai' => 'NEIWAI内外',
            'cosmo' => '都市丽人', 'triumph' => '黛安芬', 'uniqlo' => 'UNIQLO/优衣库',
        ],
        11 => [
            'samsonite' => '新秀丽', 'americantourister' => '美旅', 'coach' => 'COACH/蔻驰',
            'kipling' => 'Kipling', 'jansport' => 'JanSport', 'swissgear' => '瑞士军刀',
            'ninetygo' => '90分',
        ],
        12 => [
            'swarovski' => '施华洛世奇', 'chowtaiseng' => '周大生', 'chowtaifook' => '周大福',
            'laofengxiang' => '老凤祥', 'casio' => 'CASIO/卡西欧', 'seiko' => 'SEIKO/精工',
            'rayban' => 'Ray-Ban/雷朋',
        ],
    ];

    /**
     * 品牌别名 -> 标准搜索词（中文）。
     * 用于从用户搜索词中识别隐含品牌（如 "iphone 15" 隐含苹果、"airpods" 隐含苹果），
     * 即便该品牌未开店、由代理在标题写品牌名，也能并入搜索召回。
     * key 统一小写，匹配时不区分大小写。
     */
    public static $brandAliases = [
        'iphone' => '苹果', 'ipad' => '苹果', 'macbook' => '苹果', 'airpods' => '苹果',
        'mac' => '苹果', 'imac' => '苹果',
        'airpods pro' => '苹果', 'apple watch' => '苹果',
        'huaweimate' => '华为', 'huaweip50' => '华为', 'mate' => '华为', 'p60' => '华为',
        'xiaomimi' => '小米', 'redmi' => '小米', 'mi' => '小米',
        'honor' => '荣耀', 'magic' => '荣耀',
        'galaxy' => '三星', 'samsung' => '三星',
        'thinkpad' => '联想', 'yoga' => '联想',
        'nike' => '耐克', 'aj' => '耐克', 'air jordan' => '耐克', 'jordan' => '耐克',
        'adidas' => '阿迪', 'yeezy' => '阿迪',
        'lining' => '李宁', 'anta' => '安踏', 'li-ning' => '李宁',
        'new balance' => 'New Balance', 'nb' => 'New Balance', 'asics' => '亚瑟士',
        'puma' => '彪马', 'skechers' => '斯凯奇',
        'loreal' => '欧莱雅', 'lancome' => '兰蔻', 'estee lauder' => '雅诗兰黛',
        'la mer' => '海蓝之谜', 'sk-ii' => 'SK-II',
        'uniqlo' => '优衣库', 'zara' => 'ZARA', 'h&m' => 'H&M',
        'midea' => '美的', 'haier' => '海尔', 'gree' => '格力', 'hisense' => '海信',
        'dyson' => '戴森', 'philips' => '飞利浦', 'bosch' => '博世',
        'switch' => '任天堂', 'ps5' => '索尼', 'playstation' => '索尼', 'xbox' => '微软',
        'tesla' => '特斯拉', 'bmw' => '宝马', 'mercedes' => '奔驰', 'audi' => '奥迪',
        'rayban' => '雷朋', 'swarovski' => '施华洛世奇',
    ];

    /**
     * 取指定分类下的品牌列表（分类为 0 或未配置时返回热门品牌）
     */
    public static function getBrandsByCat($cid) {
        $cid = intval($cid);
        if ($cid > 0 && isset(self::$brandLib[$cid])) {
            return self::$brandLib[$cid];
        }
        return self::$brandLib[0];
    }

    /**
     * 品牌 key -> 搜索用关键词（去掉 "NIKE/耐克" 的英文前缀，用中文更利于命中）
     */
    public static function brandKeyword($brandKey, $cid = 0) {
        $brandKey = trim($brandKey);
        if ($brandKey === '') return '';
        $lib = self::getBrandsByCat($cid);
        // 找不到就在全库里找
        $name = $lib[$brandKey] ?? null;
        if ($name === null) {
            foreach (self::$brandLib as $group) {
                if (isset($group[$brandKey])) { $name = $group[$brandKey]; break; }
            }
        }
        if ($name === null) return $brandKey;
        // "NIKE/耐克" => 取斜杠后的中文；"华为" => 原样
        if (strpos($name, '/') !== false) {
            $parts = explode('/', $name);
            return trim(end($parts));
        }
        return $name;
    }

    public function index(){
        $content = trim(urldecode($this->arg("content", '')));
        $platform = strtolower($this->arg("platform", 'local'));
        $type = strtolower($this->arg("type", 'all'));
        $pageNum = max(1, intval($this->arg("page", 1)));

        if (!in_array($type, array('all', 'goods', 'article'))) {
            $type = 'all';
        }
        
        if (!array_key_exists($platform, $this->platforms) && !in_array($platform, ['local', 'compare', 'article'])) {
            $platform = 'local';
        }

        if ($platform === 'taobao' && preg_match('/(taobao\.com|tmall\.com)/i', $content)) {
            $goodsId = $this->resolveTaobaoGoodsId($content);
            if ($goodsId) {
                $url = url($route='index/view/detail/id=<id>', $params=array('id' => $goodsId, 'type' => 'taobao'));
                $this->title = '';
                $this->toUrl = $url;
                $this->display('app/index/view/search/to');
                exit();
            }
        }

        $this->key = $content;
        $this->keyword = $content;
        $this->q = urlencode($content);
        $this->platform = $platform;
        $this->type = $type;
        $this->platforms = $this->platforms;

        // 排序与筛选参数（视图用）
        $sort = strtolower($this->arg("sort", 'score'));
        if (!in_array($sort, array('score', 'time'))) { $sort = 'score'; }
        $this->sort = $sort;

        // 分类（nav）：只用于筛选「本地文章」，不参与商品筛选
        $nav = intval($this->arg("nav", 0));
        $this->currentNav = $nav;

        // 主流商品分类（cat）：用于品牌联动 + 本地商品分类筛选
        $cat = intval($this->arg("cat", 0));
        if ($cat > 0 && !isset(self::$mainCats[$cat])) { $cat = 0; }
        $this->cat = $cat;
        $this->mainCats = self::$mainCats;
        $this->brandList = self::getBrandsByCat($cat);
        $this->brandLibJson = json_encode(self::$brandLib, JSON_UNESCAPED_UNICODE);

        // 筛选参数（品牌/评分/价格）
        $brand = trim($this->arg("brand", ''));
        $style = intval($this->arg("style", 0));
        $pmin  = $this->arg("pmin", '');
        $pmax  = $this->arg("pmax", '');
        $rate  = floatval($this->arg("rate", 0));   // 店铺评分下限，如 4.5
        $this->brand = $brand;
        $this->style = $style;
        $this->pmin  = $pmin;
        $this->pmax  = $pmax;
        $this->rate  = $rate > 0 ? $rate : '';

        // SEO：搜索页
        if (!empty($content)) {
            $this->pageTitle = '"' . $content . '" 的搜索结果 - ' . obj('base/Base')->SiteConfig('sitename');
            $this->pageDescription = '搜索"' . $content . '"的相关商品和优惠信息';
            // 搜索结果页为低质动态页，避免被收录稀释权重
            $this->setNoindex();
        } else {
            $this->setPageSEO(
                obj('base/Base')->SEO('search_title') ?: '搜索',
                obj('base/Base')->SEO('search_keywords'),
                obj('base/Base')->SEO('search_dec')
            );
        }
        $this->pageKeywords = !empty($content) ? $content . ',搜索,' . obj('base/Base')->SEO('search_keywords') : obj('base/Base')->SEO('search_keywords');

        $this->loadCommonSidebar();

        if (empty($content)) {
            $this->page = array('list' => array(), 'count' => 0, 'pages' => '');
            $this->Page = $this->page;
            $this->emptyTip = '请输入搜索关键词';
            $this->display('app/index/view/search/index');
            exit();
        }

        // 筛选条件集合（本地平台在数据库层过滤，第三方平台走 API）
        $filters = array(
            'brand' => $brand,
            'cat'   => $cat,
            'pmin'  => $pmin,
            'pmax'  => $pmax,
            'rate'  => $rate,
            'nav'   => $nav,
            'style' => $style,
        );

        if ($platform === 'compare') {
            $this->searchCompare($content, $pageNum);
        } else {
            // local / article：全部走本地数据库（本地平台同样支持品牌/分类/价格/评分筛选）
            // 其它平台（淘宝/京东/拼多多/唯品会）：走 API
            $isLocal = ($platform === 'local' || $platform === 'article');
            $this->searchByKeyword($content, $platform, $type, $pageNum, $sort, $filters, !$isLocal);
        }
    }

    private function searchByKeyword($keyword, $platform = 'local', $type = 'all', $pageNum = 1, $sort = 'score', $filters = array(), $useApi = false){
        $pageSize = 20;

        $brand = $filters['brand'] ?? '';
        $cat   = intval($filters['cat'] ?? 0);
        $pmin  = $filters['pmin'] ?? '';
        $pmax  = $filters['pmax'] ?? '';
        $rate  = floatval($filters['rate'] ?? 0);
        $nav   = intval($filters['nav'] ?? 0);
        $style = intval($filters['style'] ?? 0);

        // 第三方平台：走 API 搜索（品牌/价格入参 API）
        if ($useApi) {
            $apiResult = $this->searchApi($keyword, $platform, $pageNum, $pageSize, $sort, $brand, $cat, $pmin, $pmax);
            $this->outputApiResult($apiResult, $keyword, $platform, $pageSize, $sort, $filters);
            exit();
        }

        if ($platform === 'local' || $platform === 'article') {
            $goodsList = array();
            $goodsCount = 0;
            $articleList = array();
            $articleCount = 0;

            // 分类(nav) 只作用于本地文章：一旦选择了分类，就只搜文章，不再展示商品
            $navOnlyArticle = ($nav > 0);

            if (!$navOnlyArticle && $platform !== 'article' && ($type === 'all' || $type === 'goods')) {
                $goodsResult = $this->searchLocal($keyword, $platform, $pageNum, $pageSize, $filters);
                $goodsList = $goodsResult['list'] ?? array();
                $goodsCount = $goodsResult['count'] ?? 0;
                foreach ($goodsList as &$g) { $g['docType'] = 'goods'; }
                unset($g);
            }

            if ($navOnlyArticle || $platform === 'article' || $type === 'article' || $type === 'all') {
                $articleResult = $this->searchArticles($keyword, $pageNum, $pageSize, $nav);
                $articleList = $articleResult['list'] ?? array();
                $articleCount = $articleResult['count'] ?? 0;
                foreach ($articleList as &$a) { $a['docType'] = 'article'; }
                unset($a);
            }

            $list = array_merge($goodsList, $articleList);
            $count = $goodsCount + $articleCount;

            // 混合排序：score = 相关度（标题命中 + 时间衰减 + 有券）；time = 按时间倒序
            $now = time();
            foreach ($list as &$it) {
                $title = $it['title'] ?? '';
                $hit = stripos($title, $keyword) !== false ? 100 : 0;
                if ($it['docType'] === 'article') {
                    $ts = strtotime($it['date'] ?? '') ?: $now;
                    $it['_time'] = $ts;
                    $it['_score'] = $hit + max(0, 60 - intval(($now - $ts) / 86400));
                } else {
                    $ts = strtotime($it['update_time'] ?? ($it['create_time'] ?? '')) ?: $now;
                    $it['_time'] = $ts;
                    $coupon = (!empty($it['couponPrice']) && $it['couponPrice'] > 0) ? 20 : 0;
                    $it['_score'] = $hit + $coupon + max(0, 60 - intval(($now - $ts) / 86400));
                }
            }
            unset($it);

            if ($sort === 'time') {
                usort($list, function($a, $b){ return ($b['_time'] ?? 0) - ($a['_time'] ?? 0); });
            } else {
                usort($list, function($a, $b){
                    $sa = $a['_score'] ?? 0; $sb = $b['_score'] ?? 0;
                    if ($sa == $sb) { return ($b['_time'] ?? 0) - ($a['_time'] ?? 0); }
                    return $sb - $sa;
                });
            }

            if (!empty($list)) {
                if ($navOnlyArticle || $platform === 'article') {
                    $pagesHtml = $articleResult['pages'] ?? '';
                } else {
                    $pagesHtml = $goodsResult['pages'] ?? ($articleResult['pages'] ?? '');
                }
                $array = array('list' => $list, 'count' => $count, 'pages' => $pagesHtml);
                $this->page = $array;
                $this->Page = $array;
                $this->dataSource = 'local';
                $this->display('app/index/view/search/index');
                exit();
            }

            $this->page = array('list' => array(), 'count' => 0, 'pages' => '');
            $this->Page = $this->page;
            $this->dataSource = 'none';
            if ($navOnlyArticle) {
                $this->emptyTip = '该分类下没有找到相关资讯';
            } elseif ($type === 'article') {
                $this->emptyTip = '没有找到相关资讯';
            } else {
                $this->emptyTip = '本地库中没有找到相关商品或资讯';
            }
            $this->display('app/index/view/search/index');
            exit();
        }

        $apiResult = $this->searchApi($keyword, $platform, $pageNum, $pageSize, $sort, $brand, $cat, $pmin, $pmax);
        $this->outputApiResult($apiResult, $keyword, $platform, $pageSize, $sort, $filters);
        exit();
    }

    private function searchCompare($keyword, $pageNum = 1){
        $pageSize = 20;

        $localGroups = $this->searchLocalCompare($keyword);
        
        $apiGroups = array();
        $client = $this->createTjkClient();
        if ($client) {
            $all = array();

            $tbResp = $client->searchGoods($keyword, 'taobao', 1, $pageSize);
            if ($tbResp['code'] == 1 && !empty($tbResp['items'])) {
                $all = array_merge($all, $tbResp['items']);
            }

            if ($client->getHdk()) {
                $jdResp = $client->searchGoods($keyword, 'jd', 1, $pageSize);
                if ($jdResp['code'] == 1 && !empty($jdResp['items'])) {
                    $all = array_merge($all, $jdResp['items']);
                }
                $pddResp = $client->searchGoods($keyword, 'pdd', 1, $pageSize);
                if ($pddResp['code'] == 1 && !empty($pddResp['items'])) {
                    $all = array_merge($all, $pddResp['items']);
                }
            }

            if (!empty($all)) {
                $apiGroups = $this->aggregateSameItems($all);
            }
        }

        $allGroups = array_merge($localGroups, $apiGroups);
        $seen = array();
        $dedup = array();
        foreach ($allGroups as $g) {
            $key = $g['repTitle'] ?? '';
            if (!empty($key) && !isset($seen[$key])) {
                $seen[$key] = true;
                $dedup[] = $g;
            }
        }

        usort($dedup, function($a, $b) {
            $pa = floatval($a['minPrice'] ?? PHP_FLOAT_MAX);
            $pb = floatval($b['minPrice'] ?? PHP_FLOAT_MAX);
            if (abs($pa - $pb) > 0.001) {
                return $pa <=> $pb;
            }
            return ($b['platformCount'] ?? 0) <=> ($a['platformCount'] ?? 0);
        });

        $total = count($dedup);
        $offset = ($pageNum - 1) * $pageSize;
        $pagedGroups = array_slice($dedup, $offset, $pageSize);

        foreach ($pagedGroups as &$g) {
            foreach ($g['items'] as &$it) {
                $this->buildBuyUrl($it);
            }
            unset($it);
        }
        unset($g);

        $root = search_url() . "?content=" . urlencode($keyword) . "&platform=compare";
        $listUrl = $root . "&page={page}";

        $pages = new \ZhiCms\ext\PageIndex;
        $array = array('list' => $pagedGroups, 'count' => $total, 'pages' => $pages->show($listUrl, $total, $pageSize));
        $this->page = $array;
        $this->Page = $array;
        $this->dataSource = 'compare';
        $this->display('app/index/view/search/index');
    }

    /**
     * 本地商品搜索（走数据库），支持品牌 / 分类 / 价格 / 店铺评分 筛选
     */
    private function searchLocal($keyword, $platform, $pageNum, $pageSize, $filters = array()){
        $brandKey = $filters['brand'] ?? '';
        $cat      = intval($filters['cat'] ?? 0);
        $pmin     = $filters['pmin'] ?? '';
        $pmax     = $filters['pmax'] ?? '';
        $rate     = floatval($filters['rate'] ?? 0);

        $where = array();
        $where[] = "`del` = 0";
        $where[] = "`title` LIKE '%" . addslashes($keyword) . "%'";

        if ($platform !== 'local') {
            $laiyuan = $this->platformToLaiyuan($platform);
            $where[] = "`laiyuan` = {$laiyuan}";
        }

        // 品牌：匹配 brandName 或标题包含品牌词
        if ($brandKey !== '') {
            $bw = addslashes(self::brandKeyword($brandKey, $cat));
            if ($bw !== '') {
                $where[] = "(`brandName` LIKE '%{$bw}%' OR `title` LIKE '%{$bw}%')";
            }
        } else {
            // 关键词本身含品牌名时（如 "iphone 15" 隐含苹果），自动识别并追加标题匹配，
            // 覆盖未开店、由代理销售但标题含品牌名的商品
            $detected = self::matchBrandAlias($keyword, $cat);
            if ($detected !== '') {
                $dw = addslashes($detected);
                $where[] = "`title` LIKE '%{$dw}%'";
            }
        }

        // 主流分类
        if ($cat > 0) {
            $where[] = "`cid` = {$cat}";
        }

        // 价格区间（actualPrice 到手价）
        if ($pmin !== '' && is_numeric($pmin)) {
            $where[] = "`actualPrice` >= " . floatval($pmin);
        }
        if ($pmax !== '' && is_numeric($pmax)) {
            $where[] = "`actualPrice` <= " . floatval($pmax);
        }

        // 店铺评分下限
        if ($rate > 0) {
            $where[] = "`dsrScore` >= " . floatval($rate);
        }

        $baseUrl = $this->buildFilterUrl($keyword, $platform, $filters);

        $page = obj('api/ApiData')->page($pageSize, "yun_items", $where, "`id` DESC", $baseUrl);
        
        if (!empty($page['list'])) {
            foreach ($page['list'] as &$it) {
                $this->buildBuyUrl($it);
                $it['url'] = $it['buyUrl'] ?? '';
                $it['scoreTxt'] = $this->getShopScore($it);
                if (empty($it['price_sign'])) { $it['price_sign'] = '¥'; }
                if (empty($it['actualPrice']) && !empty($it['price'])) { $it['actualPrice'] = $it['price']; }
            }
            unset($it);
        }

        return $page;
    }

    /**
     * 本地资讯搜索，支持按分类(navid)筛选
     */
    private function searchArticles($keyword, $pageNum, $pageSize, $nav = 0){
        $where = array();
        $where[] = "`status` = 1";
        $where[] = "`title` LIKE '%" . addslashes($keyword) . "%'";

        $nav = intval($nav);
        if ($nav > 0) {
            $where[] = "`navid` = {$nav}";
        }

        $baseUrl = search_url() . "?content=" . urlencode($keyword) . "&platform=local&type=article";
        if ($nav > 0) { $baseUrl .= "&nav=" . $nav; }

        $page = obj('api/ApiData')->page($pageSize, "yun_article", $where, "`id` DESC", $baseUrl);

        if (!empty($page['list'])) {
            foreach ($page['list'] as &$it) {
                $id = intval($it['id'] ?? 0);
                $it['docType'] = 'article';
                // 与首页文章列表参数保持一致（index/index.html 使用 mainPic/title/dec/id）
                $it['articleId'] = $id;
                $it['title'] = $it['title'] ?? '';
                $it['mainPic'] = $it['mainPic'] ?? ($it['pic'] ?? '');
                $it['dec'] = $it['dec'] ?? mb_substr(strip_tags($it['content'] ?? ''), 0, 100, 'UTF-8');
                $it['url'] = url('index/index/view/id=<id>', array('id' => $id));
                $it['articleUrl'] = $it['url'];
                // 文章无商品属性，置空避免模板误用商品字段
                $it['actualPrice'] = '';
                $it['originalprice'] = '';
                $it['couponPrice'] = '';
                $it['brandName'] = '';
                $it['shopName'] = '';
                $it['scoreTxt'] = '';
                $it['price_sign'] = '';
                $it['coupon_link'] = '';
                $it['buyUrl'] = '';
                $it['hits'] = $it['hits'] ?? 0;
                $it['comments'] = $it['comments'] ?? 0;
                $it['likes'] = $it['likes'] ?? 0;
                $it['time'] = $it['date'] ?? '';
                $it['mall_name'] = '资讯';
            }
            unset($it);
        }

        return $page;
    }

    private function searchLocalCompare($keyword){
        $where = array();
        $where[] = "`del` = 0";
        $where[] = "`title` LIKE '%" . addslashes($keyword) . "%'";

        $items = obj('api/ApiData')->dataSelect("yun_items", $where, "`id` DESC LIMIT 0, 30");
        
        if (empty($items)) {
            return array();
        }

        foreach ($items as &$it) {
            $this->buildBuyUrl($it);
        }
        unset($it);

        return $this->aggregateSameItems($items);
    }

    /**
     * 生成带全部筛选条件的列表 URL（供分页器拼 &page=N）
     */
    private function buildFilterUrl($keyword, $platform, $filters = array(), $sort = ''){
        $url = search_url() . "?content=" . urlencode($keyword) . "&platform=" . urlencode($platform);
        if ($sort !== '' && $sort !== null) { $url .= "&sort=" . urlencode($sort); }
        $map = array('nav', 'cat', 'brand', 'pmin', 'pmax', 'rate');
        foreach ($map as $k) {
            $v = $filters[$k] ?? '';
            if ($v === '' || $v === null) { continue; }
            if (is_numeric($v) && floatval($v) == 0) { continue; }
            $url .= "&" . $k . "=" . urlencode(strval($v));
        }
        return $url;
    }

    private function searchApi($keyword, $platform, $pageNum, $pageSize, $sort = 'score', $brand = '', $cat = 0, $pmin = '', $pmax = ''){
        $client = $this->createTjkClient();
        if (!$client) {
            return ['code' => 0, 'message' => 'API未配置', 'items' => [], 'total' => 0];
        }

        // 品牌 key -> 真实品牌词
        $brandWord = $brand !== '' ? self::brandKeyword($brand, $cat) : '';

        // 关键词本身已包含品牌名（如 "iphone 15" 隐含苹果）时，自动识别并把品牌词并入搜索，
        // 这样即便该品牌未开店、由代理在标题里写品牌名，也能被召回
        if ($brandWord === '' && $keyword !== '') {
            $detected = self::matchBrandAlias($keyword, $cat);
            if ($detected !== '') {
                $brandWord = $detected;
            }
        }

        // 分类词也拼入关键词，增强联动（如 "跑鞋 耐克"）
        $kw = $keyword;
        if ($cat > 0 && isset(self::$mainCats[$cat]) && $kw === '') {
            $kw = self::$mainCats[$cat];
        }

        // 品牌词始终拼进关键词一起搜索（而非仅作为独立 brand 参数）：
        // 代理销售的商品 brandName 字段常为空，但标题含品牌名，拼进关键词可按标题命中
        if ($brandWord !== '') {
            $kw = trim($kw . ' ' . $brandWord);
        }

        // 注意：品牌词已并入 $kw，调用时 brand 参数传空，避免重复且统一走标题匹配
        return $client->searchGoods($kw, $platform, $pageNum, $pageSize, 1, $sort, '', '', $pmin, $pmax);
    }

    /**
     * 从搜索词中识别品牌别名（中/英文），返回该品牌的标准搜索词（中文）。
     * 用于：用户搜 "iphone 15" 时已隐含苹果品牌，自动并入品牌词提升召回。
     */
    private static function matchBrandAlias($keyword, $cat = 0) {
        $kw = mb_strtolower($keyword, 'UTF-8');

        // 1) 优先查别名映射（按长度降序，长词优先，避免 "mi" 抢占 "xiaomimi"）
        $aliases = self::$brandAliases;
        uksort($aliases, function($a, $b) { return mb_strlen($b) - mb_strlen($a); });
        foreach ($aliases as $alias => $std) {
            if (mb_stripos($kw, $alias) !== false) {
                return $std;
            }
        }

        // 2) 再查品牌库的英文名/中文名别名
        $groups = array();
        if ($cat > 0 && isset(self::$brandLib[$cat])) {
            $groups[] = self::$brandLib[$cat];
        }
        if (isset(self::$brandLib[0])) {
            $groups[] = self::$brandLib[0];
        }
        foreach ($groups as $lib) {
            foreach ($lib as $name) {
                $alphas = explode('/', $name);
                foreach ($alphas as $a) {
                    $a = trim($a);
                    if ($a === '') continue;
                    if (mb_stripos($kw, $a) !== false) {
                        return trim(end($alphas));
                    }
                }
            }
        }
        return '';
    }

    private function outputApiResult($apiResult, $keyword, $platform, $pageSize, $sort = 'score', $filters = array()){
        $rate = floatval($filters['rate'] ?? 0);

        if ($apiResult['code'] == 1 && !empty($apiResult['items'])) {
            $list = $apiResult['items'];
            foreach ($list as &$it) {
                $this->buildBuyUrl($it);
                $it['url'] = $it['buyUrl'] ?? '';
                $it['scoreTxt'] = $this->getShopScore($it);
                if (empty($it['price_sign'])) { $it['price_sign'] = '¥'; }
                if (empty($it['actualPrice']) && !empty($it['price'])) { $it['actualPrice'] = $it['price']; }
            }
            unset($it);

            // 评分下限：API 不支持该入参，这里做结果级过滤（有评分的才判断）
            if ($rate > 0) {
                $list = array_values(array_filter($list, function($it) use ($rate) {
                    $s = floatval($it['scoreTxt'] ?? 0);
                    return $s <= 0 ? false : ($s >= $rate);
                }));
                if (empty($list)) {
                    $this->page = array('list' => array(), 'count' => 0, 'pages' => '');
                    $this->Page = $this->page;
                    $this->dataSource = 'none';
                    $this->emptyTip = '当前评分条件下没有找到相关商品，试试降低评分要求';
                    $this->display('app/index/view/search/index');
                    return;
                }
            }

            $listUrl = $this->buildFilterUrl($keyword, $platform, $filters, $sort) . "&page={page}";
            $total = $apiResult['total'] ?? count($list);

            $pages = new \ZhiCms\ext\PageIndex;
            $array = array('list' => $list, 'count' => $total, 'pages' => $pages->show($listUrl, $total, $pageSize));
            $this->page = $array;
            $this->Page = $array;
            $this->dataSource = 'api';
            $this->display('app/index/view/search/index');
            exit();
        }

        $this->page = array('list' => array(), 'count' => 0, 'pages' => '');
        $this->Page = $this->page;
        $this->dataSource = 'none';
        // 无数据时统一友好提示：忽略接口返回的 'success' 等无效 message
        if (empty($apiResult['items'])) {
            $emptyTip = !empty($keyword) ? ('没有找到与「' . $keyword . '」相关的商品，换个关键词或筛选条件试试') : '没有找到相关商品，换个关键词或筛选条件试试';
        } else {
            $emptyTip = $apiResult['message'] ?? '没有找到相关商品';
        }
        $this->emptyTip = $emptyTip;
        $this->display('app/index/view/search/index');
    }

    private function buildBuyUrl(&$item){
        $item['buyUrl'] = '';
        
        $goodsId = $item['goodsId'] ?? '';
        $platform = $item['item_from'] ?? ($item['laiyuan'] == 2 ? 'pdd' : ($item['laiyuan'] == 4 ? 'jd' : ($item['laiyuan'] == 3 ? 'vip' : 'tb')));
        // 大淘客淘宝标记 dtk / 全拼 taobao 统一规范为 tb
        if ($platform === 'dtk' || $platform === 'taobao') $platform = 'tb';

        // 统一走 Tjk 转链，不再用 itemLink 直跳（itemLink 为原始链接，无佣金）
        if (!empty($goodsId)) {
            $item['buyUrl'] = url($route='index/redirect/jump', ['platform' => $platform, 'id' => $goodsId]);
        } else {
            $title = urlencode($item['title'] ?? '');
            switch ($platform) {
                case 'jd':  $item['buyUrl'] = 'https://search.jd.com/Search?keyword=' . $title; break;
                case 'pdd': $item['buyUrl'] = 'https://mobile.yangkeduo.com/search_result.html?search_key=' . $title; break;
                case 'vip': $item['buyUrl'] = 'https://search.vip.com/s/?keyword=' . $title; break;
                default:    $item['buyUrl'] = 'https://s.taobao.com/search?q=' . $title;
            }
        }
    }

    // 取店铺评分文本（API/本地库字段：dsrScore / shopLevel / serviceScore / descScore）
    private function getShopScore($item){
        $s = floatval($item['dsrScore'] ?? 0);
        if ($s <= 0) { $s = floatval($item['shopLevel'] ?? 0); }
        if ($s <= 0) { $s = floatval($item['serviceScore'] ?? 0); }
        if ($s <= 0) { $s = floatval($item['descScore'] ?? 0); }
        // 部分渠道返回 0-100 或 1-5 星，统一归一到 5 分制展示
        if ($s > 5 && $s <= 100) { $s = $s / 20; }
        return $s > 0 ? sprintf('%.1f', $s) : '';
    }

    private function aggregateSameItems(array $items): array {
        $groups = [];
        foreach ($items as $item) {
            $title = trim($item['title'] ?? '');
            if ($title === '') continue;
            $norm = $this->normTitle($title);
            $bestIdx = -1;
            $bestSim = 0.0;
            foreach ($groups as $gi => $g) {
                $sim = $this->titleSim($norm, $g['norm']);
                if ($sim > $bestSim) {
                    $bestSim = $sim;
                    $bestIdx = $gi;
                }
            }
            if ($bestIdx >= 0 && $bestSim >= 0.5) {
                $groups[$bestIdx]['items'][] = $item;
            } else {
                $groups[] = [
                    'norm'     => $norm,
                    'repTitle' => $title,
                    'repPic'   => $item['mainPic'] ?? '',
                    'items'    => [$item],
                ];
            }
        }

        foreach ($groups as &$g) {
            $priceByPlatform = [];
            $minPrice = PHP_FLOAT_MAX;
            foreach ($g['items'] as $it) {
                $pf = $it['item_from'] ?? ($it['laiyuan'] == 2 ? 'pdd' : ($it['laiyuan'] == 4 ? 'jd' : ($it['laiyuan'] == 3 ? 'vip' : 'tb')));
                $price = floatval($it['actualPrice'] ?? $it['price'] ?? 0);
                if (!isset($priceByPlatform[$pf]) || $price < $priceByPlatform[$pf]['price']) {
                    $priceByPlatform[$pf] = ['price' => $price, 'item' => $it];
                }
                if ($price < $minPrice) {
                    $minPrice = $price;
                }
            }
            $g['priceByPlatform'] = $priceByPlatform;
            $g['minPrice'] = $minPrice == PHP_FLOAT_MAX ? 0 : $minPrice;
            $g['platformCount'] = count($priceByPlatform);
        }
        unset($g);

        usort($groups, function($a, $b) {
            if (abs($a['minPrice'] - $b['minPrice']) > 0.001) {
                return $a['minPrice'] <=> $b['minPrice'];
            }
            return $b['platformCount'] <=> $a['platformCount'];
        });

        return $groups;
    }

    private function normTitle(string $t): string {
        $t = preg_replace('/[^\p{Han}A-Za-z0-9]/u', '', $t);
        $stop = ['包邮','券后','优惠券','旗舰店','官方','正品','同款','现货','顺丰','免运费','百亿补贴',
            '天猫','淘宝','京东','拼多多','京东自营','促销','热销','爆款','新品','包退','运费险','专柜',
            '代购','原装','正品保证','限时','秒杀','抢','拍下','立减','满减','折扣','买','送','赠','元','个'];
        $t = str_replace($stop, '', $t);
        return mb_strtolower($t, 'UTF-8');
    }

    private function titleSim(string $a, string $b): float {
        if ($a === '' || $b === '') return 0;
        if ($a === $b) return 1;
        $ga = $this->bigrams($a);
        $gb = $this->bigrams($b);
        if (empty($ga) || empty($gb)) return 0;
        $inter = count(array_intersect($ga, $gb));
        $union = count(array_unique(array_merge($ga, $gb)));
        return $union ? $inter / $union : 0;
    }

    private function bigrams(string $s): array {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        if ($n <= 1) return $chars;
        $bg = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $bg[] = $chars[$i] . $chars[$i + 1];
        }
        return $bg;
    }

    private function platformToLaiyuan($platform) {
        switch (strtolower($platform)) {
            case 'pdd': return 2;
            case 'vip': return 3;
            case 'jd':  return 4;
            default:    return 1;
        }
    }

    private function createTjkClient() {
        $api = \app\common\ConfigStore::load('api');
        $dtkAppKey = $api['dtk_appkey'] ?? '';
        $dtkAppSecret = $api['dtk_appsecret'] ?? '';
        $hdkApiKey = $api['hdk_appkey'] ?? '';

        if (empty($dtkAppKey) && empty($hdkApiKey)) {
            return null;
        }

        return new \ZhiCms\ext\Tjk([
            'DtkappKey' => $dtkAppKey,
            'DtkappSecret' => $dtkAppSecret,
            'HdkApiKey' => $hdkApiKey,
        ]);
    }

    private function resolveTaobaoGoodsId($url) {
        $cacheKey = 'search_tb_gid_' . md5($url);
        return tcache($cacheKey, function() use ($url) {
            try {
                $api = \app\common\ConfigStore::load('api');
                $dtkAppKey = $api['dtk_appkey'] ?? '';
                $dtkAppSecret = $api['dtk_appsecret'] ?? '';
                if (empty($dtkAppKey) || empty($dtkAppSecret)) return null;

                $tjk = new \ZhiCms\ext\Tjk();
                $dtk = $tjk->getDtk();
                if (!$dtk) return null;

                $parsed = $dtk->ParseContent($url);
                if ($parsed['code'] == 1 && !empty($parsed['data']['goodsId'])) {
                    return $parsed['data']['goodsId'];
                }

                $twd = $dtk->TwdToTwd($url);
                if ($twd['code'] == 1 && !empty($twd['data']['goodsId'])) {
                    return $twd['data']['goodsId'];
                }
                return null;
            } catch (\Exception $e) {
                return null;
            }
        }, 600);
    }
}