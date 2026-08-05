<?php
namespace ZhiCms\ext\Tjk;

class Hdk {
    
    protected $apiKey;
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * 安全地把 API 返回的券时间转换为 date() 可用的时间戳字符串
     * 兼容：数字时间戳（秒）、数字时间戳字符串、标准日期字符串（如 2026-08-01）
     * @param mixed $time
     * @return string 格式化后的日期时间，无法解析则返回 ''
     */
    protected static function safeTime($time) {
        if ($time === '' || $time === null) return '';
        if (is_numeric($time)) {
            $ts = intval($time);
            if ($ts <= 0) return '';
            // 兼容毫秒级时间戳（13位）
            if ($ts > 9999999999) $ts = intval($ts / 1000);
            return date('Y-m-d H:i:s', $ts);
        }
        // 日期字符串：用 strtotime 解析，失败则原样返回或置空
        $ts = strtotime($time);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : '';
    }
    
    protected function request($host, $params) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $host . '?' . http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $output = curl_exec($ch);
            if (curl_error($ch)) {
                return ['code' => 0, 'msg' => curl_error($ch)];
            }
            curl_close($ch);
            return json_decode($output, true);
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }
    
    public function SearchGoods($keyword, $back = 20, $minId = 1) {
        $host = 'http://v3.api.haodanku.com/supersearch';
        $params = [
            'apikey' => $this->apiKey,
            'keyword' => $keyword,
            'back' => $back,
            'min_id' => $minId,
        ];
        
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || $result['code'] != 1) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败'];
        }
        
        $items = [];
        foreach ($result['data'] as $item) {
            $items[] = [
                'id' => $item['itemid'] ?? '',
                'goodsId' => $item['itemid'] ?? '',
                'title' => $item['itemtitle'] ?? '',
                'dtitle' => $item['itemshorttitle'] ?? '',
                'originalPrice' => $item['itemprice'] ?? 0,
                'actualPrice' => $item['itemendprice'] ?? 0,
                'shopType' => ($item['shoptype'] ?? '') == 'B' ? 1 : 0,
                'goldSellers' => 0,
                'monthSales' => $item['itemsale'] ?? 0,
                'twoHoursSales' => 0,
                'dailySales' => 0,
                'commissionType' => 0,
                'desc' => $item['itemdesc'] ?? '',
                'couponReceiveNum' => 0,
                'couponLink' => $item['couponurl'] ?? '',
                // 券时间安全转换：API 返回可能是数字时间戳字符串或日期字符串，
                // 直接传入 date() 会触发 PHP8 "Argument #2 must be of type ?int" 报错。
                'couponEndTime' => isset($item['couponendtime']) ? self::safeTime($item['couponendtime']) : '',
                'couponStartTime' => isset($item['couponstarttime']) ? self::safeTime($item['couponstarttime']) : '',
                'couponPrice' => $item['couponmoney'] ?? 0,
                'couponConditions' => '',
                'activityType' => 0,
                'createTime' => '',
                'mainPic' => $item['itempic'] ?? '',
                'marketingMainPic' => '',
                'sellerId' => $item['sellerid'] ?? '',
                'cid' => 0,
                'discounts' => ($item['itemprice'] ?? 0) > 0 ? round(($item['itemendprice'] ?? 0) / ($item['itemprice'] ?? 1), 2) : 0,
                'commissionRate' => $item['tkrates'] ?? 0,
                'couponTotalNum' => 0,
                'haitao' => 0,
                'activityStartTime' => '',
                'activityEndTime' => '',
                'shopName' => '',
                'shopLevel' => 0,
                'descScore' => 0,
                'brand' => 0,
                'brandId' => 0,
                'brandName' => '',
                'hotPush' => 0,
                'teamName' => '',
                'itemLink' => '',
                'tchaoshi' => 0,
                'detailPics' => '',
                'dsrScore' => 0,
                'dsrPercent' => 0,
                'shipScore' => 0,
                'shipPercent' => 0,
                'serviceScore' => 0,
                'servicePercent' => 0,
                'subcid' => [],
                'quanMLink' => 0,
                'hzQuanOver' => 0,
                'yunfeixian' => 0,
                'estimateAmount' => -1,
                'freeshipRemoteDistrict' => 0,
                'tbcid' => 0,
                'couponsurplus' => $item['couponsurplus'] ?? 0,
                'min_buy' => $item['min_buy'] ?? 0,
                'item_from' => $item['item_from'] ?? '',
            ];
        }
        
        $items = array_map(function($it){ return \ZhiCms\ext\Tjk::standardizeItem($it, 'taobao'); }, $items);
        return [
            'code' => 1,
            'message' => 'success',
            'total' => count($items),
            'pageId' => $result['min_id'] ?? '',
            'minId' => $result['min_id'] ?? 1,
            'tb_p' => $result['tb_p'] ?? 1,
            'items' => $items,
        ];
    }

    /**
     * 拼多多搜索（好单库） http://v2.api.haodanku.com/pdd_goods_search
     * 成功状态码 200（与淘宝 supersearch 的 1 不同，需单独判断）
     */
    public function SearchPddGoods($keyword, $limit = 20, $minId = 1, $sort = '', $isCoupon = '') {
        $host = 'http://v2.api.haodanku.com/pdd_goods_search';
        // 好单库拼多多接口实际以 back 作为每页条数（文档虽写 limit，实际校验 back），
        // 且必须取 10,20,30,40,50,60,70,80,90,100 之一
        $allowed = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        $back = in_array(intval($limit), $allowed) ? intval($limit) : 20;
        $params = [
            'apikey' => $this->apiKey,
            'keyword' => $keyword,
            'back' => $back,
            'min_id' => $minId,
        ];
        if ($sort !== '')    $params['sort'] = $sort;
        if ($isCoupon !== '') $params['is_coupon'] = $isCoupon;

        $result = $this->request($host, $params);

        if (!isset($result['code']) || $result['code'] != 200) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败'];
        }

        $items = [];
        foreach (($result['data'] ?? []) as $item) {
            $items[] = [
                'goodsId' => $item['goods_id'] ?? '',
                'goodsSign' => $item['goods_sign'] ?? '',
                'title' => $item['goodsname'] ?? '',
                'originalPrice' => $item['itemprice'] ?? 0,
                'actualPrice' => $item['itemendprice'] ?? 0,
                'monthSales' => $item['itemsale'] ?? 0,
                'mainPic' => $item['itempic'] ?? '',
                'couponPrice' => $item['couponmoney'] ?? 0,
                'couponReceiveNum' => $item['couponnum'] ?? 0,
                'couponLink' => '',
                'couponStartTime' => isset($item['couponstarttime']) ? date('Y-m-d H:i:s', intval($item['couponstarttime'])) : '',
                'couponEndTime' => isset($item['couponendtime']) ? date('Y-m-d H:i:s', intval($item['couponendtime'])) : '',
                'commissionRate' => $item['promotion_rate'] ?? 0,
                'shopName' => $item['shopname'] ?? '',
                'detailPics' => $item['pdd_image'] ?? '',
                'item_from' => $item['item_from'] ?? 'pdd',
            ];
        }

        $items = array_map(function($it){ return \ZhiCms\ext\Tjk::standardizeItem($it, 'pdd'); }, $items);
        return [
            'code' => 1,
            'message' => 'success',
            'total' => count($items),
            'minId' => $result['min_id'] ?? 1,
            'items' => $items,
        ];
    }

    /**
     * 京东搜索（好单库） http://v3.api.haodanku.com/jd_goods_search
     * 成功状态码 200
     */
    public function SearchJdGoods($keyword, $back = 20, $minId = 1, $sort = '', $hasCoupon = '') {
        $host = 'http://v3.api.haodanku.com/jd_goods_search';
        // 好单库京东接口 back 仅允许 1/2/5/10/20/30/50，超出则回退 20
        $allowed = [1, 2, 5, 10, 20, 30, 50];
        $back = in_array(intval($back), $allowed) ? intval($back) : 20;
        $params = [
            'apikey' => $this->apiKey,
            'keyword' => $keyword,
            'back' => $back,
            'min_id' => $minId,
        ];
        if ($sort !== '')      $params['sort'] = $sort;
        if ($hasCoupon !== '') $params['has_coupon'] = $hasCoupon;

        $result = $this->request($host, $params);

        if (!isset($result['code']) || $result['code'] != 200) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败'];
        }

        $items = [];
        foreach (($result['data'] ?? []) as $item) {
            $items[] = [
                'goodsId' => $item['itemid'] ?? $item['skuid'] ?? '',
                'title' => $item['goodsname'] ?? '',
                'originalPrice' => $item['itemprice'] ?? 0,
                'actualPrice' => $item['itemendprice'] ?? 0,
                'monthSales' => $item['itemsale'] ?? 0,
                'mainPic' => $item['itempic'] ?? '',
                'couponPrice' => $item['couponmoney'] ?? 0,
                'couponReceiveNum' => $item['couponnum'] ?? 0,
                'couponLink' => $item['couponurl'] ?? '',
                'couponStartTime' => isset($item['couponstarttime']) ? date('Y-m-d H:i:s', intval($item['couponstarttime'])) : '',
                'couponEndTime' => isset($item['couponendtime']) ? date('Y-m-d H:i:s', intval($item['couponendtime'])) : '',
                'commissionRate' => $item['commissionshare'] ?? 0,
                'shopName' => $item['shopname'] ?? '',
                'shopType' => $item['shoptype'] ?? $item['shop_type'] ?? 0,
                'item_from' => $item['item_from'] ?? 'jd',
            ];
        }

        $items = array_map(function($it){ return \ZhiCms\ext\Tjk::standardizeItem($it, 'jd'); }, $items);
        return [
            'code' => 1,
            'message' => 'success',
            'total' => count($items),
            'minId' => $result['min_id'] ?? 1,
            'items' => $items,
        ];
    }

    /**
     * 唯品会搜索（好单库） http://v2.api.haodanku.com/vip_goods_search
     * 成功状态码 200（接口文档仅给出请求参数，返回字段以下方映射为最佳适配）
     */
    public function SearchVipGoods($keyword, $minSize = 20, $minId = 1) {
        $host = 'http://v2.api.haodanku.com/vip_goods_search';
        // 好单库唯品会接口实际以 back 控制每页条数，仅允许 1/2/5/10/20/50，超出则回退 20
        $allowed = [1, 2, 5, 10, 20, 50];
        $back = in_array(intval($minSize), $allowed) ? intval($minSize) : 20;
        $params = [
            'apikey' => $this->apiKey,
            'keyword' => $keyword,
            'back' => $back,
            'min_id' => $minId,
        ];

        $result = $this->request($host, $params);

        if (!isset($result['code']) || $result['code'] != 200) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败'];
        }

        $items = [];
        foreach (($result['data'] ?? $result['list'] ?? []) as $item) {
            // 券时间：唯品会 couponstarttime 为秒级时间戳（0 表示无券）
            $couponStart = intval($item['couponstarttime'] ?? 0);
            $couponEnd   = intval($item['couponendtime'] ?? 0);
            $items[] = [
                'goodsId' => $item['goodsid'] ?? '',
                'title' => $item['itemtitle'] ?? '',
                'dtitle' => $item['itemshorttitle'] ?? '',
                'originalPrice' => $item['itemprice'] ?? 0,        // 在售价
                'actualPrice' => $item['itemendprice'] ?? $item['itemprice'] ?? 0, // 商品券后价
                'discounts' => $item['itemrate'] ?? 0,             // 折扣: 唯品价/市场价
                'couponPrice' => $item['couponmoney'] ?? 0,        // 优惠券金额
                'couponLink' => $item['itemurl'] ?? '',            // 商品落地页（转链用）
                'couponStartTime' => $couponStart ? date('Y-m-d H:i:s', $couponStart) : '0',
                'couponEndTime' => $couponEnd ? date('Y-m-d H:i:s', $couponEnd) : '0',
                'couponConditions' => $item['couponminbuy'] ?? '', // 券最小金额购买
                'commissionRate' => $item['tkrates'] ?? 0,         // 佣金比例(%)
                'commissionType' => 0,
                'monthSales' => intval(preg_replace('/\D/', '', (string)($item['itemsale'] ?? 0))), // 销量(原值如 "2000+")
                'mainPic' => $item['itempic'] ?? '',
                'shopType' => $item['shoptype'] ?? 0,              // 店铺类型
                'shopName' => $item['shopname'] ?? '',             // 店铺名称
                'brandId' => $item['brandid'] ?? 0,                // 品牌ID
                'brandName' => $item['brandname'] ?? '',           // 品牌名称
                'cid' => $item['son_category'] ?? 0,               // 子类目ID
                'subcid' => $item['itemcategory'] ?? '',           // 类目名称
                'detailPics' => !empty($item['goodsCarouselPictures']) ? (is_array($item['goodsCarouselPictures']) ? json_encode($item['goodsCarouselPictures'], JSON_UNESCAPED_UNICODE) : $item['goodsCarouselPictures']) : '',
                'whiteImage' => $item['whiteImage'] ?? '',
                'item_from' => $item['item_from'] ?? 'vip',
            ];
        }

        $items = array_map(function($it){ return \ZhiCms\ext\Tjk::standardizeItem($it, 'vip'); }, $items);
        return [
            'code' => 1,
            'message' => 'success',
            'total' => count($items),
            'minId' => $result['min_id'] ?? 1,
            'items' => $items,
        ];
    }
    
    /**
     * 朋友圈采集（好单库） http://v3.api.haodanku.com/friends_circle_items
     * 成功状态码 200
     */
    public function FriendsCircleItems($minId = 1) {
        $host = 'http://v3.api.haodanku.com/friends_circle_items';
        $params = [
            'apikey' => $this->apiKey,
            'min_id' => $minId,
        ];

        $result = $this->request($host, $params);

        if (!isset($result['code']) || ($result['code'] != 1 && $result['code'] != 200)) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败', 'data' => []];
        }

        return [
            'code' => 1,
            'message' => 'success',
            'min_id' => $result['min_id'] ?? 1,
            'data' => $result['data'] ?? [],
        ];
    }

    /**
     * 获取好单库 emoji 列表（进程内缓存），用于把文案中的 $emoji表情[ID]$ 占位符替换为真实表情
     * 接口：http://api.haodanku.com/emoji/emoji_list_api
     */
    public function GetEmojiList() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $host = 'http://api.haodanku.com/emoji/emoji_list_api';
        $result = $this->request($host, []);
        $map = [];
        if (is_array($result)) {
            foreach ($result as $em) {
                if (isset($em['id'])) {
                    $map[intval($em['id'])] = $em;
                }
            }
        }
        $cache = $map;
        return $cache;
    }

    /**
     * 处理朋友圈文案中的 emoji / 普通表情 / 淘口令 占位符
     *   $emoji表情[ID]$ / $普通表情[ID]$ -> <img> 表情（无图时退化为 emoji 字符）
     *   $淘口令$ -> 移除
     */
    public function ProcessEmoji($text) {
        if (empty($text)) {
            return $text;
        }
        $map = $this->GetEmojiList();
        $text = preg_replace_callback('/\$(?:emoji表情|普通表情)\[(\d+)\]\$/', function($m) use ($map) {
            $id = intval($m[1]);
            if (!isset($map[$id])) {
                return '';
            }
            $em = $map[$id];
            $img = $em['imgurl'] ?? '';
            $char = $em['image'] ?? '';
            if (!empty($img)) {
                $alt = !empty($char) ? $char : '';
                return '<img src="' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . '" class="hdk-emoji" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" style="width:20px;height:20px;vertical-align:middle;display:inline-block;" />';
            }
            if (!empty($char)) {
                return $char;
            }
            return '';
        }, $text);
        // 淘口令占位符移除
        $text = str_replace('$淘口令$', '', $text);
        return $text;
    }

    public function RatesUrl($goodsId) {
        $host = 'http://v3.api.haodanku.com/ratesurl';
        $params = [
            'apikey' => $this->apiKey,
            'itemid' => $goodsId,
        ];
        
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || ($result['code'] != 1 && $result['code'] != 200)) {
            return ['code' => 0, 'message' => $result['msg'] ?? '转链失败'];
        }
        
        return [
            'code' => 1,
            'message' => 'success',
            'data' => [
                'couponLink' => $result['data']['couponurl'] ?? '',
                'taokeLink' => $result['data']['tkl'] ?? '',
                'tkl' => $result['data']['tkl'] ?? '',
                'shortLink' => '',
            ],
        ];
    }

    public function GetGoodsDetails($goodsId) {
        $host = 'http://v3.api.haodanku.com/item_detail';
        $params = [
            'apikey' => $this->apiKey,
            'itemid' => $goodsId,
        ];

        $result = $this->request($host, $params);

        // 好单库 v3 接口成功码为 200（与淘宝 supersearch 的 1 不同）
        if (!isset($result['code']) || ($result['code'] != 1 && $result['code'] != 200)) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败'];
        }

        $data = \ZhiCms\ext\Tjk::standardizeItem($result['data'] ?? [], 'taobao');
        return [
            'code' => 1,
            'message' => 'success',
            'data' => $data,
        ];
    }
}