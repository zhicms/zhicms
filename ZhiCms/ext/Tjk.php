<?php
namespace ZhiCms\ext;

use ZhiCms\ext\Tjk\Dtk;
use ZhiCms\ext\Tjk\Hdk;
use ZhiCms\ext\Tjk\Pdd;

class Tjk {
    
    protected $dtk;
    protected $hdk;
    protected $pdd;
    protected $pid = '';        // 淘宝联盟推广位 pid（转链/生成淘口令时用于佣金归属）
    protected $pddPid = '';     // 拼多多推广位 pid
    protected $customConfig = [];
    
    public function __construct($config = null) {
        $this->customConfig = $config;
        
        if (!empty($config)) {
            $this->initWithCustomConfig($config);
        } else {
            $this->initWithLocalConfig();
        }
    }
    
    private function initWithCustomConfig(array $config) {
        $dtkAppKey = $config['DtkappKey'] ?? '';
        $dtkAppSecret = $config['DtkappSecret'] ?? '';
        $hdkApiKey = $config['HdkApiKey'] ?? '';
        $hdkUnionId = $config['HdkUnionId'] ?? '';
        $hdkVipPid = $config['HdkVipPid'] ?? '';
        $hdkPddPid = $config['HdkPddPid'] ?? '';
        $pddClientId = $config['PddClientId'] ?? '';
        $pddClientSecret = $config['PddClientSecret'] ?? '';
        $this->pddPid = $config['PddPid'] ?? $hdkPddPid;
        $this->pid = $config['pid'] ?? ($config['dtk_pid'] ?? ($config['tb_pid'] ?? ''));
        
        if (!empty($dtkAppKey) && !empty($dtkAppSecret)) {
            $this->dtk = new Dtk($dtkAppKey, $dtkAppSecret);
        }
        
        if (!empty($hdkApiKey)) {
            $this->hdk = new Hdk($hdkApiKey, $hdkUnionId, $hdkVipPid, $hdkPddPid);
        }
        
        if (!empty($pddClientId) && !empty($pddClientSecret)) {
            $this->pdd = new Pdd($pddClientId, $pddClientSecret, $this->pddPid);
        }
        
        if (empty($this->dtk) && empty($this->hdk)) {
            $this->initWithLocalConfig();
        }
    }
    
    private function initWithLocalConfig() {
        if (class_exists('\\app\\common\\ConfigStore')) {
            $api = \app\common\ConfigStore::load('api');
        } else {
            $api = array();
            if (file_exists(\CONFIG_PATH . 'apiset.php')) {
                include \CONFIG_PATH . 'apiset.php';
            }
        }
        $dtkAppKey = $api['dtk_appkey'] ?? '';
        $dtkAppSecret = $api['dtk_appsecret'] ?? '';
        $hdkApiKey = $api['hdk_appkey'] ?? '';
        $hdkUnionId = $api['hdk_union_id'] ?? '';
        $hdkVipPid = $api['hdk_vip_pid'] ?? '';
        $hdkPddPid = $api['hdk_pdd_pid'] ?? '';
        $pddClientId = $api['pdd_client_id'] ?? '';
        $pddClientSecret = $api['pdd_client_secret'] ?? '';
        $this->pddPid = $api['pdd_pid'] ?? $hdkPddPid;
        $this->pid = $api['dtk_pid'] ?? ($api['tb_pid'] ?? '');
        
        if (!empty($dtkAppKey) && !empty($dtkAppSecret)) {
            $this->dtk = new Dtk($dtkAppKey, $dtkAppSecret);
        }
        
        if (!empty($hdkApiKey)) {
            $this->hdk = new Hdk($hdkApiKey, $hdkUnionId, $hdkVipPid, $hdkPddPid);
        }
        
        if (!empty($pddClientId) && !empty($pddClientSecret)) {
            $this->pdd = new Pdd($pddClientId, $pddClientSecret, $this->pddPid);
        }
    }
    
    public function setCustomConfig(array $config) {
        $this->customConfig = $config;
        $this->initWithCustomConfig($config);
    }
    
