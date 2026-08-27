<?php
namespace app\api\controller;

/**
 * 小程序搜索接口（镜像电脑版 app\index\controller\SearchController 能力）
 *
 * 支持：
 *  - 本地文章 / 本地商品（yun_article / yun_items）
 *  - 第三方平台：淘宝 / 京东 / 拼多多 / 唯品会（大淘客 / 好单库）
 *  - 比价：本地 + 多平台按标题聚合同款，给出各平台最低价
 *
 * GET index.php?r=api/search&keyword=手机&platform=taobao&type=all&page=1
 *   platform: local | taobao | jd | pdd | vip | compare
 *   type:     all | goods | article   （仅 local 平台生效，第三方只返回商品）
 */
class SearchController extends ApiBaseController {

    /** 与电脑版一致的平台映射 */
    private $platforms = array(
        'local'  => '本地',
        'taobao' => '淘宝',
        'jd'     => '京东',
        'pdd'    => '拼多多',
        'vip'    => '唯品会',
        'compare'=> '比价',
    );

    public function index() {
        $this->options();

        $keyword  = trim(urldecode($this->raw('keyword', '')));
        $platform = strtolower($this->raw('platform', 'local'));
        $type     = strtolower($this->raw('type', 'all'));
        $page     = max(1, intval($this->raw('page', 1)));
        $pageSize = min(50, max(1, intval($this->raw('page_size', 20))));

        // 筛选条件
        $filters = $this->parseFilters();

        if ($keyword === '') {
            $this->json(array('code' => 0, 'message' => '请输入搜索关键词', 'items' => array(), 'has_more' => false));
            return;
        }
        if (!in_array($platform, array('local', 'taobao', 'jd', 'pdd', 'vip', 'compare'), true)) {
            $platform = 'local';
        }
        if (!in_array($type, array('all', 'goods', 'article'), true)) {
            $type = 'all';
        }

        if ($platform === 'compare') {
            $this->doCompare($keyword, $page, $pageSize, $filters);
            return;
        }

        if ($platform === 'local') {
            $this->doLocal($keyword, $type, $page, $pageSize, $filters);
            return;
        }

        // 第三方平台
        $this->doApi($keyword, $platform, $page, $pageSize, $filters);
    }

    /**
     * 解析前端筛选参数
     *  has_coupon : 是否有券（1/true/on）
     *  pmin/pmax  : 价格区间
     *  sort       : default | price_asc | price_desc | sales
     *  brand      : 品牌（追加到关键词）
     */
    private function parseFilters() {
        $hasCoupon = strtolower((string)$this->raw('has_coupon', ''));
        $sort      = strtolower((string)$this->raw('sort', 'default'));
        $pmin      = $this->raw('pmin', '');
        $pmax      = $this->raw('pmax', '');
        $brand     = trim((string)$this->raw('brand', ''));

        $allowedSort = array('default', 'price_asc', 'price_desc', 'sales');
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'default';
        }

