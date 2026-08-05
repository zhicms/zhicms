<?php
namespace app\api\controller;

/**
 * 商品接口（真实大淘客 / 好单库数据），供小程序商品推荐使用。
 * 字段已映射为小程序插件约定的格式：id / name / price / originalPrice / image / tag / sold ...
 */
class GoodsController extends ApiBaseController {

    /**
     * 商品搜索
     * GET index.php?r=api/goods/search&keyword=手机&platform=taobao&page=1&page_size=20
     */
    public function search() {
        $this->options();

        $keyword   = trim($this->raw('keyword', ''));
        $platform  = $this->raw('platform', 'taobao');
        $page      = max(1, intval($this->raw('page', 1)));
        $pageSize  = min(50, max(1, intval($this->raw('page_size', 20))));

        if ($keyword === '') {
            $this->json(array('code' => 0, 'message' => '关键词不能为空', 'products' => array()), 400);
        }

        $tjk = new \ZhiCms\ext\Tjk();
        $res = $tjk->searchGoods($keyword, $platform, $page, $pageSize);

        if (empty($res) || $res['code'] != 1) {
            $this->json(array(
                'code'    => 0,
                'message' => $res['message'] ?? '未找到商品',
                'products' => array(),
            ));
        }

        $products = array_map(array($this, 'mapProduct'), $res['items'] ?? array());

        $this->json(array(
            'code'    => 1,
            'message' => 'success',
            'products' => $products,
            'total'   => $res['total'] ?? count($products),
        ));
    }

    /**
     * 商品详情
     * GET index.php?r=api/goods/detail&id=商品ID&platform=dtk
     */
    public function detail() {
        $this->options();

        $id       = trim($this->raw('id', ''));
        $platform = $this->raw('platform', 'dtk');

        if ($id === '') {
            $this->json(array('code' => 0, 'message' => '商品ID不能为空'), 400);
        }

        $tjk = new \ZhiCms\ext\Tjk();
        $res = $tjk->getGoodsDetail($id, $platform);

        if (empty($res) || $res['code'] != 1) {
            $this->json(array('code' => 0, 'message' => $res['message'] ?? '商品不存在', 'product' => null));
        }

        $this->json(array(
            'code'    => 1,
            'message' => 'success',
            'product' => $this->mapProduct($res['item'] ?? array()),
        ));
    }

    /**
     * 榜单商品（风云榜）
     * GET index.php?r=api/goods/rank&type=1   (1实时榜 2全天热销榜 3热推榜 7综合热搜榜)
     */
    public function rank() {
        $this->options();

        $type = intval($this->raw('type', 1));
        if (!in_array($type, array(1, 2, 3, 7), true)) {
            $type = 1;
        }

        $tjk = new \ZhiCms\ext\Tjk();
        $res = $tjk->getRankingList($type, '', 20, '1');

        if (empty($res) || $res['code'] != 1) {
            $this->json(array('code' => 0, 'message' => $res['message'] ?? '榜单数据获取失败', 'products' => array()));
        }

        $products = array_map(array($this, 'mapProduct'), $res['items'] ?? array());

        $this->json(array(
            'code'     => 1,
            'message'  => 'success',
            'rankType' => $type,
            'products' => $products,
        ));
    }