    public function searchGoods($keyword, $platform = 'taobao', $pageNum = 1, $pageSize = 20, $minId = 1, $sort = '', $hasCoupon = '', $brand = '', $pmin = '', $pmax = '') {
        $platform = strtolower($platform);

        // 品牌作为关键词追加（API 搜索按关键词匹配）
        if (!empty($brand)) {
            $keyword = trim($keyword . ' ' . $brand);
        }

        // 淘宝/天猫：仅走大淘客（Dtk），按约定淘宝转链/搜索统一用大淘客
        if ($platform === 'taobao' || $platform === 'dtk') {
            if (!$this->dtk) {
                return ['code' => 0, 'message' => '大淘客API未配置', 'items' => [], 'total' => 0];
            }
            // 排序映射：站内排序值 -> 大淘客 sort 编码
            // 0综合 1价格低到高 2价格高到低 3销量低到高 4销量高到低 5佣金比例低到高 6佣金比例高到低
            $dtkSortMap = array(
                'score'     => '0',
                'default'   => '0',
                'sales'     => '4',
                'sales_asc' => '3',
                'price'     => '1',
                'price_asc' => '1',
                'price_desc'=> '2',
                'commission'=> '6',
                'new'       => '0',
            );
            $dtkSort = '';
            if ($sort !== '' && $sort !== null) {
                $dtkSort = isset($dtkSortMap[$sort]) ? $dtkSortMap[$sort] : (is_numeric($sort) ? strval($sort) : '');
            }
            $dtkRes = $this->dtk->SearchGoods($keyword, $pageNum, $pageSize, $pmin, $pmax, $dtkSort);
            if ($dtkRes['code'] == 1 && !empty($dtkRes['items'])) {
                foreach ($dtkRes['items'] as &$it) {
                    $it['item_from'] = 'tb';
                }
                unset($it);
                return $dtkRes;
            }
            return ['code' => 0, 'message' => $dtkRes['message'] ?? '未找到商品，请检查大淘客配置或关键词', 'items' => [], 'total' => 0];
        }

        // 好单库淘宝（hdk 单独别名，仍走大淘客保持一致）
        if ($platform === 'hdk') {
            // 注意：$keyword 已在上方拼入品牌，这里 brand 传空避免重复追加
            return $this->searchGoods($keyword, 'taobao', $pageNum, $pageSize, $minId, $sort, $hasCoupon, '', $pmin, $pmax);
        }

        // 好单库多平台：拼多多 / 京东 / 唯品会
        if (!$this->hdk) {
            return [
                'code' => 0,
                'message' => '好单库API未配置',
                'items' => [],
                'total' => 0,
            ];
        }

        switch ($platform) {
            case 'pdd':
                // 优先走拼多多官方多多进宝 SDK，好单库作为备用
                if ($this->pdd) {
                    $pddRes = $this->pdd->searchGoods($keyword, $pageSize, $pageNum, $sort, $hasCoupon, $pmin, $pmax);
                    if ($pddRes['code'] == 1 && !empty($pddRes['items'])) {
                        $pddRes['item_from'] = 'pdd';
                        return $pddRes;
                    }
                }
                $result = $this->hdk->SearchPddGoods($keyword, $pageSize, $minId, $sort, $hasCoupon);
                break;
            case 'jd':
                $result = $this->hdk->SearchJdGoods($keyword, $pageSize, $minId, $sort, $hasCoupon);
                break;
            case 'vip':
                $result = $this->hdk->SearchVipGoods($keyword, $pageSize, $minId);
                break;
            default:
                return [
                    'code' => 0,
                    'message' => '不支持的平台：' . $platform,
                    'items' => [],
                    'total' => 0,
                ];
        }
        $result['item_from'] = $platform;
        return $result;
    }
    
    public function getGoodsDetail($goodsId, $platform = 'dtk') {
        $platform = strtolower($platform);
        
        if ($platform == 'jd' || $platform == 'hdk' || $platform == 'pdd' || $platform == 'vip') {
            if (!$this->hdk) {
                return [
                    'code' => 0,
                    'message' => '好单库API未配置',
                    'item' => null,
                ];
            }
            return $this->hdk->GetGoodsDetails($goodsId);
        }
        
        if (!$this->dtk) {
            return [
                'code' => 0,
                'message' => '大淘客API未配置',
                'item' => null,
            ];
        }
        return $this->dtk->GetGoodsDetails($goodsId);
    }
    
