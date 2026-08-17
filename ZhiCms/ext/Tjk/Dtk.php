<?php
namespace ZhiCms\ext\Tjk;

class Dtk {
    
    protected $appKey;
    protected $appSecret;
    
    public function __construct($appKey, $appSecret) {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
    }
    
    protected function makeSign($data) {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            $str .= '&' . $k . '=' . $v;
        }
        $str = trim($str, '&');
        return strtoupper(md5($str . '&key=' . $this->appSecret));
    }
    
    protected function request($host, $params, $type = 'GET', $apiVersion = null) {
        $data = [
            'appKey' => $this->appKey,
            'version' => $apiVersion ?? 'v1.0.0',
        ];
        $data = array_merge($params, $data);
        $data['sign'] = $this->makeSign($data);
        
        try {
            $ch = curl_init();
            if ($type == 'POST') {
                curl_setopt($ch, CURLOPT_URL, $host);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            } else {
                curl_setopt($ch, CURLOPT_URL, $host . '?' . http_build_query($data));
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // 连接超时（DNS/握手阶段）必须单独限制，否则在部分网络环境下
            // CURLOPT_TIMEOUT 对连接阶段不生效，会导致 PHP 进程被单个转链请求永久挂起
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $output = curl_exec($ch);
            if (curl_error($ch)) {
                return ['code' => -1, 'msg' => curl_error($ch)];
            }
            curl_close($ch);
            return json_decode($output, true);
        } catch (\Exception $e) {
            return ['code' => -1, 'msg' => $e->getMessage()];
        }
    }

    public function pullGoods($action = 'pull', $pageSize = 10, $pageId = '1') {
        return $this->PullGoodsByTime($pageSize, $pageId);
    }
    
    public function getGoodsDetail($goodsId) {
        return $this->GetGoodsDetails($goodsId);
    }

    /**
     * 大淘客关键词搜索
     * 注意：官方 pageSize 仅支持 10/50/100。若调用方传入其它值（如 20），
     * 这里会向上取到最近的合法档位请求，再按调用方的 pageSize 做本地切片，
     * 保证「每页条数」与分页器一致，避免翻页错乱/重复。
     *
     * @param string $keyword  关键词
     * @param int    $pageNum  页码（调用方口径）
     * @param int    $pageSize 每页条数（调用方口径）
     * @param string $pmin     价格下限
     * @param string $pmax     价格上限
     * @param string $sort     排序：0综合 1价格低到高 2价格高到低 3销量低到高 4销量高到低 ...
     */
    public function SearchGoods($keyword, $pageNum = 1, $pageSize = 100, $pmin = '', $pmax = '', $sort = '') {
        $host = 'https://openapi.dataoke.com/api/goods/get-dtk-search-goods';

        $wantSize = max(1, intval($pageSize));
        $wantPage = max(1, intval($pageNum));

        // 选择合法的 API 档位（10/50/100），并换算成 API 页码 + 本地偏移
        $apiSize = 100;
        foreach ([10, 50, 100] as $allow) {
            if ($wantSize <= $allow) { $apiSize = $allow; break; }
        }
        $needSlice = ($apiSize != $wantSize);
        $offset = 0;
        $apiPage = $wantPage;
        if ($needSlice) {
            $startIdx = ($wantPage - 1) * $wantSize;      // 全局起始下标
            $apiPage  = intval(floor($startIdx / $apiSize)) + 1;
            $offset   = $startIdx % $apiSize;
        }

        $params = [
            'pageId' => $apiPage,
            'pageSize' => $apiSize,
        ];
        
        if (!empty($keyword)) {
            $params['keyWords'] = $keyword;
        }
        if ($pmin !== '' && is_numeric($pmin)) {
            $params['priceLowerLimit'] = intval($pmin);
        }
        if ($pmax !== '' && is_numeric($pmax)) {
            $params['priceUpperLimit'] = intval($pmax);
        }
        if ($sort !== '' && $sort !== null) {
            $params['sort'] = $sort;
        }
        
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败'];
        }
        
        $items = [];
        
        if (!isset($result['data']['list']) || !is_array($result['data']['list']) || count($result['data']['list']) == 0) {
            return [
                'code' => 0,
                'message' => '未找到相关商品',
                'total' => 0,
                'pageId' => '',
                'items' => [],
            ];
        }
foreach ($result['data']['list'] as $item) {
            $items[] = [
                'id' => $item['id'] ?? '',
                'goodsId' => $item['goodsId'] ?? '',
                'goodsSign' => $item['goodsSign'] ?? '',
                'title' => $item['title'] ?? '',
                'dtitle' => $item['dtitle'] ?? '',
                'originalPrice' => $item['originalPrice'] ?? 0,
                'actualPrice' => $item['actualPrice'] ?? 0,
                'shopType' => $item['shopType'] ?? 0,
                'monthSales' => $item['monthSales'] ?? 0,
                'twoHoursSales' => $item['twoHoursSales'] ?? 0,
                'dailySales' => $item['dailySales'] ?? 0,
                'commissionType' => $item['commissionType'] ?? 0,
                'desc' => $item['desc'] ?? '',
                'couponReceiveNum' => $item['couponReceiveNum'] ?? 0,
                'couponLink' => $item['couponLink'] ?? '',
                'couponEndTime' => $item['couponEndTime'] ?? '',
                'couponStartTime' => $item['couponStartTime'] ?? '',
                'couponPrice' => $item['couponPrice'] ?? 0,
                'couponConditions' => $item['couponConditions'] ?? '',
                'activityType' => $item['activityType'] ?? 0,
                'createTime' => $item['createTime'] ?? '',
                'mainPic' => $item['mainPic'] ?? '',
                'marketingMainPic' => $item['marketingMainPic'] ?? '',
                'sellerId' => $item['sellerId'] ?? '',
                'cid' => $item['cid'] ?? 0,
                'subcid' => $item['subcid'] ?? [],
                'tbcid' => $item['tbcid'] ?? 0,
                'discounts' => $item['discounts'] ?? 0,
                'commissionRate' => $item['commissionRate'] ?? 0,
                'couponTotalNum' => $item['couponTotalNum'] ?? 0,
                'activityStartTime' => $item['activityStartTime'] ?? '',
                'activityEndTime' => $item['activityEndTime'] ?? '',
                'shopName' => $item['shopName'] ?? '',
                'shopLevel' => $item['shopLevel'] ?? 0,
                'descScore' => $item['descScore'] ?? 0,
                'dsrScore' => $item['dsrScore'] ?? 0,
                'dsrPercent' => $item['dsrPercent'] ?? 0,
                'shipScore' => $item['shipScore'] ?? 0,
                'shipPercent' => $item['shipPercent'] ?? 0,
                'serviceScore' => $item['serviceScore'] ?? 0,
                'servicePercent' => $item['servicePercent'] ?? 0,
                'brand' => $item['brand'] ?? 0,
                'brandId' => $item['brandId'] ?? 0,
                'brandName' => $item['brandName'] ?? '',
                'hotPush' => $item['hotPush'] ?? 0,
                'teamName' => $item['teamName'] ?? '',
                'itemLink' => $item['itemLink'] ?? '',
                'quanMLink' => $item['quanMLink'] ?? 0,
                'hzQuanOver' => $item['hzQuanOver'] ?? 0,
                'yunfeixian' => $item['yunfeixian'] ?? 0,
                'estimateAmount' => $item['estimateAmount'] ?? 0,
                'freeshipRemoteDistrict' => $item['freeshipRemoteDistrict'] ?? 0,
                'brandList' => $item['brandList'] ?? [],
                'discountType' => $item['discountType'] ?? 0,
                'discountFull' => $item['discountFull'] ?? 0,
                'discountCut' => $item['discountCut'] ?? 0,
                'marketGroup' => $item['marketGroup'] ?? [],
                'activityInfo' => $item['activityInfo'] ?? [],
                'activityName' => $item['activityName'] ?? '',
                'activityId' => $item['activityId'] ?? 0,
                'inspectedGoods' => $item['inspectedGoods'] ?? 0,
                'shopLogo' => $item['shopLogo'] ?? '',
                'goldSellers' => $item['goldSellers'] ?? 0,
                'haitao' => $item['haitao'] ?? 0,
                'tchaoshi' => $item['tchaoshi'] ?? 0,
                'detailPics' => $item['detailPics'] ?? '',
                'item_from' => 'taobao',
            ];
        }
        
        $items = array_map(function($it){ return \ZhiCms\ext\Tjk::standardizeItem($it, 'taobao'); }, $items);

        // pageSize 非法档位时，按调用方口径做本地切片，保证每页条数与分页器一致
        if ($needSlice) {
            $items = array_slice($items, $offset, $wantSize);
        }

        return [
            'code' => 1,
            'message' => 'success',
            'total' => $result['data']['totalNum'] ?? 0,
            'pageId' => $result['data']['pageId'] ?? '',
            'items' => $items,
        ];
    }
    
    public function GetGoodsDetails($goodsId) {
        $host = 'https://openapi.dataoke.com/api/goods/get-goods-details';
        $params = ['goodsId' => $goodsId];
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败'];
        }
        if (empty($result['data'])) {
            return ['code' => 0, 'message' => $result['msg'] ?? '商品数据为空'];
        }

        // 详情接口返回里没有 goodsSign 字段，需从 data 中取（dataoke 详情接口字段名为 goodsSign 或 id）
        $detail = $result['data'];
        $detail['goodsSign'] = $detail['goodsSign'] ?? ($detail['id'] ?? '');
        $d = \ZhiCms\ext\Tjk::standardizeItem($detail, 'taobao');
        return [
            'code' => 1,
            'message' => 'success',
            'data' => $d,
        ];
    }
    
    public function ParseContent($content) {
        $host = 'https://openapi.dataoke.com/api/tb-service/parse-content';
        $params = ['content' => $content];
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '解析失败'];
        }
        
        $data = $result['data'];
        $originInfo = $data['originInfo'] ?? [];
        
        return [
            'code' => 1,
            'message' => 'success',
            'data' => [
                'commissionRate' => $data['commissionRate'] ?? 0,
                'commissionType' => $data['commissionType'] ?? '',
                'goodsId' => $data['goodsId'] ?? '',
                'originType' => $data['originType'] ?? '',
                'originUrl' => $data['originUrl'] ?? '',
                'activityId' => $originInfo['activityId'] ?? '',
                'amount' => $originInfo['amount'] ?? 0,
                'endTime' => $originInfo['endTime'] ?? '',
                'image' => $originInfo['image'] ?? '',
                'pid' => $originInfo['pid'] ?? '',
                'price' => $originInfo['price'] ?? 0,
                'shopLogo' => $originInfo['shopLogo'] ?? '',
                'shopName' => $originInfo['shopName'] ?? '',
                'startFee' => $originInfo['startFee'] ?? 0,
                'startTime' => $originInfo['startTime'] ?? '',
                'status' => $originInfo['status'] ?? 0,
                'title' => $originInfo['title'] ?? '',
            ],
        ];
    }
    
    /**
     * 大淘客朋友圈商品列表（friends-circle-list）
     * 文档：https://www.dataoke.com/pmc/api-d.html?id=25
     * 排序 sort：0综合 1上架时间 2热销 3领券量 4佣金比例 5券后价高到低 6券后价低到高
     * cid：大淘客一级/二级分类id，需与网站现有分类映射一致（由调用方传）
     * @param string $pageId   分页id（首页传 ''）
     * @param int    $pageSize 每页条数
     * @param int    $sort     排序方式
     * @param int    $cid      分类id（0=全部）
     * @return array
     */
    public function FriendsCircleList($pageId = '', $pageSize = 50, $sort = 0, $cid = 0) {
        $host = 'https://openapi.dataoke.com/api/goods/friends-circle-list';
        $params = [
            'version'  => '1.3.0',
            'pageId'   => (string)$pageId,
            'pageSize' => (int)$pageSize,
            'sort'     => (int)$sort,
        ];
        if (!empty($cid)) {
            $params['cid'] = (int)$cid;
        }
        $result = $this->request($host, $params, '1.3.0');

        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '请求失败'];
        }
        if (empty($result['data']['list']) || !is_array($result['data']['list'])) {
            return [
                'code'    => 0,
                'message' => '未找到相关商品',
                'total'   => 0,
                'pageId'  => '',
                'items'   => [],
            ];
        }

        $items = [];
        foreach ($result['data']['list'] as $item) {
            $it = [
                'id'             => $item['id'] ?? '',
                'goodsId'        => $item['goodsId'] ?? '',
                'goodsSign'      => $item['goodsSign'] ?? '',
                'title'          => $item['title'] ?? '',
                'dtitle'         => $item['dtitle'] ?? '',
                'originalPrice'  => $item['originalPrice'] ?? 0,
                'actualPrice'    => $item['actualPrice'] ?? 0,
                'shopType'       => $item['shopType'] ?? 0,
                'monthSales'     => $item['monthSales'] ?? 0,
                'commissionRate' => $item['commissionRate'] ?? 0,
                'couponPrice'    => $item['couponPrice'] ?? 0,
                'couponLink'     => $item['couponLink'] ?? '',
                'couponStartTime'=> $item['couponStartTime'] ?? '',
                'couponEndTime'  => $item['couponEndTime'] ?? '',
                'mainPic'        => $item['mainPic'] ?? '',
                'marketingMainPic'=> $item['marketingMainPic'] ?? '',
                'cid'            => $item['cid'] ?? 0,
                'subcid'        => $item['subcid'] ?? [],
                'itemLink'       => $item['itemLink'] ?? '',
                // 朋友圈专属字段
                'circleText'     => $item['circleText'] ?? '',
                'picList'        => $item['picList'] ?? [],
                'item_from'      => 'taobao',
            ];
            // 注意：朋友圈素材含专属字段 circleText/picList/item_from，
            // 不能用 standardizeItem（其 $def 不含这些字段会被丢弃），直接返回映射数组
            $items[] = $it;
        }

        return [
            'code'   => 1,
            'message'=> 'success',
            'total'  => $result['data']['totalNum'] ?? 0,
            'pageId' => $result['data']['pageId'] ?? '',
            'items'  => $items,
        ];
    }

    public function TwdToTwd($content) {
        $host = 'https://openapi.dataoke.com/api/tb-service/twd-to-twd';
        $params = ['content' => $content];
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '解析失败'];
        }
        
        $data = $result['data'];
        return [
            'code' => 1,
            'message' => 'success',
            'data' => [
                'couponClickUrl' => $data['couponClickUrl'] ?? '',
                'couponEndTime' => $data['couponEndTime'] ?? '',
                'couponInfo' => $data['couponInfo'] ?? '',
                'couponStartTime' => $data['couponStartTime'] ?? '',
                'itemId' => $data['itemId'] ?? '',
                'couponTotalCount' => $data['couponTotalCount'] ?? '',
                'couponRemainCount' => $data['couponRemainCount'] ?? '',
                'originUrl' => $data['originUrl'] ?? '',
                'tpwd' => $data['tpwd'] ?? '',
                'maxCommissionRate' => $data['maxCommissionRate'] ?? '',
                'shortUrl' => $data['shortUrl'] ?? '',
                'minCommissionRate' => $data['minCommissionRate'] ?? '',
                'title' => $data['title'] ?? '',
                'goodsId' => $data['itemId'] ?? '',
                'commissionRate' => $data['maxCommissionRate'] ?? 0,
            ],
        ];
    }
    
    public function GetPrivilegeLink($goodsId, $pid = '', $goodsSign = '', $itemUrl = '', $version = 'v1.3.1') {
        $host = 'https://openapi.dataoke.com/api/tb-service/get-privilege-link';
        // 高效转链（get-privilege-link）支持 goodsId 或 goodsSign 二选一。
        // 全链路以 goodsSign 作为淘宝(大淘客)产品 id 为准，故转链时优先使用 goodsSign，
        // 仅当 goodsSign 缺失时回退用 goodsId（如历史数据或好单库商品）。
        $realGoodsId = $goodsSign ?: $goodsId;
        if (empty($realGoodsId)) {
            return ['code' => 0, 'message' => '缺少商品ID'];
        }
        $params = ['goodsId' => $realGoodsId];
        if (!empty($pid)) {
            $params['pid'] = $pid;
        }
        // get-privilege-link（高效转链）version 默认 v1.3.1，允许外部覆盖用于探测
        $result = $this->request($host, $params, 'GET', $version);

        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '转链失败'];
        }

        $data = $result['data'];
        $tpwd = $data['tpwd'] ?? '';
        $longTpwd = $data['longTpwd'] ?? '';

        // 兜底/生成口令所用的链接：优先淘客短链 shortUrl（即使无优惠券也始终可用），
        // 其次领券二合一链接、商品链接
        $genUrl = $data['shortUrl'] ?: ($data['couponClickUrl'] ?: ($data['itemUrl'] ?: $itemUrl));

        // 标准口令为空：用 creat-taokouling 以链接兜底生成（同时得到 tpwd 与 longTpwd）
        // 标准口令已有但缺少长口令（iOS 需要）：同样用链接补生成 longTpwd
        if (!empty($genUrl) && (empty($tpwd) || empty($longTpwd))) {
            $gen = $this->CreateTpwd($genUrl, $data['title'] ?? '');
            if ($gen['code'] == 1) {
                if (empty($tpwd)) {
                    $tpwd = $gen['data']['tpwd'] ?? '';
                }
                if (empty($longTpwd)) {
                    $longTpwd = $gen['data']['longTpwd'] ?? $gen['data']['tpwd'] ?? '';
                }
            }
        }

        $itemLink   = $data['itemUrl'] ?: $itemUrl;

        // 主推广链接：优先 couponClickUrl（领券二合一），不存在则回退 shortUrl（淘客短链）
        $couponUrl  = $data['couponClickUrl'] ?: ($data['couponLink'] ?? '');
        $shortUrl   = $data['shortUrl'] ?? '';
        $mainUrl    = $couponUrl ?: $shortUrl;

        return [
            'code' => 1,
            'message' => 'success',
            'data' => [
                // —— 四种按需调用参数 ——
                // 1) 主链接：领券二合一优先，否则淘客短链
                'couponClickUrl' => $couponUrl,   // 优先
                'shortUrl' => $shortUrl,          // 不存在 couponClickUrl 时回退
                'url' => $mainUrl,
                // 2) 移动端（App/小程序）淘口令
                'tpwd' => $tpwd,
                'tkl' => $tpwd,
                // 3) 苹果手机专用长口令（iOS 需 longTpwd）
                'longTpwd' => $longTpwd,
                // 4) 商品原链接（兜底）
                'itemUrl' => $itemLink,
                'taokeLink' => $itemLink,
                // —— 其余兼容字段 ——
                'couponEndTime' => $data['couponEndTime'] ?? '',
                'couponInfo' => $data['couponInfo'] ?? '',
                'couponStartTime' => $data['couponStartTime'] ?? '',
                'itemId' => $data['itemId'] ?? '',
                'couponTotalCount' => $data['couponTotalCount'] ?? '',
                'couponRemainCount' => $data['couponRemainCount'] ?? '',
                'maxCommissionRate' => $data['maxCommissionRate'] ?? '',
                'minCommissionRate' => $data['minCommissionRate'] ?? '',
            ],
        ];
    }

    /**
     * 生成淘口令（兜底方案）
     * 以大淘客 create-tpwd 接口，针对「领券/推广链接」生成可读的淘口令字符串
     * @param string $url  商品或优惠券链接
     * @param string $text 推广文案（展示在淘口令前后）
     * @param string $logo 可选 logo 图
     * @return array
     */
    public function CreateTpwd($url, $text = '', $logo = '') {
        if (empty($url)) {
            return ['code' => 0, 'message' => '生成淘口令缺少链接'];
        }
        // 大淘客淘口令生成接口（由商品/领券链接生成淘口令），version 固定 v1.0.0
        $host = 'https://openapi.dataoke.com/api/tb-service/creat-taokouling';
        $params = ['url' => $url];
        if (!empty($text)) {
            $params['text'] = mb_substr($text, 0, 20, 'UTF-8');
        }
        if (!empty($logo)) {
            $params['logo'] = $logo;
        }

        $result = $this->request($host, $params, 'GET', 'v1.0.0');
        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '淘口令生成失败'];
        }

        $data = $result['data'] ?? [];
        // 兼容不同返回字段：data.tpwd / data.model
        $tpwd = $data['tpwd'] ?? ($data['model'] ?? '');
        if (empty($tpwd)) {
            return ['code' => 0, 'message' => '淘口令内容为空'];
        }

        return [
            'code' => 1,
            'message' => 'success',
            'data' => [
                'tpwd' => $tpwd,
                'longTpwd' => $data['longTpwd'] ?? $tpwd,
            ],
        ];
    }
    
    public function GetOrderDetails($startTime, $endTime) {
        $host = 'https://openapi.dataoke.com/api/tb-service/get-order-details';
        $params = [
            'startTime' => $startTime,
            'endTime' => $endTime,
        ];
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '查询失败'];
        }
        
        $orders = [];
        foreach ($result['data']['orderList'] as $order) {
            $orders[] = [
                'orderId' => $order['orderId'],
                'goodsId' => $order['goodsId'],
                'goodsName' => $order['goodsName'],
                'goodsPrice' => $order['goodsPrice'],
                'commission' => $order['commission'],
                'orderStatus' => $order['orderStatus'],
            ];
        }
        
        return [
            'code' => 1,
            'message' => 'success',
            'total' => $result['data']['total'] ?? 0,
            'orders' => $orders,
        ];
    }
    
    public function PullGoodsByTime($pageSize = 10, $pageId = '1', $cid = '', $subcid = '', $pre = '', $sort = '', $startTime = '', $endTime = '', $freeshipRemoteDistrict = '', $choice = '', $hasCoupon = '') {
        $validSizes = [10, 50, 100, 200];
        if (!in_array($pageSize, $validSizes)) {
            $pageSize = 10;
        }
        
        $host = 'https://openapi.dataoke.com/api/goods/pull-goods-by-time';
        $params = [
            'pageSize' => $pageSize,
            'pageId' => $pageId,
        ];
        
        if (!empty($cid)) $params['cid'] = $cid;
        if (!empty($subcid)) $params['subcid'] = $subcid;
        if ($pre !== '') $params['pre'] = $pre;
        if (!empty($sort)) $params['sort'] = $sort;
        if (!empty($startTime)) $params['startTime'] = $startTime;
        if (!empty($endTime)) $params['endTime'] = $endTime;
        if ($freeshipRemoteDistrict !== '') $params['freeshipRemoteDistrict'] = $freeshipRemoteDistrict;
        if ($choice !== '') $params['choice'] = $choice;
        if ($hasCoupon !== '') $params['hasCoupon'] = $hasCoupon;
        
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '拉取失败'];
        }
        
        $items = [];
        if (!isset($result['data']['list']) || !is_array($result['data']['list'])) {
            return [
                'code' => 0,
                'message' => '数据格式错误',
                'total' => 0,
                'pageId' => '',
                'items' => [],
            ];
        }
        foreach ($result['data']['list'] as $item) {
            $items[] = [
                'id' => $item['id'] ?? '',
                'goodsId' => $item['goodsId'] ?? '',
                'goodsSign' => $item['goodsSign'] ?? '',
                'title' => $item['title'] ?? '',
                'dtitle' => $item['dtitle'] ?? '',
                'originalPrice' => $item['originalPrice'] ?? 0,
                'actualPrice' => $item['actualPrice'] ?? 0,
                'shopType' => $item['shopType'] ?? 0,
                'goldSellers' => $item['goldSellers'] ?? 0,
                'monthSales' => $item['monthSales'] ?? 0,
                'twoHoursSales' => $item['twoHoursSales'] ?? 0,
                'dailySales' => $item['dailySales'] ?? 0,
                'commissionType' => $item['commissionType'] ?? 0,
                'desc' => $item['desc'] ?? '',
                'couponReceiveNum' => $item['couponReceiveNum'] ?? 0,
                'couponLink' => $item['couponLink'] ?? '',
                'couponEndTime' => $item['couponEndTime'] ?? '',
                'couponStartTime' => $item['couponStartTime'] ?? '',
                'couponPrice' => $item['couponPrice'] ?? 0,
                'couponConditions' => $item['couponConditions'] ?? '',
                'activityType' => $item['activityType'] ?? 0,
                'createTime' => $item['createTime'] ?? '',
                'mainPic' => $item['mainPic'] ?? '',
                'marketingMainPic' => $item['marketingMainPic'] ?? '',
                'sellerId' => $item['sellerId'] ?? '',
                'cid' => $item['cid'] ?? 0,
                'discounts' => $item['discounts'] ?? 0,
                'commissionRate' => $item['commissionRate'] ?? 0,
                'couponTotalNum' => $item['couponTotalNum'] ?? 0,
                'haitao' => $item['haitao'] ?? 0,
                'activityStartTime' => $item['activityStartTime'] ?? '',
                'activityEndTime' => $item['activityEndTime'] ?? '',
                'shopName' => $item['shopName'] ?? '',
                'shopLevel' => $item['shopLevel'] ?? 0,
                'descScore' => $item['descScore'] ?? 0,
                'brand' => $item['brand'] ?? 0,
                'brandId' => $item['brandId'] ?? 0,
                'brandName' => $item['brandName'] ?? '',
                'hotPush' => $item['hotPush'] ?? 0,
                'teamName' => $item['teamName'] ?? '',
                'itemLink' => $item['itemLink'] ?? '',
                'tchaoshi' => $item['tchaoshi'] ?? 0,
                'detailPics' => $item['detailPics'] ?? '',
                'dsrScore' => $item['dsrScore'] ?? 0,
                'dsrPercent' => $item['dsrPercent'] ?? 0,
                'shipScore' => $item['shipScore'] ?? 0,
                'shipPercent' => $item['shipPercent'] ?? 0,
                'serviceScore' => $item['serviceScore'] ?? 0,
                'servicePercent' => $item['servicePercent'] ?? 0,
                'subcid' => $item['subcid'] ?? '',
                'tbcid' => $item['tbcid'] ?? 0,
                'quanMLink' => $item['quanMLink'] ?? '',
                'hzQuanOver' => $item['hzQuanOver'] ?? 0,
                'yunfeixian' => $item['yunfeixian'] ?? 0,
                'estimateAmount' => $item['estimateAmount'] ?? 0,
                'shopLogo' => $item['shopLogo'] ?? '',
                'freeshipRemoteDistrict' => $item['freeshipRemoteDistrict'] ?? 0,
            ];
        }
        
        $items = array_map(function($it){ return \ZhiCms\ext\Tjk::standardizeItem($it, 'taobao'); }, $items);
        return [
            'code' => 1,
            'message' => 'success',
            'total' => $result['data']['totalNum'] ?? 0,
            'pageId' => $result['data']['pageId'] ?? '',
            'items' => $items,
        ];
    }
    
    public function GetNewestGoods($pageSize = 10, $pageId = '1') {
        $validSizes = [10, 50, 100, 200];
        if (!in_array($pageSize, $validSizes)) {
            $pageSize = 10;
        }
        
        $host = 'https://openapi.dataoke.com/api/goods/get-newest-goods';
        $params = [
            'pageSize' => $pageSize,
            'pageId' => $pageId,
        ];
        $result = $this->request($host, $params);
        
        if (!isset($result['code']) || $result['code'] != 0) {
            return ['code' => 0, 'message' => $result['msg'] ?? '获取失败'];
        }
        
        $items = [];
        foreach ($result['data']['list'] as $item) {
            $items[] = [
                'id' => $item['id'] ?? '',
                'goodsId' => $item['goodsId'] ?? '',
                'goodsSign' => $item['goodsSign'] ?? '',
                'originalPrice' => $item['originalPrice'] ?? 0,
                'actualPrice' => $item['actualPrice'] ?? 0,
                'couponPrice' => $item['couponPrice'] ?? 0,
                'discounts' => $item['discounts'] ?? 0,
                'commissionType' => $item['commissionType'] ?? 0,
                'commissionRate' => $item['commissionRate'] ?? 0,
                'monthSales' => $item['monthSales'] ?? 0,
                'hotPush' => $item['hotPush'] ?? 0,
                'subcid' => $item['subcid'] ?? [],
                'twoHoursSales' => $item['twoHoursSales'] ?? 0,
                'dailySales' => $item['dailySales'] ?? 0,
                'specialText' => $item['specialText'] ?? '',
                'couponRemainCount' => $item['couponRemainCount'] ?? 0,
                'couponReceiveNum' => $item['couponReceiveNum'] ?? 0,
                'couponLink' => $item['couponLink'] ?? '',
                'couponId' => $item['couponId'] ?? '',
                'inspectedGoods' => $item['inspectedGoods'] ?? 0,
            ];
        }
        
        $items = array_map(function($it){ return \ZhiCms\ext\Tjk::standardizeItem($it, 'taobao'); }, $items);
        return [
            'code' => 1,
            'message' => 'success',
            'total' => $result['data']['totalNum'] ?? 0,
            'pageId' => $result['data']['pageId'] ?? '',
            'items' => $items,
        ];
    }

    /**
     * 全量商品列表（get-goods-list）- 返回完整字段包括 title/mainPic/dtitle 等
     * 相比 pullGoodsByTime 定时拉取，该接口返回大淘客全库商品，覆盖面更广
     * @param int    $pageSize  每页数量
     * @param string $pageId    分页标识，首次传 "1"
     * @param array  $extra     额外筛选参数: cid, sort, priceLowerLimit, priceUpperLimit 等
     */
    public function GetGoodsList($pageSize = 50, $pageId = '1', $extra = []) {
        $validSizes = [10, 50, 100, 200];
        if (!in_array($pageSize, $validSizes)) {
            $pageSize = 50;
        }

        $host = 'https://openapi.dataoke.com/api/goods/get-goods-list';
        $params = [
            'pageSize' => $pageSize,
            'pageId'   => $pageId,
        ];

        // 映射筛选参数（sort 用 isset 避免 '0' 被 empty 误判为空）
        if (isset($extra['cid']) && $extra['cid'] !== '')  $params['cids'] = $extra['cid'];
        if (isset($extra['sort']) && $extra['sort'] !== '') $params['sort'] = $extra['sort'];
        if (isset($extra['priceLowerLimit']) && $extra['priceLowerLimit'] !== '') $params['priceLowerLimit'] = $extra['priceLowerLimit'];
        if (isset($extra['priceUpperLimit']) && $extra['priceUpperLimit'] !== '') $params['priceUpperLimit'] = $extra['priceUpperLimit'];

        // 该接口需要 v1.2.4 版本
        $result = $this->request($host, $params, 'GET', 'v1.2.4');

        // 兼容两种响应格式：
        //   格式A（标准DTK）: { code: 0, msg: "成功", data: { list, pageId, totalNum } }
        //   格式B（文档描述）: { status: 200, data: { code: 0, msg: "成功", data: { list, pageId, totalNum } } }
        $listData  = null;
        $errorMsg  = '请求失败';

        // 先尝试格式B（嵌套，有 status 顶层字段）
        if (isset($result['status']) && isset($result['data']['code'])) {
            if ($result['data']['code'] != 0) {
                $errorMsg = $result['data']['msg'] ?? 'API返回错误';
            } else {
                $listData = $result['data']['data'] ?? null;
            }
        }
        // 再尝试格式A（标准DTK，code 在顶层）
        elseif (isset($result['code'])) {
            if ($result['code'] != 0) {
                $errorMsg = $result['msg'] ?? 'API返回错误';
            } else {
                $listData = $result['data'] ?? null;
            }
        }
        // 完全未知格式
        else {
            $errorMsg = $result['msg'] ?? '未知响应格式';
        }

        if ($listData === null || !is_array($listData)) {
            return [
                'code'    => 0,
                'message' => $errorMsg,
                'total'   => 0,
                'pageId'  => '',
                'items'   => [],
            ];
        }

        $rawList = $listData['list'] ?? [];

        if (!is_array($rawList) || empty($rawList)) {
            return [
                'code'    => 0,
                'message' => 'API返回空列表',
                'total'   => 0,
                'pageId'  => '',
                'items'   => [],
            ];
        }

        // 构建商品条目
        $items = [];
        foreach ($rawList as $item) {
            $items[] = [
                'id'               => $item['id'] ?? '',
                'goodsId'          => $item['goodsId'] ?? '',
                'goodsSign'        => $item['goodsSign'] ?? '',
                'title'            => $item['title'] ?? '',
                'dtitle'           => $item['dtitle'] ?? '',
                'originalPrice'    => $item['originalPrice'] ?? 0,
                'actualPrice'      => $item['actualPrice'] ?? 0,
                'shopType'         => $item['shopType'] ?? 0,
                'goldSellers'      => $item['goldSellers'] ?? 0,
                'monthSales'       => $item['monthSales'] ?? 0,
                'twoHoursSales'    => $item['twoHoursSales'] ?? 0,
                'dailySales'       => $item['dailySales'] ?? 0,
                'commissionType'   => $item['commissionType'] ?? 0,
                'desc'             => $item['desc'] ?? '',
                'couponReceiveNum' => $item['couponReceiveNum'] ?? 0,
                'couponLink'       => $item['couponLink'] ?? '',
                'couponEndTime'    => $item['couponEndTime'] ?? '',
                'couponStartTime'  => $item['couponStartTime'] ?? '',
                'couponPrice'      => $item['couponPrice'] ?? 0,
                'couponConditions' => $item['couponConditions'] ?? '',
                'couponTotalNum'   => $item['couponTotalNum'] ?? 0,
                'activityType'     => $item['activityType'] ?? 0,
                'activityStartTime'=> $item['activityStartTime'] ?? '',
                'activityEndTime'  => $item['activityEndTime'] ?? '',
                'createTime'       => $item['createTime'] ?? '',
                'mainPic'          => $item['mainPic'] ?? '',
                'marketingMainPic' => $item['marketingMainPic'] ?? '',
                'sellerId'         => $item['sellerId'] ?? '',
                'cid'              => $item['cid'] ?? 0,
                'subcid'           => $item['subcid'] ?? [],
                'tbcid'            => $item['tbcid'] ?? 0,
                'discounts'        => $item['discounts'] ?? 0,
                'commissionRate'   => $item['commissionRate'] ?? 0,
                'haitao'           => $item['haitao'] ?? 0,
                'shopName'         => $item['shopName'] ?? '',
                'shopLevel'        => $item['shopLevel'] ?? 0,
                'brand'            => $item['brand'] ?? 0,
                'brandId'          => $item['brandId'] ?? 0,
                'brandName'        => $item['brandName'] ?? '',
                'hotPush'          => $item['hotPush'] ?? 0,
                'teamName'         => $item['teamName'] ?? '',
                'itemLink'         => $item['itemLink'] ?? '',
                'tchaoshi'         => $item['tchaoshi'] ?? 0,
                'detailPics'       => $item['detailPics'] ?? '',
                'dsrScore'         => $item['dsrScore'] ?? 0,
                'dsrPercent'       => $item['dsrPercent'] ?? 0,
                'shipScore'        => $item['shipScore'] ?? 0,
                'shipPercent'      => $item['shipPercent'] ?? 0,
                'serviceScore'     => $item['serviceScore'] ?? 0,
                'servicePercent'   => $item['servicePercent'] ?? 0,
                'quanMLink'        => $item['quanMLink'] ?? 0,
                'hzQuanOver'       => $item['hzQuanOver'] ?? 0,
                'yunfeixian'       => $item['yunfeixian'] ?? 0,
                'estimateAmount'   => $item['estimateAmount'] ?? 0,
                'shopLogo'         => $item['shopLogo'] ?? '',
                'specialText'      => $item['specialText'] ?? '',
                'freeshipRemoteDistrict' => $item['freeshipRemoteDistrict'] ?? 0,
                'brandWenan'       => $item['brandWenan'] ?? '',
            ];
        }

        $items = array_map(function($it){ return \ZhiCms\ext\Tjk::standardizeItem($it, 'taobao'); }, $items);

        return [
            'code'    => 1,
            'message' => 'success',
            'total'   => $listData['totalNum'] ?? 0,
            'pageId'  => $listData['pageId'] ?? '',
            'items'   => $items,
        ];
    }

    /**
     * 品牌栏目（brand/get-column-list）- 大淘客联盟 id=44
     * 返回各分类下收录的品牌列表；可传 cid 按分类筛选。
     * 文档：https://www.dataoke.com/pmc/api-d.html?id=44
     *
     * @param int    $pageSize 每页数量（10/50/100/200）
     * @param string $pageId   页码ID，首次传 "1"
     * @param string $cid      分类ID（可选）
     * @return array ['code','message','total','pageId','brands']
     */
    public function GetBrandColumnList($pageSize = 50, $pageId = '1', $cid = '') {
        $validSizes = [10, 50, 100, 200];
        if (!in_array($pageSize, $validSizes)) {
            $pageSize = 50;
        }

        $host = 'https://openapi.dataoke.com/api/delanys/brand/get-column-list';
        $params = [
            'pageId'   => (string) $pageId,
            'pageSize' => $pageSize,
        ];
        if ($cid !== '' && $cid !== null) {
            $params['cid'] = $cid;
        }

        $result = $this->request($host, $params);

        if (!isset($result['code']) || $result['code'] != 0) {
            return [
                'code'    => 0,
                'message' => $result['msg'] ?? '请求失败',
                'total'   => 0,
                'pageId'  => '',
                'brands'  => [],
            ];
        }

        $data  = $result['data'] ?? [];
        $lists = $data['lists'] ?? [];
        if (!is_array($lists)) {
            $lists = [];
        }

        $brands = [];
        foreach ($lists as $b) {
            $brands[] = [
                'brandId'          => $b['brandId'] ?? 0,
                'brandName'        => $b['brandName'] ?? '',
                'brandLogo'        => $b['brandLogo'] ?? '',
                'brandFeatures'    => $b['brandFeatures'] ?? '',
                'brandDesc'        => $b['brandDesc'] ?? '',
                'sales'            => $b['sales'] ?? 0,
                'maxDiscountAmount'=> $b['maxDiscountAmount'] ?? 0,
                'maxDiscount'      => $b['maxDiscount'] ?? 0,
                'commissionRate'   => $b['commissionRate'] ?? 0,
                'goodsList'        => $b['goodsList'] ?? [],
            ];
        }

        return [
            'code'    => 1,
            'message' => 'success',
            'total'   => $data['totalCount'] ?? 0,
            'pageId'  => $data['currentPage'] ?? (string) $pageId,
            'brands'  => $brands,
        ];
    }

    /**
     * 单个品牌详情（brand/get-goods-list）- 大淘客开放平台 id=45
     * 传入品牌ID，返回该品牌下的商品列表与品牌信息。
     * 文档：https://www.dataoke.com/kfpt/api-d.html?id=45
     *
     * @param string|int $brandId  品牌ID（必填）
     * @param int        $pageSize 每页数量（10/50/100/200）
     * @param string     $pageId   页码ID，首次传 "1"
     * @return array ['code','message','total','pageId','goods','brandInfo']
     */
    public function GetBrandGoodsList($brandId, $pageSize = 50, $pageId = '1') {
        $validSizes = [10, 50, 100, 200];
        if (!in_array($pageSize, $validSizes)) {
            $pageSize = 50;
        }

        $host = 'https://openapi.dataoke.com/api/delanys/brand/get-goods-list';
        $params = [
            'brandId'  => (string) $brandId,
            'pageId'   => (string) $pageId,
            'pageSize' => $pageSize,
        ];

        $result = $this->request($host, $params);

        if (!isset($result['code']) || $result['code'] != 0) {
            return [
                'code'      => 0,
                'message'   => $result['msg'] ?? '请求失败',
                'total'     => 0,
                'pageId'    => '',
                'goods'     => [],
                'brandInfo' => [],
            ];
        }

        $data    = $result['data'] ?? [];
        $rawList = $data['lists'] ?? [];
        if (!is_array($rawList)) {
            $rawList = [];
        }

        $goods = [];
        foreach ($rawList as $item) {
            $goods[] = \ZhiCms\ext\Tjk::standardizeItem($item, 'taobao');
        }

        $brandInfo = [
            'brandId'          => $data['brandId'] ?? $brandId,
            'brandName'        => $data['brandName'] ?? '',
            'brandLogo'        => $data['brandLogo'] ?? '',
            'brandDesc'        => $data['brandDesc'] ?? '',
            'brandFeatures'    => $data['brandFeatures'] ?? '',
            'sales'            => $data['sales'] ?? 0,
            'fansNum'          => $data['fansNum'] ?? 0,
            'maxDiscountAmount'=> 0,
        ];

        return [
            'code'      => 1,
            'message'   => 'success',
            'total'     => $data['totalCount'] ?? 0,
            'pageId'    => $data['currentPage'] ?? (string) $pageId,
            'goods'     => $goods,
            'brandInfo' => $brandInfo,
        ];
    }

    /**
     * 各大榜单（goods/get-ranking-list）- 大淘客开放平台 id=6
     * 文档：https://www.dataoke.com/kfpt/api-d.html?id=6
     *
     * rankType 取值（商品榜，返回 items）：
     *   1 实时榜  2 全天热销榜  3 热推榜  7 综合热搜榜
     *
     * @param int    $rankType 榜单类型（必填）
     * @param string $cid      商品类目ID（可选）
     * @param int    $pageSize 每页数量（可选）
     * @param string $pageId   分页ID，首次传 "1"
     * @return array ['code','message','rankType','items','keywords']
     */
    public function GetRankingList($rankType = 1, $cid = '', $pageSize = 100, $pageId = '1') {
        $rankType = (int) $rankType;

        $host = 'https://openapi.dataoke.com/api/goods/get-ranking-list';
        $params = [
            'rankType' => $rankType,
        ];
        if ($cid !== '' && $cid !== null)       $params['cid']      = $cid;
        if (!empty($pageSize))                  $params['pageSize'] = $pageSize;
        if ($pageId !== '' && $pageId !== null) $params['pageId']   = (string) $pageId;

        // 该接口使用 v1.3.0 版本
        $result = $this->request($host, $params, 'GET', 'v1.3.0');

        if (!isset($result['code']) || $result['code'] != 0) {
            return [
                'code'     => 0,
                'message'  => $result['msg'] ?? '请求失败',
                'rankType' => $rankType,
                'items'    => [],
                'keywords' => [],
            ];
        }

        $data = $result['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        // 商品榜（1 实时榜 / 2 全天热销榜 / 3 热推榜 / 7 综合热搜榜）：
        // 归一化商品字段，并补充榜单专有字段
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $std = \ZhiCms\ext\Tjk::standardizeItem($row, 'taobao');
            $std['ranking']   = $row['ranking'] ?? 0;   // 榜单排名
            $std['searchNum'] = $row['searchNum'] ?? 0; // 综合热搜榜：搜索数
            $std['keyWord']   = $row['keyWord'] ?? '';  // 综合热搜榜：关键词
            $items[] = $std;
        }

        return [
            'code'     => 1,
            'message'  => 'success',
            'rankType' => $rankType,
            'items'    => $items,
            'keywords' => [],
        ];
    }

    /**
     * 线报（dels/spider/list-tip-off）- 大淘客联盟 id=62
     * 返回全网（淘宝/京东/天猫）最新的商品优惠线报（口令/文案/图片），
     * 实时更新，可用于社群、自动发单等场景。
     * 文档：https://www.dataoke.com/kfpt/api-d.html?id=62
     *
     * @param string $pageId   分页ID，首次传 "1"
     * @param int    $pageSize 每页数量（建议 20）
     * @param string $topic    线报主题类型（可选，留空返回全部）
     * @param int    $platform 平台筛选：0-淘客（默认），1-京东。留空/不传则默认 0
     * @param string $version  接口版本，默认 v4.0.0
     * @return array ['code','message','list','total','pageId']
     */
    public function GetTipOff($pageId = '1', $pageSize = 20, $topic = '', $platform = 0, $version = 'v4.0.0') {
        $host = 'https://openapi.dataoke.com/api/dels/spider/list-tip-off';
        $params = [];
        if (!empty($pageId))                   $params['pageId']   = (string) $pageId;
        if (!empty($pageSize))                 $params['pageSize'] = $pageSize;
        if ($topic !== '' && $topic !== null)  $params['topic']    = $topic;
        if ($platform !== '' && $platform !== null) $params['platform'] = (int) $platform;

        $result = $this->request($host, $params, 'GET', $version);

        if (!isset($result['code']) || $result['code'] != 0) {
            return [
                'code'    => 0,
                'message' => $result['msg'] ?? '请求失败',
                'list'    => [],
                'total'   => 0,
                'pageId'  => '',
            ];
        }

        $data = $result['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        $rawList = $data['list'] ?? [];
        if (!is_array($rawList)) {
            $rawList = [];
        }

        $list = [];
        foreach ($rawList as $row) {
            if (!is_array($row)) {
                continue;
            }
            // picUrls 可能是逗号分隔的多图，取第一张
            $pic = $row['picUrls'] ?? '';
            if (is_string($pic) && strpos($pic, ',') !== false) {
                $pic = explode(',', $pic)[0];
            }
            $list[] = [
                'id'            => $row['itemIds'] ?? '',
                'content'       => $row['content'] ?? '',
                'contentCopy'   => $row['contentCopy'] ?? '',
                'pic'           => $pic,
                'type'          => $row['type'] ?? 0,
                'platform'      => $row['platform'] ?? '',
                'recommendDesc' => $row['recommendDesc'] ?? '',
                'isRecommend'   => $row['isRecommend'] ?? 0,
                'homeRecommend' => $row['homeRecommend'] ?? 0,
                'createTime'    => $row['createTime'] ?? '',
                'updateTime'    => $row['updateTime'] ?? '',
            ];
        }

        return [
            'code'    => 1,
            'message' => 'success',
            'list'    => $list,
            'total'   => $data['totalNum'] ?? 0,
            'pageId'  => $data['pageId'] ?? (string) $pageId,
        ];
    }
}