    /**
     * 获取商品淘口令（转链）
     * GET index.php?r=api/goods/tpwd&id=商品ID&platform=dtk
     * 说明：淘系链接无法在微信/抖音等小程序中通过 web-view 打开，
     * 故小程序端点击商品时改为复制淘口令，引导用户打开手机淘宝。
     */
    public function tpwd() {
        $this->options();

        $id         = trim($this->raw('id', ''));
        $platform   = $this->raw('platform', 'dtk');
        $goodsSign  = trim($this->raw('goodsSign', ''));
        $itemLink   = trim($this->raw('itemLink', ''));
        $couponLink = trim($this->raw('couponLink', ''));

        // 淘宝商品 id（num_iid）是纯数字；若传入的 id 非数字，说明真实产品 id 实际在 goodsSign 中，
        // 按「淘宝的产品 id 就是 goodsSign」自动改用 goodsSign 作为转链主 id
        if ($id !== '' && !ctype_digit((string) $id) && $goodsSign !== '') {
            $id = $goodsSign;
        }

        // 没有商品ID时，若带原始推广链接则直接返回，便于小程序复制兜底
        if ($id === '') {
            if ($couponLink || $itemLink) {
                $this->json(array(
                    'code' => 1,
                    'message' => 'success',
                    'tpwd' => '',
                    'url'  => $couponLink ?: $itemLink,
                ));
                return;
            }
            $this->json(array('code' => 0, 'message' => '商品ID不能为空'), 400);
        }

        $tjk = new \ZhiCms\ext\Tjk();
        $res = $tjk->getPrivilegeLink($id, $itemLink, $platform, $goodsSign);

        if (empty($res) || $res['code'] != 1 || empty($res['data'])) {
            // 转链失败：退回可复制的领券/推广链接，保证用户至少能打开商品
            if ($couponLink || $itemLink) {
                $this->json(array(
                    'code'    => 1,
                    'message' => 'success',
                    'tpwd'    => '',
                    'url'     => $couponLink ?: $itemLink,
                ));
                return;
            }
            $this->json(array('code' => 0, 'message' => $res['message'] ?? '淘口令生成失败'));
        }

        $d = $res['data'];
        // 淘客推广链接（shortUrl）始终可用：即使无优惠券，也用它作为「复制链接」兜底，而非优惠券链接
        $taokeUrl = $d['shortUrl'] ?? ($d['shortLink'] ?? '');
        $fallbackUrl = $taokeUrl ?: ($d['couponLink'] ?: ($d['itemUrl'] ?: ($couponLink ?: $itemLink)));
        $this->json(array(
            'code'       => 1,
            'message'    => 'success',
            'tpwd'       => $d['tpwd'] ?? ($d['tkl'] ?? ''),
            'longTpwd'   => $d['longTpwd'] ?? ($d['tpwd'] ?? ($d['tkl'] ?? '')),
            'shortUrl'   => $taokeUrl,
            'couponLink' => $d['couponLink'] ?? ($couponLink ?: $itemLink),
            'itemUrl'    => $d['itemUrl'] ?? '',
            // url 默认用淘客链接（无券也可用）；仅当淘客链接缺失时才退回优惠券/原始链接
            'url'        => $fallbackUrl,
        ));
    }

    /**
     * 商品转链（供移动端 H5 调用，替代好单库 ratesurl/get_jditems_link）
     * POST index.php?r=api/goods/transfer
     * 参数：itemid=商品ID(淘宝) 或 material_id=商品ID(京东), platform=tb|jd|pdd|vip
     * 返回好单库兼容格式：{ code:1, data:{taoword,item_url,link,shortUrl,couponClickUrl} }
     */
    public function transfer() {
        $this->options();

        $goodsId   = trim($this->raw('itemid', ''));
        $jdId      = trim($this->raw('material_id', ''));
        $platform  = strtolower(trim($this->raw('platform', $this->raw('type', 'tb'))));

        // 京东用 material_id
        if ($platform === 'jd' && empty($goodsId) && !empty($jdId)) {
            $goodsId = $jdId;
        }
        if (empty($goodsId)) {
            $this->json(array('code' => 0, 'msg' => '商品ID不能为空'), 400);
        }

        // 平台映射
        $platformMap = array(
            'tb' => 'taobao', 'taobao' => 'taobao',
            'jd' => 'jd', 'pdd' => 'pdd', 'vip' => 'vip',
        );
        $apiPlatform = $platformMap[$platform] ?? 'taobao';

        $tjk = new \ZhiCms\ext\Tjk();
        $res = $tjk->getPrivilegeLink($goodsId, '', $apiPlatform);

        if (empty($res) || $res['code'] != 1 || empty($res['data'])) {
            // 转链失败：兜底返回原始链接（前端可复制跳转）
            $this->json(array(
                'code' => 0,
                'msg'  => $res['message'] ?? '转链失败',
                'data' => null,
            ));
        }

        $d = $res['data'];
        $shortUrl = $d['shortUrl'] ?? ($d['shortLink'] ?? '');
        $couponLink = $d['couponClickUrl'] ?? ($d['couponLink'] ?? '');
        $itemUrl    = $d['itemUrl'] ?? ($d['itemLink'] ?? '');

        // 好单库兼容格式
        $this->json(array(
            'code' => 1,
            'data' => array(
                'taoword'        => $d['tpwd'] ?? ($d['tkl'] ?? ''),
                'item_url'       => $shortUrl ?: $couponLink ?: $itemUrl,
                'link'           => $shortUrl ?: $itemUrl,
                'shortUrl'       => $shortUrl,
                'couponClickUrl' => $couponLink,
            ),
        ));
    }