    public function getPrivilegeLink($goodsId = '', $itemUrl = '', $platform = 'dtk', $goodsSign = '', $pid = '') {
        $platform = strtolower($platform);

        // 淘宝 -> 大淘客；京东/拼多多/唯品会 -> 好单库（拼多多优先官方SDK，失败回退好单库）
        if ($platform == 'tb') {
            if (!$this->dtk) {
                return ['code' => 0, 'message' => '大淘客API未配置', 'data' => null];
            }
            $pid = $pid ?: $this->pid;
            $dtkRet = $this->dtk->GetPrivilegeLink($goodsId, $pid, $goodsSign, $itemUrl);
            if (!empty($dtkRet) && isset($dtkRet['code']) && $dtkRet['code'] == 1) {
                return $dtkRet;
            }
            // 大淘客失败，回退好单库（大概率不支持，但兜底）
            if ($this->hdk) {
                return $this->hdk->RatesUrl($goodsId, 'tb');
            }
            return $dtkRet;
        }

        if ($platform == 'jd' || $platform == 'hdk' || $platform == 'pdd' || $platform == 'vip') {
            if (!$this->hdk) {
                return ['code' => 0, 'message' => '好单库API未配置', 'data' => null];
            }
            // 拼多多优先走官方多多进宝 SDK（好单库作为备用），其余平台走好单库 RatesUrl
            if ($platform == 'pdd' && $this->pdd) {
                $pddRet = $this->pdd->getPrivilegeLink($goodsId, $pid ?: $this->pddPid);
                if (isset($pddRet['code']) && $pddRet['code'] == 1) {
                    return $pddRet;
                }
                // 官方失败，回退好单库
                return $this->hdk->RatesUrl($goodsId, 'pdd');
            }
            return $this->hdk->RatesUrl($goodsId, $platform);
        }

        if (!$this->dtk) {
            return [
                'code' => 0,
                'message' => '大淘客API未配置',
                'data' => null,
            ];
        }
        // pid 优先用调用方传入，否则用全局配置的推广位
        $pid = $pid ?: $this->pid;
        return $this->dtk->GetPrivilegeLink($goodsId, $pid, $goodsSign, $itemUrl);
    }
    
    public function parseContent($content, $platform = 'dtk') {
        $platform = strtolower($platform);
        
        if ($platform == 'jd' || $platform == 'hdk' || $platform == 'pdd' || $platform == 'vip') {
            if (!$this->hdk) {
                return [
                    'code' => 0,
                    'message' => '好单库API未配置',
                    'data' => null,
                ];
            }
            return ['code' => 0, 'message' => '好单库暂不支持解析内容'];
        }
        
        if (!$this->dtk) {
            return [
                'code' => 0,
                'message' => '大淘客API未配置',
                'data' => null,
            ];
        }
        return $this->dtk->ParseContent($content);
    }
    
