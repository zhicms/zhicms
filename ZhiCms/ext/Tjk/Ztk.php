<?php
namespace ZhiCms\ext\Tjk;

/**
 * 折京客（zhetaoke）京东联盟接口封装。
 *
 * 当前实现：京东精选（京粉）商品列表
 *   open_jing_union_open_goods_jingfen_query.ashx
 *
 * 说明：
 *  - 该接口为「频道制」（按 eliteId 拉取精选商品），不支持关键词搜索。
 *  - 返回结构嵌套较深（京东联盟原始结构），且最外层 result 为 JSON 字符串（需二次解码）。
 *  - 时间戳为毫秒级，映射时需 /1000。
 *  - 商品唯一标识为 itemId（加密串），非数字 skuId；转链需走折京客自身京东转链接口。
 */
class Ztk {

    protected $appKey;
    protected $unionId;
    protected $host = 'http://api.zhetaoke.com:20000/api/open_jing_union_open_goods_jingfen_query.ashx';
    protected $hostQuery = 'http://api.zhetaoke.com:20000/api/open_jing_union_open_goods_query.ashx';

    public function __construct($appKey, $unionId = '') {
        $this->appKey  = $appKey;
        $this->unionId = $unionId;
    }

    /**
     * 京东精选（京粉）商品列表
     *
     * @param int    $eliteId   频道ID（1-好券商品 2-精选卖场 10-9.9包邮 15-京东配送 22-实时热销榜 ... 详见接口文档）
     * @param int    $pageIndex 页码（从 1 开始）
     * @param int    $pageSize  每页数量
     * @param string $sortName  排序字段：price / commissionShare / commission / inOrderCount30DaysSku / comments / goodComments
     * @param string $sortDir   排序方向：asc / desc
     * @return array ['code'=>1,'message'=>'success','total'=>int,'items'=>[...标准化商品...]]
     */
    public function SearchJdGoodsJingfen($eliteId = 1, $pageIndex = 1, $pageSize = 20, $sortName = 'inOrderCount30DaysSku', $sortDir = 'desc') {
        $params = [
            'appkey'    => $this->appKey,
            'unionId'   => $this->unionId,
            'eliteId'   => intval($eliteId),
            'pageIndex' => intval($pageIndex),
            'pageSize'  => intval($pageSize),
            'sortName'  => $sortName,
            'sort'      => ($sortDir === 'asc') ? 'asc' : 'desc',
        ];

        $raw = $this->request($this->host, $params);
        if ($raw === '' || $raw === false) {
            return ['code' => 0, 'message' => '折淘客接口请求失败（无响应）', 'items' => [], 'total' => 0];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['code' => 0, 'message' => '折淘客接口返回非 JSON', 'items' => [], 'total' => 0];
        }

        // 外层：jd_union_open_goods_jingfen_query_response { code, result(字符串) }
        $resp = $json['jd_union_open_goods_jingfen_query_response'] ?? null;
        if (!is_array($resp) || (string)($resp['code'] ?? '') !== '0') {
            return ['code' => 0, 'message' => '折淘客接口错误：' . json_encode($resp ?? $json, JSON_UNESCAPED_UNICODE), 'items' => [], 'total' => 0];
        }

        // result 为 JSON 字符串，需二次解码
        $result = json_decode($resp['result'] ?? '{}', true);
        if (!is_array($result) || intval($result['code'] ?? 0) != 200) {
            return ['code' => 0, 'message' => '折淘客业务错误：' . ($result['message'] ?? 'unknown'), 'items' => [], 'total' => 0];
        }

        $list  = $result['data'] ?? [];
        $total = intval($result['totalCount'] ?? count($list));

        $items = [];
        foreach ($list as $item) {
            $items[] = $this->mapItem($item);
        }
        // 统一字段标准化（与大淘客/好单库京东字段对齐）
        $items = array_map(function ($it) {
            return \ZhiCms\ext\Tjk::standardizeItem($it, 'jd');
        }, $items);

        return ['code' => 1, 'message' => 'success', 'total' => $total, 'items' => $items];
    }