    /**
     * 剪贴板口令/链接转链（公共底部识别粘贴板调用）
     * POST index.php?r=api/goods/convert
     * 参数：content=口令或链接, platform=taobao|jd|pdd|vip
     * 返回：{ code, label, converted, shortUrl, title }
     *   - 淘宝：解析内容 -> 高效转链，返回专属淘口令/淘客短链
     *   - 京东/拼多多/唯品会：当前仅回显原链接（大淘客仅支持淘宝，好单库未开放解析），
     *     前端提示条仍会展示并支持一键复制，剪贴板原口令会被注销避免二次识别
     */
    public function convert() {
        $this->options();

        $content  = trim($this->raw('content', ''));
        $platform = strtolower(trim($this->raw('platform', '')));

        if ($content === '') {
            $this->json(array('code' => 0, 'message' => '内容为空'), 400);
        }

        $labels = array('taobao' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会');
        $label  = $labels[$platform] ?? '电商';

        // 仅淘宝走大淘客真实转链；其余平台暂不支持转换，回显原内容
        if ($platform === 'taobao') {
            $tjk = new \ZhiCms\ext\Tjk();
            $parse = $tjk->parseContent($content, 'dtk');
            if ($parse['code'] == 1 && !empty($parse['data']['goodsId'])) {
                $goodsId = (string) $parse['data']['goodsId'];
                $title   = $parse['data']['title'] ?? '';
                $priv = $tjk->getPrivilegeLink($goodsId, '', 'dtk', '');
                if ($priv['code'] == 1 && !empty($priv['data'])) {
                    $d        = $priv['data'];
                    $tpwd     = $d['longTpwd'] ?? ($d['tpwd'] ?? '');
                    $shortUrl = $d['shortUrl'] ?? '';
                    $converted = $tpwd ?: $shortUrl;
                    if ($converted !== '') {
                        $this->json(array(
                            'code'     => 1,
                            'message'  => 'success',
                            'label'    => $label,
                            'converted'=> $converted,
                            'tpwd'     => $tpwd,
                            'shortUrl' => $shortUrl,
                            'title'    => $title,
                        ));
                    }
                }
            }
            // 淘宝解析/转链失败：回显原口令，保证仍可复制
            $this->json(array(
                'code'     => 1,
                'message'  => 'success',
                'label'    => $label,
                'converted'=> $content,
                'title'    => '',
            ));
        }

        // 京东/拼多多/唯品会：暂不支持转换，回显原链接（前端提示一键复制）
        $this->json(array(
            'code'     => 1,
            'message'  => 'success',
            'label'    => $label,
            'converted'=> $content,
            'title'    => '',
        ));
    }

    /**
     * 将标准化商品字段映射为小程序插件所需格式
     */
    protected function mapProduct($it) {
        if (empty($it) || !is_array($it)) {
            return array();
        }
        $itemFrom    = $it['item_from'] ?? '';
        $goodsSign   = $it['goodsSign'] ?? '';
        $couponPrice = isset($it['couponPrice']) ? floatval($it['couponPrice']) : 0;
        $shopType    = isset($it['shopType']) ? intval($it['shopType']) : 0;

        if ($couponPrice > 0) {
            $tag = '券¥' . $couponPrice;
        } elseif ($shopType == 1) {
            $tag = '天猫';
        } else {
            $tag = '';
        }

        $goodsId = $it['goodsId'] ?? '';
        $detailUrl = $goodsId !== ''
            ? $this->siteUrl() . 'index.php?r=index/view/detail/id=' . urlencode($goodsId) . '/type=' . urlencode($itemFrom ?: 'tb')
            : '';

        return array(
            'id'           => $goodsId,
            'goodsSign'    => $goodsSign,
            'name'         => $it['title'] ?: ($it['dtitle'] ?? ''),
            'price'        => isset($it['actualPrice']) ? floatval($it['actualPrice']) : 0,
            'originalPrice'=> isset($it['originalPrice']) ? floatval($it['originalPrice']) : 0,
            'couponPrice'  => $couponPrice,
            'image'        => $it['mainPic'] ?? '',
            'tag'          => $tag,
            'sold'         => isset($it['monthSales']) ? intval($it['monthSales']) : 0,
            'shopType'     => $shopType,
            'shopName'     => $it['shopName'] ?? '',
            'platform'     => $itemFrom,
            // 推广/领券链接优先（itemLink 在大淘客搜索接口中常为空串），小程序点击跳转用
            'itemLink'     => (!empty($it['itemLink']) ? $it['itemLink'] : ($it['couponLink'] ?? '')),
            'couponLink'   => $it['couponLink'] ?? '',
            'detail_url'   => $detailUrl,
        );
    }

    /**
     * 好单库超搜索代理（替代 haodanku supersearch / jd_goods_search / pdd_goods_search 等）
     * GET index.php?r=api/goods/hdksearch&keyword=手机&platform=tb&min_id=1&min_size=20
     * 返回好单库兼容格式：{code:1, data:[...], min_id:"2"}
     */
    public function hdksearch() {
        $this->options();

        $keyword  = trim($this->raw('keyword', ''));
        $platform = strtolower(trim($this->raw('platform', 'tb')));
        $minId    = max(1, intval($this->raw('min_id', '1')));
        $pageSize = min(50, max(1, intval($this->raw('min_size', 20))));

        if ($keyword === '') {
            $this->json(array('code' => 0, 'msg' => '关键词不能为空', 'data' => array(), 'min_id' => ''));
        }

        // 平台映射
        $platMap = array(
            'tb' => 'taobao', 'jd' => 'jd', 'pdd' => 'pdd', 'vip' => 'vip', 'wph' => 'vip',
        );
        $apiPlatform = $platMap[$platform] ?? 'taobao';

        $tjk = new \ZhiCms\ext\Tjk();
        $res = $tjk->searchGoods($keyword, $apiPlatform, $minId, $pageSize);

        if (empty($res) || $res['code'] != 1 || empty($res['items'])) {
            $this->json(array(
                'code'   => 0,
                'msg'    => $res['message'] ?? '未找到商品',
                'data'   => array(),
                'min_id' => '',
            ));
        }

        $data = array();
        foreach ($res['items'] as $item) {
            $data[] = array(
                'itemid'          => $item['goodsId'] ?? '',
                'itemtitle'       => $item['title'] ?? '',
                'itemshorttitle'  => $item['dtitle'] ?? ($item['title'] ?? ''),
                'itempic'         => $item['mainPic'] ?? '',
                'itemprice'       => (string)($item['originalPrice'] ?? '0'),
                'itemendprice'    => (string)($item['actualPrice'] ?? '0'),
                'itemsale'        => (string)($item['monthSales'] ?? '0'),
                'shoptype'        => ($item['shopType'] ?? 0) == 1 ? 'B' : 'C',
                'shopname'        => $item['shopName'] ?? '',
                'couponmoney'     => (string)($item['couponPrice'] ?? '0'),
                'couponstarttime' => $item['couponStartTime'] ?? '',
                'couponendtime'   => $item['couponEndTime'] ?? '',
                'couponurl'       => $item['couponLink'] ?? '',
                'tkrates'         => (string)($item['commissionRate'] ?? '0'),
                'tkmoney'         => '',
                'itemdesc'        => $item['content'] ?? '',
                'sellernick'      => $item['shopName'] ?? '',
                'sellerId'        => (string)($item['shopId'] ?? '0'),
                'goodsSign'       => $item['goodsSign'] ?? '',
            );
        }

        $nextMinId = (string)($minId + 1);
        if (count($data) < $pageSize) {
            $nextMinId = (string)$minId; // 最后一页
        }

        $this->json(array(
            'code'   => 1,
            'msg'    => 'success',
            'data'   => $data,
            'min_id' => $nextMinId,
        ));
    }

    /**
     * 搜索建议代理（替代 haodanku column/get_suggest）
     * GET index.php?r=api/goods/hdksuggest&keyword=手机
     * 返回好单库兼容格式：{code:1, data:[{keyword:"联想词1"}, ...]}
     */
    public function hdksuggest() {
        $this->options();

        $keyword = trim($this->raw('keyword', $this->raw('key', '')));
        if ($keyword === '') {
            $this->json(array('code' => 0, 'data' => array()));
        }

        $kw = addslashes($keyword);
        $items = obj('api/ApiData')->thisQuery(
            "SELECT DISTINCT title FROM yun_items WHERE del = 0 AND title LIKE '%{$kw}%' LIMIT 10"
        );

        $data = array();
        $seen  = array();
        if (!empty($items)) {
            foreach ($items as $row) {
                $t = $row['title'] ?? '';
                if ($t === '') continue;
                // 从标题中提取关键词片段作为联想
                $short  = mb_substr(str_replace($keyword, '', $t), 0, 15, 'UTF-8');
                $sug    = trim($keyword . $short);
                if ($sug !== '' && !isset($seen[$sug])) {
                    $seen[$sug] = true;
                    $data[]     = array('keyword' => $sug);
                }
            }
        }

        // 无本地结果时，返回关键词变体兜底
        if (empty($data)) {
            $data[] = array('keyword' => $keyword . '正品');
            $data[] = array('keyword' => $keyword . '旗舰店');
            $data[] = array('keyword' => $keyword . '同款');
        }

        $this->json(array('code' => 1, 'data' => $data));
    }

    /**
     * 好单库通用代理（透明转发，后端注入 apikey，前端无需暴露密钥）
     * GET/POST index.php?r=api/goods/hdkproxy&_target=wire_report_new&cid=xxx&min_id=1...
     * 响应为好单库原始 JSON
     */
    public function hdkproxy() {
        $this->options();

        $target = trim($this->raw('_target', ''));
        if ($target === '') {
            $this->json(array('code' => 0, 'msg' => '缺少 _target 参数'), 400);
        }

        $api = \app\common\ConfigStore::load('api');
        $hdkApiKey = $api['hdk_apikey'] ?? ($api['hdk_appkey'] ?? '');
        if (empty($hdkApiKey)) {
            $this->json(array('code' => 0, 'msg' => '好单库API未配置'), 500);
        }

        // 根据接口版本选择主机
        $v3Targets = array('wire_report_new', 'weal_category', 'weal_list', 'get_share_link');
        $host = in_array($target, $v3Targets)
            ? 'https://v3.api.haodanku.com/'
            : 'https://v2.api.haodanku.com/';
        $url = $host . $target;

        // 收集所有参数（排除框架内部参数和 _target）
        $params = $_REQUEST;
        unset($params['r'], $params['_target'], $params['__'], $params['s'], $params['callback']);
        $params['apikey'] = $hdkApiKey;

        try {
            $ch = curl_init();
            $method = strtoupper($_SERVER['\REQUEST_METHOD'] ?? 'GET');
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            } else {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $output   = curl_exec($ch);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->json(array('code' => 0, 'msg' => '请求失败: ' . $error), 500);
            }

            // 透明返回好单库原始 JSON
            header('Content-Type: application/json; charset=utf-8');
            echo $output;
            exit;
        } catch (\Exception $e) {
            $this->json(array('code' => 0, 'msg' => $e->getMessage()), 500);
        }
    }
}