    /**
     * 跨平台聚合搜索：淘宝(大淘客) + 京东/拼多多/唯品会(好单库)
     * 每个平台取前 $pageSize 条，合并后按销量排序、去重。
     *
     * @param string     $keyword   商品关键词
     * @param int        $pageNum   页码
     * @param int        $pageSize  每个平台条数
     * @param array|null $platforms 需要搜索的平台数组，null 表示全部
     *                              （前台全站搜索传 ['taobao','jd'] 以还原旧行为，
     *                               避免混入 pdd/vip 影响详情页跳转）
     */
    public function searchAllPlatforms($keyword, $pageNum = 1, $pageSize = 5, $platforms = null) {
        // 比价路线：以淘宝联盟库为主，优先返回淘宝结果；淘宝不足时用京东/拼多多/唯品会补充。
        $allItems = [];
        $byPlat   = ['tb' => [], 'jd' => [], 'pdd' => [], 'vip' => []];

        $wantTb  = $this->dtk && (is_null($platforms) || in_array('tb', (array) $platforms) || in_array('taobao', (array) $platforms));
        $wantJd  = $this->hdk && (is_null($platforms) || in_array('jd', (array) $platforms));
        $wantPdd = $this->hdk && (is_null($platforms) || in_array('pdd', (array) $platforms));
        $wantVip = $this->hdk && (is_null($platforms) || in_array('vip', (array) $platforms));
        $wantHdk = $this->hdk && ($wantJd || $wantPdd || $wantVip);

        // 1) 淘宝/天猫：大淘客（优先，优先填满目标数量）
        if ($wantTb) {
            $result = $this->dtk->SearchGoods($keyword, $pageNum, $pageSize);
            if ($result['code'] == 1 && !empty($result['items'])) {
                foreach ($result['items'] as $item) {
                    $item['item_from'] = 'tb';
                    $byPlat['tb'][] = $item;
                }
            }
        }

        // 2) 京东 / 拼多多 / 唯品会：好单库（作为淘宝比价的补充）
        if ($wantHdk) {
            if ($wantJd) {
                $jd = $this->hdk->SearchJdGoods($keyword, $pageSize, 1);
                if ($jd['code'] == 1 && !empty($jd['items'])) {
                    foreach ($jd['items'] as $item) {
                        $item['item_from'] = 'jd';
                        $byPlat['jd'][] = $item;
                    }
                }
            }
            if ($wantPdd) {
                $pdd = $this->hdk->SearchPddGoods($keyword, $pageSize, 1);
                if ($pdd['code'] == 1 && !empty($pdd['items'])) {
                    foreach ($pdd['items'] as $item) {
                        $item['item_from'] = 'pdd';
                        $byPlat['pdd'][] = $item;
                    }
                }
            }
            if ($wantVip) {
                $vip = $this->hdk->SearchVipGoods($keyword, $pageSize, 1);
                if ($vip['code'] == 1 && !empty($vip['items'])) {
                    foreach ($vip['items'] as $item) {
                        $item['item_from'] = 'vip';
                        $byPlat['vip'][] = $item;
                    }
                }
            }
        }

        // 去重（同平台同商品ID视为重复；无ID则用标题）
        $seen = [];
        foreach (['tb', 'jd', 'pdd', 'vip'] as $p) {
            foreach ($byPlat[$p] as $it) {
                $id  = $it['goodsId'] ?? '';
                $key = $p . ':' . ($id !== '' ? $id : (mb_substr($it['title'] ?? '', 0, 20)));
                if (isset($seen[$key])) continue;
                $seen[$key] = 1;
                $allItems[] = $it;
            }
        }

        // 排序：淘宝优先（比价主推），其余按京东/拼多多/唯品会顺序；同平台按销量降序
        $platOrder = ['tb' => 0, 'jd' => 1, 'pdd' => 2, 'vip' => 3];
        usort($allItems, function($a, $b) use ($platOrder) {
            $pa = $platOrder[$a['item_from'] ?? 'vip'] ?? 9;
            $pb = $platOrder[$b['item_from'] ?? 'vip'] ?? 9;
            if ($pa !== $pb) return $pa - $pb;
            return ($b['monthSales'] ?? 0) - ($a['monthSales'] ?? 0);
        });

        return [
            'code' => 1,
            'message' => 'success',
            'items' => $allItems,
            'total' => count($allItems),
        ];
    }
    
    public function pullGoodsByTime($params = []) {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置'];
        }
        
        $pageSize = $params['pageSize'] ?? 10;
        $pageId = $params['pageId'] ?? '1';
        $cid = $params['cid'] ?? '';
        $subcid = $params['subcid'] ?? '';
        $pre = $params['pre'] ?? '';
        $sort = $params['sort'] ?? '';
        $startTime = $params['startTime'] ?? '';
        $endTime = $params['endTime'] ?? '';
        $freeshipRemoteDistrict = $params['freeshipRemoteDistrict'] ?? '';
        $choice = $params['choice'] ?? '';
        $hasCoupon = $params['hasCoupon'] ?? '';
        