    /**
     * 京东商品关键词搜索（折淘客 open_jing_union_open_goods_query）。
     *
     * 与京粉精选（频道制、忽略关键词）不同，本接口支持真实关键词搜索，并支持
     * 排序 / 是否有券 / 价格区间。注意：折淘客该接口对 cid1Id/cid3Id 分类参数
     * 实际无效（实测传任意 cid1Id 返回结果不变），故「分类对齐」交由调用方按
     * 返回字段 categoryInfo.cid1 做后端二次过滤（见 Tjk::searchGoods 京东分支）。
     *
     * @param string $keyword   搜索关键词
     * @param int    $pageIndex 页码（从 1 开始）
     * @param int    $pageSize  每页数量
     * @param string $sortName  排序字段：price / commissionShare / commission / inOrderCount30DaysSku / comments / goodComments
     * @param string $sortDir   排序方向：asc / desc
     * @param int    $isCoupon  是否只查有券商品（1=有券）
     * @param int    $priceFrom 价格下限（元，>0 生效）
     * @param int    $priceTo   价格上限（元，>0 生效）
     * @param int    $cid1      京东一级分类ID（占位，折淘客接口忽略，仅供日志/扩展）
     * @return array ['code'=>1,'message'=>'success','total'=>int,'items'=>[...标准化商品...]]
     */
    public function SearchJdGoodsQuery($keyword, $pageIndex = 1, $pageSize = 20, $sortName = 'inOrderCount30DaysSku', $sortDir = 'desc', $isCoupon = 0, $priceFrom = 0, $priceTo = 0, $cid1 = 0) {
        $params = [
            'appkey'    => $this->appKey,
            'unionId'   => $this->unionId,
            'keyword'   => trim($keyword),
            'pageIndex' => intval($pageIndex),
            'pageSize'  => intval($pageSize),
            'sortName'  => $sortName,
            'sort'      => ($sortDir === 'asc') ? 'asc' : 'desc',
        ];
        if (intval($isCoupon) === 1) {
            $params['isCoupon'] = 1;
        }
        if (floatval($priceFrom) > 0) {
            $params['pricefrom'] = floatval($priceFrom);
        }
        if (floatval($priceTo) > 0) {
            $params['priceto'] = floatval($priceTo);
        }
        if (intval($cid1) > 0) {
            $params['cid1Id'] = intval($cid1);
        }

        $raw = $this->request($this->hostQuery, $params);
        if ($raw === '' || $raw === false) {
            return ['code' => 0, 'message' => '折淘客接口请求失败（无响应）', 'items' => [], 'total' => 0];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['code' => 0, 'message' => '折淘客接口返回非 JSON', 'items' => [], 'total' => 0];
        }

        $resp = $json['jd_union_open_goods_query_response'] ?? null;
        if (!is_array($resp) || (string)($resp['code'] ?? '') !== '0') {
            return ['code' => 0, 'message' => '折淘客接口错误：' . json_encode($resp ?? $json, JSON_UNESCAPED_UNICODE), 'items' => [], 'total' => 0];
        }

        $result = json_decode($resp['result'] ?? '{}', true);
        if (!is_array($result) || intval($result['code'] ?? 0) != 200) {
            return ['code' => 0, 'message' => '折淘客业务错误：' . ($result['message'] ?? 'unknown'), 'items' => [], 'total' => 0];
        }

        $list  = $result['data'] ?? [];
        $total = intval($result['totalCount'] ?? count($list));

        $items = [];
        foreach ($list as $item) {
            $items[] = $this->mapItem($item);
        }
        $items = array_map(function ($it) {
            return \ZhiCms\ext\Tjk::standardizeItem($it, 'jd');
        }, $items);

        return ['code' => 1, 'message' => 'success', 'total' => $total, 'items' => $items];
    }