        return array(
            'has_coupon' => ($hasCoupon === '1' || $hasCoupon === 'true' || $hasCoupon === 'on') ? '1' : '',
            'pmin'       => is_numeric($pmin) ? floatval($pmin) : '',
            'pmax'       => is_numeric($pmax) ? floatval($pmax) : '',
            'sort'       => $sort,
            'brand'      => $brand,
        );
    }

    /* ---------- 本地：文章 + 商品 ---------- */
    private function doLocal($keyword, $type, $page, $pageSize, $filters) {
        $goodsList = array();
        $articleList = array();

        if ($type === 'all' || $type === 'goods') {
            // 先转义引号（防 SQL 注入闭合），再转义 LIKE 通配符 % _ \（防全表扫描/ReDoS）
            $kw = addslashes($keyword);
            $kw = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $kw);
            $where = array("`del` = 0", "`title` LIKE '%{$kw}%'");
            // 本地价格区间筛选
            if (is_numeric($filters['pmin'])) {
                $where[] = "`actualPrice` >= " . floatval($filters['pmin']);
            }
            if (is_numeric($filters['pmax'])) {
                $where[] = "`actualPrice` <= " . floatval($filters['pmax']);
            }
            $order = '`id` DESC';
            if ($filters['sort'] === 'price_asc')  $order = '`actualPrice` ASC';
            if ($filters['sort'] === 'price_desc') $order = '`actualPrice` DESC';
            if ($filters['sort'] === 'sales')      $order = '`monthSales` DESC';
            $rows = obj('api/ApiData')->dataSelect('yun_items', $where, $order);
            $rows = $rows ?: array();
            foreach ($rows as $it) {
                $goodsList[] = $this->mapGoods($it, 'local');
            }
        }
        if ($type === 'all' || $type === 'article') {
            // 先转义引号（防 SQL 注入闭合），再转义 LIKE 通配符 % _ \（防全表扫描/ReDoS）
            $kw = addslashes($keyword);
            $kw = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $kw);
            $where = array("`status` = 1", "`title` LIKE '%{$kw}%'");
            $rows = obj('api/ApiData')->dataSelect('yun_article', $where, '`id` DESC');
            $rows = $rows ?: array();
            foreach ($rows as $a) {
                $articleList[] = $this->mapArticle($a);
            }
        }

        // 按时间倒序合并（本地无复杂相关度，简单按 id/date 倒序）
        $list = array_merge($goodsList, $articleList);
        usort($list, function ($a, $b) {
            $ta = strtotime($a['_ts'] ?? 0);
            $tb = strtotime($b['_ts'] ?? 0);
            return $tb - $ta;
        });

        $total = count($list);
        $slice = array_slice($list, ($page - 1) * $pageSize, $pageSize);
        // 去掉内部字段
        foreach ($slice as &$it) { unset($it['_ts']); }
        unset($it);

        $this->json(array(
            'code'     => 1,
            'message'  => 'success',
            'items'    => $slice,
            'total'    => $total,
            'has_more' => ($page * $pageSize) < $total,
        ));
    }

    /* ---------- 第三方平台 ---------- */
    private function doApi($keyword, $platform, $page, $pageSize, $filters) {
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $keywordForApi = $keyword;
            if (!empty($filters['brand'])) {
                $keywordForApi = trim($keyword . ' ' . $filters['brand']);
            }
            $res = $tjk->searchGoods(
                $keywordForApi,
                $platform,
                $page,
                $pageSize,
                1,                                  // minId
                $filters['sort'],                   // 排序
                $filters['has_coupon'],             // 是否有券
                '',                                 // brand（已并入关键词）
                $filters['pmin'],                   // pmin
                $filters['pmax']                    // pmax
            );
        } catch (\Throwable $e) {
            $res = array('code' => 0, 'message' => '接口异常');
        }
        if (empty($res) || ($res['code'] ?? 0) != 1 || empty($res['items'])) {
            $this->json(array(
                'code'    => 1,
                'message' => 'success',
                'items'   => array(),
                'total'   => 0,
                'has_more'=> false,
            ));
            return;
        }
        $items = array();
        foreach ($res['items'] as $it) {
            $m = \app\api\controller\GoodsController::mapProduct($it);
            $m = $this->normalizeProduct($m, $platform);
            // 价格区间二次过滤（部分接口未严格支持 pmin/pmax）
            $price = floatval($m['price'] ?? 0);
            if (is_numeric($filters['pmin']) && $price < floatval($filters['pmin'])) continue;
            if (is_numeric($filters['pmax']) && $price > floatval($filters['pmax'])) continue;
            $items[] = $m;
        }
        if (empty($items)) {
            $this->json(array('code' => 1, 'message' => 'success', 'items' => array(), 'total' => 0, 'has_more' => false));
            return;
        }
        // has_more 以本页实际返回数量判断，避免第三方接口不返回真实 total 导致分页失效
        $total = intval($res['total'] ?? 0);
        if ($total <= 0) {
            $total = ($page - 1) * $pageSize + count($items);
            if (count($items) >= $pageSize) {
                $total += 1; // 还有下一页的标记
            }
        }
        $this->json(array(
            'code'     => 1,
            'message'  => 'success',
            'items'    => $items,
            'total'    => $total,
            'has_more' => count($items) >= $pageSize,
        ));
    }

    /* ---------- 比价：淘宝 + 京东 + 拼多多 三家同款聚合 ---------- */
    private function doCompare($keyword, $page, $pageSize, $filters) {
        $all = array();

        // 第三方：仅 淘宝 / 京东 / 拼多多 三家
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $kw = $keyword;
            if (!empty($filters['brand'])) {
                $kw = trim($keyword . ' ' . $filters['brand']);
            }
            foreach (array('taobao', 'jd', 'pdd') as $pf) {
                $res = $tjk->searchGoods($kw, $pf, 1, max(20, $pageSize), 1, $filters['sort'], $filters['has_coupon'], '', $filters['pmin'], $filters['pmax']);
                if (!empty($res) && ($res['code'] ?? 0) == 1 && !empty($res['items'])) {
                    foreach ($res['items'] as $it) {
                        $m = \app\api\controller\GoodsController::mapProduct($it);
                        $m = $this->normalizeProduct($m, $pf);
                        // 价格区间过滤
                        $price = floatval($m['price'] ?? 0);
                        if (is_numeric($filters['pmin']) && $price < floatval($filters['pmin'])) continue;
                        if (is_numeric($filters['pmax']) && $price > floatval($filters['pmax'])) continue;
                        $all[] = $m;
                    }
                }
            }
        } catch (\Throwable $e) {
            // 第三方失败不影响其他平台
        }

        if (empty($all)) {
            $this->json(array('code' => 1, 'message' => 'success', 'items' => array(), 'total' => 0, 'has_more' => false));
            return;
        }

        $groups = $this->aggregate($all);
        $total = count($groups);
        $slice = array_slice($groups, ($page - 1) * $pageSize, $pageSize);

        // 每个分组补全最低价平台标记，便于前端高亮
        foreach ($slice as &$g) {
            $g['bestPlatform'] = '';
            $best = PHP_FLOAT_MAX;
            foreach (($g['priceByPlatform'] ?? array()) as $pf => $info) {
                if ($info['price'] < $best) { $best = $info['price']; $g['bestPlatform'] = $pf; }
            }
        }
        unset($g);

        $this->json(array(
            'code'     => 1,
            'message'  => 'success',
            'items'    => $slice,
            'total'    => $total,
            'has_more' => count($slice) >= $pageSize,
        ));
    }

    /* ---------- 聚合同款（参考电脑版 SearchController::aggregateSameItems） ---------- */
    private function aggregate(array $items) {
        $groups = array();
        foreach ($items as $item) {
            $title = trim($item['title'] ?? '');
            if ($title === '') continue;
            $norm = $this->normTitle($title);
            $bestIdx = -1; $bestSim = 0;
            foreach ($groups as $gi => $g) {
                $sim = $this->titleSim($norm, $g['norm']);
                if ($sim > $bestSim) { $bestSim = $sim; $bestIdx = $gi; }
            }
            if ($bestIdx >= 0 && $bestSim >= 0.5) {
                $groups[$bestIdx]['items'][] = $item;
            } else {
                $groups[] = array('norm' => $norm, 'repTitle' => $title, 'repPic' => $item['pic'] ?? '', 'items' => array($item));
            }
        }

        foreach ($groups as &$g) {
            $priceByPlatform = array();
            $minPrice = PHP_FLOAT_MAX;
            foreach ($g['items'] as $it) {
                $pf = $it['platform'] ?? 'local';
                $price = floatval($it['price'] ?? 0);
                if (!isset($priceByPlatform[$pf]) || $price < $priceByPlatform[$pf]['price']) {
                    $priceByPlatform[$pf] = array('price' => $price, 'item' => $it);
                }
                if ($price < $minPrice) $minPrice = $price;
            }
            $g['priceByPlatform'] = $priceByPlatform;
            $g['minPrice'] = $minPrice == PHP_FLOAT_MAX ? 0 : $minPrice;
            $g['platformCount'] = count($priceByPlatform);
        }
        unset($g);

        usort($groups, function ($a, $b) {
            if (abs($a['minPrice'] - $b['minPrice']) > 0.001) return $a['minPrice'] <=> $b['minPrice'];
            return $b['platformCount'] <=> $a['platformCount'];
        });
        return $groups;
    }

    private function normTitle($t) {
        $t = preg_replace('/[^\p{Han}A-Za-z0-9]/u', '', $t);
        $stop = array('包邮','券后','优惠券','旗舰店','官方','正品','同款','现货','顺丰','免运费','百亿补贴',
            '天猫','淘宝','京东','拼多多','京东自营','促销','热销','爆款','新品','包退','运费险','专柜',
            '代购','原装','正品保证','限时','秒杀','抢','拍下','立减','满减','折扣','买','送','赠','元','个');
        $t = str_replace($stop, '', $t);
        return mb_strtolower($t, 'UTF-8');
    }

    private function titleSim($a, $b) {
        if ($a === '' || $b === '') return 0;
        if ($a === $b) return 1;
        $ga = $this->bigrams($a); $gb = $this->bigrams($b);
        if (empty($ga) || empty($gb)) return 0;
        $inter = count(array_intersect($ga, $gb));
        $union = count(array_unique(array_merge($ga, $gb)));
        return $union ? $inter / $union : 0;
    }

    private function bigrams($s) {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        if ($n <= 1) return $chars;
        $bg = array();
        for ($i = 0; $i < $n - 1; $i++) $bg[] = $chars[$i] . $chars[$i + 1];
        return $bg;
    }

    /* ---------- 字段约化 ---------- */
    private function normalizeProduct($m, $platform) {
        if (empty($m) || !is_array($m)) return array('docType' => 'goods', 'title' => '', 'pic' => '', 'price' => 0, 'platform' => $platform);
        return array(
            'docType'      => 'goods',
            'id'           => $m['goodsId'] ?? ($m['id'] ?? ''),
            'goodsId'      => $m['goodsId'] ?? '',
            'goodsSign'    => $m['goodsSign'] ?? '',
            'title'        => $m['name'] ?? ($m['title'] ?? ''),
            'pic'          => $m['image'] ?? ($m['pic'] ?? ''),
            'price'        => floatval($m['price'] ?? 0),
            'originalPrice'=> floatval($m['originalPrice'] ?? 0),
            'couponPrice'  => floatval($m['couponPrice'] ?? 0),
            'shopName'     => $m['shopName'] ?? '',
            'platform'     => $platform,
            'itemLink'     => $m['itemLink'] ?? '',
            'couponLink'   => $m['couponLink'] ?? '',
            'monthSales'   => intval($m['sold'] ?? 0),
            'isChoice'     => false,
            'item_from'    => $platform,
            'detail_url'   => $m['detail_url'] ?? '',
        );
    }

    private function mapGoods($it, $platform) {
        $price = floatval($it['actualPrice'] ?? $it['price'] ?? 0);
        $orig = floatval($it['originalPrice'] ?? 0);
        return array(
            'docType'     => 'goods',
            'id'          => intval($it['id']),
            'goodsId'     => $it['goodsId'] ?? '',
            'goodsSign'   => $it['goodsSign'] ?? '',
            'title'       => $it['dtitle'] ?: ($it['title'] ?? ''),
            'pic'         => $it['mainPic'] ?? '',
            'price'       => $price,
            'originalPrice' => $orig,
            'couponPrice' => floatval($it['couponPrice'] ?? 0),
            'shopName'    => $it['shopName'] ?? '',
            'platform'    => $platform,
            'itemLink'    => $it['itemLink'] ?? '',
            'couponLink'  => $it['couponLink'] ?? '',
            'monthSales'  => intval($it['monthSales'] ?? 0),
            'isChoice'    => intval($it['choice'] ?? 0) === 1,
            'item_from'   => $it['item_from'] ?? $platform,
            '_ts'         => $it['update_time'] ?? ($it['create_time'] ?? '2026-01-01 00:00:00'),
        );
    }

    private function mapArticle($a) {
        $navid = intval($a['navid']);
        return array(
            'docType'  => 'article',
            'id'       => intval($a['id']),
            'title'    => $a['title'] ?: '',
            'pic'      => $a['mainPic'] ?: '',
            'desc'     => $a['dec'] ?: '',
            'catName'  => \app\base\controller\BaseController::getNavName($navid),
            'view'     => intval($a['view']),
            'date'     => $a['date'] ?: '',
            'navid'    => $navid,
            '_ts'      => $a['date'] ?? '2026-01-01 00:00:00',
        );
    }
}