        return $this->dtk->PullGoodsByTime($pageSize, $pageId, $cid, $subcid, $pre, $sort, $startTime, $endTime, $freeshipRemoteDistrict, $choice, $hasCoupon);
    }
    
    public function getNewestGoods($pageSize = 10, $pageId = '1') {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置'];
        }
        return $this->dtk->GetNewestGoods($pageSize, $pageId);
    }

    /**
     * 全量商品列表（get-goods-list），返回完整字段包括标题/主图/销量等
     * 适用于联盟库淘宝页面的无关键词全库浏览，替代 pullGoodsByTime
     */
    public function getGoodsList($pageSize = 50, $pageId = '1', $extra = []) {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置', 'items' => [], 'total' => 0];
        }
        return $this->dtk->GetGoodsList($pageSize, $pageId, $extra);
    }
    
    public function getBrandList($pageSize = 50, $pageId = '1', $cid = '') {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置', 'total' => 0, 'pageId' => '', 'brands' => []];
        }
        return $this->dtk->GetBrandColumnList($pageSize, $pageId, $cid);
    }
    
    public function getBrandGoods($brandId, $pageSize = 50, $pageId = '1') {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置', 'total' => 0, 'pageId' => '', 'goods' => [], 'brandInfo' => []];
        }
        $result = $this->dtk->GetBrandGoodsList($brandId, $pageSize, $pageId);

        // 品牌官方商品为空时，用品牌名称作为关键词搜索，补充产品数据
        if ($result['code'] == 1 && empty($result['goods']) && !empty($result['brandInfo']['brandName'])) {
            $keyword = trim($result['brandInfo']['brandName']);
            $search  = $this->dtk->SearchGoods($keyword, 1, $pageSize);
            if ($search['code'] == 1 && !empty($search['items'])) {
                // 优先保留标题中含品牌名的商品以提升相关性；无匹配则回退到全部搜索结果
                $matched = [];
                foreach ($search['items'] as $it) {
                    if ($keyword !== '' && stripos((string)($it['title'] ?? ''), $keyword) !== false) {
                        $matched[] = $it;
                    }
                }
                if (!empty($matched)) {
                    $result['goods'] = array_slice($matched, 0, $pageSize);
                    $result['total'] = count($matched);
                } else {
                    $result['goods'] = $search['items'];
                    $result['total'] = $search['total'] ?? count($search['items']);
                }
                $result['fromSearch'] = true;
            }
        }
        return $result;
    }
    
    /**
     * 各大榜单（对接大淘客 get-ranking-list，文档 id=6）
     * @param int    $rankType 榜单类型：1实时榜 2全天热销榜 3热推榜 7综合热搜榜
     * @param string $cid      类目ID（可选）
     * @param int    $pageSize 每页数量
     * @param string $pageId   分页ID
     * @return array ['code','message','rankType','items','keywords']
     */
    public function getRankingList($rankType = 1, $cid = '', $pageSize = 100, $pageId = '1') {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置', 'rankType' => $rankType, 'items' => [], 'keywords' => []];
        }
        return $this->dtk->GetRankingList($rankType, $cid, $pageSize, $pageId);
    }

    /**
     * 线报（对接大淘客 list-tip-off，文档 id=62）
     * @param string $pageId   分页ID，首次传 "1"
     * @param int    $pageSize 每页数量
     * @param string $topic    主题类型（可选）
     * @param int    $platform 平台筛选：0-淘客（默认），1-京东
     * @return array ['code','message','list','total','pageId']
     */
    public function getTipOff($pageId = '1', $pageSize = 20, $topic = '', $platform = 0) {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置', 'list' => [], 'total' => 0, 'pageId' => ''];
        }
        return $this->dtk->GetTipOff($pageId, $pageSize, $topic, $platform);
    }

    public function getDtk() {
        return $this->dtk;
    }
    
    public function getHdk() {
        return $this->hdk;
    }

    /**
     * 统一商品字段：将大淘客/好单库（及预留的拼多多/唯品会/京东）各接口返回的
     * 商品数组归一化为同一套字段，便于统一入库(yun_items)与前端展示。
     * 拉取/搜索/最新/详情 四类接口返回的数据参数在此处达成一致。
     */
    public static function standardizeItem(array $item, string $itemFrom = ''): array {
        $def = [
            'goodsId' => '', 'goodsSign' => '', 'title' => '', 'dtitle' => '', 'content' => '',
            'itemLink' => '', 'mainPic' => '', 'marketingMainPic' => '',
            'originalPrice' => 0, 'actualPrice' => 0, 'discounts' => 0, 'couponPrice' => 0,
            'couponLink' => '', 'couponStartTime' => '0', 'couponEndTime' => '0', 'couponConditions' => '',
            'couponTotalNum' => 0, 'couponReceiveNum' => 0, 'couponRemainCount' => 0, 'couponId' => '',
            'commissionRate' => 0, 'commissionType' => 0,
            'monthSales' => 0, 'twoHoursSales' => 0, 'dailySales' => 0,
            'shopType' => 0, 'shopName' => '', 'shopId' => 0, 'shopLevel' => 0, 'shopLogo' => '',
            'cid' => 0, 'subcid' => '', 'tbcid' => 0, 'brand' => 0, 'brandId' => 0, 'brandName' => '',
            'activityType' => 0, 'activityStartTime' => '0', 'activityEndTime' => '0', 'activityName' => '', 'activityId' => 0,
            'createTime' => '', 'detailPics' => '', 'yunfeixian' => 0, 'freeshipRemoteDistrict' => 0,
            'choice' => 0, 'hotPush' => 0, 'goldSellers' => 0, 'haitao' => 0, 'tchaoshi' => 0,
            'estimateAmount' => 0, 'specialText' => '', 'inspectedGoods' => 0,
            'dsrScore' => 0, 'dsrPercent' => 0, 'shipScore' => 0, 'shipPercent' => 0,
            'serviceScore' => 0, 'servicePercent' => 0, 'quanMLink' => 0, 'hzQuanOver' => 0,
        ];
        $out = [];
        foreach ($def as $k => $dv) {
            $out[$k] = $item[$k] ?? $dv;
        }
        // 别名归一化（不同平台字段名不一致）
        if (empty($out['title']))    $out['title']    = $item['goodsName'] ?? $item['itemtitle'] ?? '';
        if (empty($out['mainPic']))  $out['mainPic']  = $item['goodsImage'] ?? $item['itempic'] ?? '';
        if (empty($out['content']))  $out['content']  = $item['desc'] ?? $item['itemdesc'] ?? '';
        if (empty($out['shopId']))   $out['shopId']   = intval($item['sellerId'] ?? 0);
        $out['shopId'] = intval($out['shopId']);
        // 店铺类型归一化：B/天猫 -> 1，C/淘宝 -> 0
        $st = $item['shopType'] ?? $out['shopType'];
        if (is_string($st)) {
            $s = strtoupper(trim($st));
            if (in_array($s, ['B', '天猫', 'TMALL'], true)) $st = 1;
            elseif (in_array($s, ['C', '淘宝', 'TB'], true)) $st = 0;
            else $st = is_numeric($st) ? intval($st) : 0;
        }
        $out['shopType'] = intval($st);
        // 数组型字段转 JSON 字符串
        if (is_array($out['subcid']))    $out['subcid']    = json_encode($out['subcid'], JSON_UNESCAPED_UNICODE);
        if (is_array($out['detailPics'])) $out['detailPics'] = json_encode($out['detailPics'], JSON_UNESCAPED_UNICODE);
        // 数值强转
        foreach (['originalPrice','actualPrice','discounts','couponPrice','commissionRate','estimateAmount','dsrScore','dsrPercent','shipScore','shipPercent','serviceScore','servicePercent'] as $f) {
            $out[$f] = is_numeric($out[$f]) ? floatval($out[$f]) : 0;
        }
        foreach (['couponTotalNum','couponReceiveNum','couponRemainCount','commissionType','monthSales','twoHoursSales','dailySales','shopLevel','cid','tbcid','brand','brandId','activityType','activityId','yunfeixian','freeshipRemoteDistrict','choice','hotPush','goldSellers','haitao','tchaoshi','quanMLink','hzQuanOver','inspectedGoods'] as $f) {
            $out[$f] = is_numeric($out[$f]) ? intval($out[$f]) : 0;
        }
        // 来源标记（优先用原始 item_from，否则用传入兜底值）
        $src = $item['item_from'] ?? $itemFrom ?? '';
        // 统一平台编码：淘宝=tb，京东=jd，拼多多=pdd，唯品会=vip（全链路一致，避免 taobao/tb 混用）
        $srcMap = array(
            'taobao' => 'tb', 'tb' => 'tb', 'dtk' => 'tb',
            'jd' => 'jd', 'j d' => 'jd',
            'pdd' => 'pdd', 'pinduoduo' => 'pdd',
            'vip' => 'vip', 'vipshop' => 'vip', 'wph' => 'vip',
        );
        $out['item_from'] = $srcMap[strtolower(trim($src))] ?? strtolower(trim($src));
        return $out;
    }
}