    /**
     * 把折淘客京东精选返回的单条（嵌套结构）映射为统一商品字段。
     * 时间戳为毫秒，需 /1000。
     */
    protected function mapItem(array $item) {
        // 选券：优先 isBest==1，否则取 discount 最大者
        $coupons = $item['couponInfo']['couponList'] ?? [];
        $best = null;
        foreach ($coupons as $c) {
            if (intval($c['isBest'] ?? 0) == 1) { $best = $c; break; }
        }
        if (!$best && !empty($coupons)) {
            usort($coupons, function ($a, $b) {
                return floatval($b['discount'] ?? 0) <=> floatval($a['discount'] ?? 0);
            });
            $best = $coupons[0];
        }
        $couponPrice  = $best ? floatval($best['discount'] ?? 0) : 0;
        $couponQuota  = $best ? ($best['quota'] ?? '') : '';
        $couponLink   = $best ? ($best['link'] ?? '') : '';
        $couponStart  = $best ? intval(($best['useStartTime'] ?? 0) / 1000) : 0;
        $couponEnd    = $best ? intval(($best['useEndTime'] ?? 0) / 1000) : 0;

        $priceInfo = $item['priceInfo'] ?? [];
        $price             = floatval($priceInfo['price'] ?? 0);
        $lowestCouponPrice = floatval($priceInfo['lowestCouponPrice'] ?? 0);
        $actualPrice = ($couponPrice > 0 && $lowestCouponPrice > 0) ? $lowestCouponPrice : $price;

        $commission      = $item['commissionInfo'] ?? [];
        $commissionRate = floatval($commission['commissionShare'] ?? 0);

        $imageInfo = $item['imageInfo'] ?? [];
        $imageList = $imageInfo['imageList'] ?? [];
        $mainPic   = !empty($imageList[0]['url']) ? $imageList[0]['url'] : ($imageInfo['whiteImage'] ?? '');
        $whiteImage = $imageInfo['whiteImage'] ?? '';

        $shop = $item['shopInfo'] ?? [];
        $cat  = $item['categoryInfo'] ?? [];

        // materialUrl 为相对路径（jingfen.jd.com/...），补全 https 作为落地页
        $materialUrl = $item['materialUrl'] ?? '';
        if ($materialUrl && stripos($materialUrl, 'http') !== 0) {
            $materialUrl = 'https://' . $materialUrl;
        }
        $spuid    = $item['spuid'] ?? '';
        $itemLink = $materialUrl ?: ($spuid ? ('https://item.jd.com/' . $spuid . '.html') : '');

        return [
            'goodsId'          => $item['itemId'] ?? '',
            'goodsSign'        => '',
            'title'            => $item['skuName'] ?? '',
            'dtitle'           => $item['skuName'] ?? '',
            'content'          => $item['skuName'] ?? '',
            'itemLink'         => $itemLink,
            'mainPic'          => $mainPic,
            'marketingMainPic' => $whiteImage,
            'originalPrice'    => $price,
            'actualPrice'      => $actualPrice,
            'discounts'        => 0,
            'couponPrice'      => $couponPrice,
            'couponLink'       => $couponLink,
            'couponStartTime'  => $couponStart ? date('Y-m-d H:i:s', $couponStart) : '0',
            'couponEndTime'    => $couponEnd ? date('Y-m-d H:i:s', $couponEnd) : '0',
            'couponConditions' => is_numeric($couponQuota) ? ('满' . $couponQuota . '元') : (string)$couponQuota,
            'couponTotalNum'   => 0,
            'couponReceiveNum' => 0,
            'couponRemainCount'=> 0,
            'couponId'         => '',
            'commissionRate'   => $commissionRate,
            'commissionType'   => 0,
            'monthSales'       => intval($item['inOrderCount30DaysSku'] ?? $item['inOrderCount30Days'] ?? 0),
            'twoHoursSales'    => 0,
            'dailySales'       => 0,
            'shopType'         => 0,
            'shopName'         => $shop['shopName'] ?? '',
            'shopId'           => intval($shop['shopId'] ?? 0),
            'shopLevel'        => floatval($shop['shopLevel'] ?? 0),
            'shopLogo'         => '',
            'cid'              => intval($cat['cid3'] ?? $cat['cid2'] ?? 0),
            'cid1'             => intval($cat['cid1'] ?? 0),
            'cid1Name'         => $cat['cid1Name'] ?? '',
            'subcid'           => $cat['cid3Name'] ?? '',
            'tbcid'            => 0,
            'brand'            => 0,
            'brandId'          => intval($item['brandCode'] ?? 0),
            'brandName'        => $item['brandName'] ?? '',
            'yunfeixian'       => 0,
            'freeshipRemoteDistrict' => 0,
            'item_from'        => 'jd',
        ];
    }

