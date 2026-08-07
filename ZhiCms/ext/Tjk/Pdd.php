<?php
namespace ZhiCms\ext\Tjk;

/**
 * 拼多多多多进宝官方 SDK（独立实现，不依赖好单库）
 * 文档：https://jinbao.pinduoduo.com/
 * 搜索：pdd.ddk.goods.search  -> 返回 goods_sign（产品ID）
 * 转链：pdd.ddk.goods.promotion.url.generate -> goods_sign + p_id 生成推广短链
 */
class Pdd
{
    protected $clientId;
    protected $clientSecret;
    protected $pid;
    protected $gateway = 'https://gw-api.pinduoduo.com/api/router';

    public function __construct($clientId = '', $clientSecret = '', $pid = '')
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->pid = $pid;
    }

    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * 拼多多签名：md5(client_secret + 排序拼接(k+v) + client_secret) 大写
     */
    protected function makeSign(array $params)
    {
        ksort($params);
        $str = $this->clientSecret;
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $str .= $k . $v;
        }
        $str .= $this->clientSecret;
        return strtoupper(md5($str));
    }

    protected function request($type, array $params)
    {
        $params['type'] = $type;
        $params['client_id'] = $this->clientId;
        $params['timestamp'] = strval(time() * 1000);
        if (!isset($params['data_type'])) {
            $params['data_type'] = 'JSON';
        }
        $params['sign'] = $this->makeSign($params);

        $ch = curl_init($this->gateway);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['code' => 0, 'message' => '请求失败: ' . $err, 'data' => null];
        }
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['code' => 0, 'message' => '返回解析失败: ' . $raw, 'data' => null];
        }
        // 拼多多错误返回 {error_response:{error_msg:...}}
        if (isset($data['error_response'])) {
            return ['code' => 0, 'message' => $data['error_response']['error_msg'] ?? '接口错误', 'data' => $data['error_response']];
        }
        return ['code' => 1, 'message' => 'success', 'data' => $data];
    }

    /**
     * 搜索商品，返回统一字段（goodsId = goods_sign）
     */
    public function searchGoods($keyword, $pageSize = 20, $page = 1, $sort = '', $hasCoupon = '', $pmin = '', $pmax = '')
    {
        $params = [
            'keyword' => $keyword,
            'page' => intval($page),
            'page_size' => intval($pageSize),
            'pid' => $this->pid,
        ];
        if (!empty($sort)) {
            $params['sort_type'] = intval($sort);
        }
        if ($hasCoupon === '1' || $hasCoupon === 1) {
            $params['with_coupon'] = 'true';
        }
        // 价格区间（拼多多 range_list：0=价格下限，1=价格上限，单位分）
        $range = [];
        if ($pmin !== '' && is_numeric($pmin)) {
            $range[0] = intval(floatval($pmin) * 100);
        }
        if ($pmax !== '' && is_numeric($pmax)) {
            $range[1] = intval(floatval($pmax) * 100);
        }
        if (!empty($range)) {
            $params['range_list'] = json_encode([$range], JSON_UNESCAPED_UNICODE);
        }

        $ret = $this->request('pdd.ddk.goods.search', $params);
        if ($ret['code'] != 1) {
            return ['code' => 0, 'message' => $ret['message'], 'items' => [], 'total' => 0];
        }
        $resp = $ret['data']['goods_search_response'] ?? [];
        $list = $resp['goods_list'] ?? [];
        $items = [];
        foreach ($list as $it) {
            $items[] = [
                'goodsId' => $it['goods_sign'] ?? '',            // 产品ID = goods_sign（与大淘客 goodsSign 对齐）
                'goodsSign' => $it['goods_sign'] ?? '',
                'title' => $it['goods_name'] ?? '',
                'originalPrice' => $it['min_group_price'] ? $it['min_group_price'] / 100 : 0,
                'actualPrice' => isset($it['min_group_price']) ? ($it['min_group_price'] - ($it['coupon_discount'] ?? 0)) / 100 : 0,
                'couponPrice' => isset($it['coupon_discount']) ? $it['coupon_discount'] / 100 : 0,
                'mainPic' => $it['goods_thumbnail_url'] ?? ($it['goods_image_url'] ?? ''),
                'monthSales' => $it['sales_num'] ?? 0,
                'commissionRate' => isset($it['promotion_rate']) ? $it['promotion_rate'] / 10 : 0, // 千分比 -> 百分比
                'shopName' => '',
                'item_from' => 'pdd',
                'couponStartTime' => $it['coupon_start_time'] ?? '',
                'couponEndTime' => $it['coupon_end_time'] ?? '',
            ];
        }
        return ['code' => 1, 'message' => 'success', 'items' => $items, 'total' => intval($resp['total_count'] ?? 0)];
    }

    /**
     * 转链：goods_sign + p_id 生成推广短链
     */
    public function getPrivilegeLink($goodsSign, $pid = '')
    {
        $pid = $pid ?: $this->pid;
        $params = [
            'p_id' => $pid,
            'goods_sign' => $goodsSign,
            'generate_short_url' => 'true',
            'generate_mobile' => 'true',
            'multi_group' => 'false',
        ];
        $ret = $this->request('pdd.ddk.goods.promotion.url.generate', $params);
        if ($ret['code'] != 1) {
            return ['code' => 0, 'message' => $ret['message'], 'data' => null];
        }
        $resp = $ret['data']['goods_promotion_url_generate_response'] ?? [];
        $list = $resp['goods_promotion_url_list'] ?? [];
        $urlInfo = $list[0] ?? [];
        $shortUrl = $urlInfo['short_url'] ?? ($urlInfo['mobile_short_url'] ?? ($urlInfo['url'] ?? ''));
        $mobileUrl = $urlInfo['mobile_url'] ?? ($urlInfo['mobile_short_url'] ?? '');
        return [
            'code' => 1,
            'message' => 'success',
            'data' => [
                'couponLink' => $shortUrl,
                'url' => $shortUrl,
                'mobileUrl' => $mobileUrl,
                'raw' => $urlInfo,
            ],
        ];
    }
}