    /**
     * HTTP GET 请求（优先 curl，回退 file_get_contents）。
     */
    protected function request($url, $params) {
        $query = http_build_query($params);
        $full  = $url . (strpos($url, '?') === false ? '?' : '&') . $query;
        if (function_exists('curl_init')) {
            $ch = curl_init($full);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT      => 'ZhiCms-Ztk/1.0',
            ]);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err) {
                return false;
            }
            return $res;
        }
        if (ini_get('allow_url_fopen')) {
            return @file_get_contents($full);
        }
        return false;
    }

    /**
     * 京东转链（折淘客 open_jing_union_open_promotion_byunionid_get）。
     *
     * 重要：折京客京粉商品返回的商品标识是加密串 itemId（非数字 skuId），
     * 不能用于好单库京东转链（好单库 material_id 要求数字商品ID），必须走折淘客自有转链；
     * 实测 materialId = itemId 加密串即可成功生成推广短链（u.jd.com）+ 京口令。
     *
     * @param string $materialId 推广物料：京粉商品加密串 itemId / 商品链接 / 京粉链接 / 京东短链 / 口令（需 Urlencode）
     * @param string $couponUrl  优惠券领取链接（二合一用；为空则自动匹配官方券）
     * @param string $positionId 自定义推广位 id（返利用；导购可空）
     * @param string $subUnionId 子渠道标识（订单行透出）
     * @param int    $chainType  1=长链 2=短链(默认) 3=长链+短链
     * @return array 统一转链结构：['code'=>1,'message'=>...,'data'=>[shortLink,shortUrl,couponLink,url,tkl,...]]
     */
    public function CreateJdLink($materialId, $couponUrl = '', $positionId = '', $subUnionId = '', $chainType = 2) {
        $params = [
            'appkey'     => $this->appKey,
            'unionId'    => $this->unionId,
            'materialId' => $materialId,
            'chainType'  => $chainType,
        ];
        if ($couponUrl !== '')   $params['couponUrl']   = $couponUrl;
        if ($positionId !== '')  $params['positionId']  = $positionId;
        if ($subUnionId !== '')  $params['subUnionId']  = $subUnionId;

        $raw = $this->requestPost('http://api.zhetaoke.com:20000/api/open_jing_union_open_promotion_byunionid_get.ashx', $params);
        if ($raw === '' || $raw === false) {
            return ['code' => 0, 'message' => '折淘客京东转链请求失败（无响应）', 'data' => null];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['code' => 0, 'message' => '折淘客京东转链返回非 JSON', 'data' => null];
        }
        $resp = $json['jd_union_open_promotion_byunionid_get_response'] ?? null;
        if (!is_array($resp) || (string)($resp['code'] ?? '') !== '0') {
            return ['code' => 0, 'message' => '折淘客京东转链接口错误：' . json_encode($resp ?? $json, JSON_UNESCAPED_UNICODE), 'data' => null];
        }
        $result = json_decode($resp['result'] ?? '{}', true);
        if (!is_array($result) || intval($result['code'] ?? 0) != 200) {
            return ['code' => 0, 'message' => '折淘客京东转链业务错误：' . ($result['message'] ?? 'unknown'), 'data' => null];
        }
        $data = $result['data'] ?? [];

        $shortURL   = $data['shortURL'] ?? '';
        $clickURL   = $data['clickURL'] ?? '';
        $jShortCmd  = $data['jShortCommand'] ?? '';   // 京口令（短）
        $jCommand   = $data['jCommand'] ?? '';        // 京口令（长）

        $link = $shortURL ?: $clickURL;
        return [
            'code'    => 1,
            'message' => 'success',
            'data'    => [
                'shortLink'   => $link,
                'shortUrl'    => $link,
                'couponLink'  => $link,
                'longUrl'     => $clickURL,
                'url'         => $link,
                'tkl'         => $jShortCmd,
                'tpwd'        => $jShortCmd,
                'jCommand'    => $jCommand,
                'materialId'  => $materialId,
            ],
        ];
    }

    /**
     * HTTP POST 请求（优先 curl，回退 file_get_contents 流上下文）。
     */
    protected function requestPost($url, $params) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($params),
                CURLOPT_USERAGENT      => 'ZhiCms-Ztk/1.0',
            ]);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err) {
                return false;
            }
            return $res;
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($params),
                'timeout' => 15,
            ]]);
            return @file_get_contents($url, false, $ctx);
        }
        return false;
    }
}
