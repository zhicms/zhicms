<?php
namespace ZhiCms\ext;

use ZhiCms\ext\Tjk\Dtk;
use ZhiCms\ext\Tjk\Hdk;
use ZhiCms\ext\Tjk\Pdd;
use app\common\CacheService;

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

    /**
     * 静态工厂：按完整配置键构建实例，并做进程内单例复用。
     *
     * 背景：业务层散落大量 `new \ZhiCms\ext\Tjk()`（不传配置），只能走 initWithLocalConfig，
     * 一旦后台传入自定义配置（多站点/多账号）就会拿错账号；同时每次 new 都会重新构造 SDK。
     * 此工厂统一读取全部 api 配置项（含 pid / 拼多多 / 好单库 unionId），避免调用方漏传。
     *
     * @param array|null $config 自定义配置；null 表示读取本地 api 配置
     * @return static
     */
    public static function factory($config = null) {
        static $instances = [];
        if ($config === null) {
            $key = '__local__';
        } else {
            $key = md5(json_encode($config));
        }
        if (!isset($instances[$key])) {
            $instances[$key] = new static($config);
        }
        return $instances[$key];
    }

    /**
     * 从本地 api 配置读取**完整**配置数组（含 pid / 拼多多 / 好单库参数）。
     * 供后台等需要显式传配置的场景使用，避免只传 appkey 导致转链 pid、拼多多 SDK 丢失。
     */
    public static function loadFullApiConfig() {
        if (class_exists('\\app\\common\\ConfigStore')) {
            $api = \app\common\ConfigStore::load('api');
        } else {
            $api = array();
            if (file_exists(\CONFIG_PATH . 'apiset.php')) {
                include \CONFIG_PATH . 'apiset.php';
            }
        }
        return is_array($api) ? $api : [];
    }
    
    public function searchGoods($keyword, $platform = 'taobao', $pageNum = 1, $pageSize = 20, $minId = 1, $sort = '', $hasCoupon = '', $brand = '', $pmin = '', $pmax = '') {
        $platform = strtolower(trim((string) $platform));

        // 平台别名归一：调用方可能传 tb/taobao/tmall/tm（统一平台编码）或 dtk/hdk（渠道编码），
        // 若不做归一，传 'tb' 会落到 default 分支报"不支持的平台"，淘宝主搜索直接空结果。
        $alias = array(
            'tb' => 'taobao', 'taobao' => 'taobao', 'tmall' => 'taobao', 'tm' => 'taobao',
            'dtk' => 'taobao', 'hdk' => 'hdk',
            'jd' => 'jd', 'pdd' => 'pdd', 'pinduoduo' => 'pdd',
            'vip' => 'vip', 'vipshop' => 'vip', 'wph' => 'vip',
        );
        $platform = $alias[$platform] ?? $platform;

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
        $platform = strtolower(trim((string)$platform));
        // 平台别名归一（与 searchGoods/getPrivilegeLink 保持一致，避免传 tb/tm/wph 时路由错误）
        $pfAlias = array(
            'tb' => 'dtk', 'taobao' => 'dtk', 'tmall' => 'dtk', 'tm' => 'dtk', 'dtk' => 'dtk',
            'pinduoduo' => 'pdd', 'wph' => 'vip', 'vipshop' => 'vip',
        );
        $platform = $pfAlias[$platform] ?? $platform;
        $goodsId  = trim((string)$goodsId);

        // jd/hdk/pdd/vip 走好单库
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

        // tb/taobao 平台：大淘客 V2 优先（含 desc 推广文案 / detailPics 详情切图），
        // V2 失败时回退 V1，再回退好单库兜底。goodsId 支持非纯数字（淘宝/天猫编码）。
        if ($this->dtk) {
            $res = $this->dtk->GetGoodsDetailsV2($goodsId);
            if (($res['code'] ?? 0) != 1) {
                $res = $this->dtk->GetGoodsDetails($goodsId);
            }
            if (($res['code'] ?? 0) == 1) {
                return $res;
            }
            if ($this->hdk) {
                $hdkRes = $this->hdk->GetGoodsDetails($goodsId);
                if (($hdkRes['code'] ?? 0) == 1) {
                    return $hdkRes;
                }
            }
            return $res;
        }

        if ($this->hdk) {
            return $this->hdk->GetGoodsDetails($goodsId);
        }

        return [
            'code' => 0,
            'message' => 'API未配置',
            'item' => null,
        ];
    }

    /**
     * 热门搜索词（大淘客 get-top100）
     * @param int $type 1=买家热搜榜 2=淘客热搜榜，默认随机
     */
    public function getTopWords($type = 0) {
        if ($this->dtk) {
            if ($type <= 0) $type = rand(1, 2);   // 随机榜
            // 热搜词变化极慢，缓存 30 分钟（搜索页每次进入都会拉取）
            $key = 'api_topwords_' . intval($type);
            return $this->apiCache($key, 1800, function () use ($type) {
                return $this->dtk->GetTop100($type);
            });
        }
        return ['code' => 0, 'message' => 'API未配置', 'data' => []];
    }

    public function getPrivilegeLink($goodsId = '', $itemUrl = '', $platform = 'dtk', $goodsSign = '', $pid = '') {
        $platform = strtolower($platform);
        // 平台别名统一归一：taobao/dtk/tmall/tm 都视为淘宝(tb)，jd/pdd/vip 保持，
        // 避免调用方（网页端 jump 已规范、小程序 open/transfer/convert 仍传 taobao/dtk）传入别名时
        // 因未命中分支而落入兜底大淘客（对京东/拼多多/唯品会会转链失败）。
        if (in_array($platform, ['taobao', 'dtk', 'tmall', 'tm'], true)) {
            $platform = 'tb';
        }
        // 其余平台别名归一（小程序等端常传 wph/pinduoduo 等别名）
        $pfAlias = array('pinduoduo' => 'pdd', 'wph' => 'vip', 'vipshop' => 'vip', 'haodanku' => 'hdk');
        if (isset($pfAlias[$platform])) {
            $platform = $pfAlias[$platform];
        }

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
                // 拼多多转链必须用 goods_sign；goodsId 字段存储的即 goods_sign，
                // 但调用方可能已单独解析出真实 goodsSign（如从本地库查询），优先使用。
                $pddSign = $goodsSign ?: $goodsId;
                $pddRet = $this->pdd->getPrivilegeLink($pddSign, $pid ?: $this->pddPid);
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
     *
     * 两种模式：
     *  - 默认（按销量混合）：各平台各取 $pageSize 条，合并后按平台优先级+销量排序去重
     *  - $fillPriority=true（AI 助手/优先填充）：先搜淘宝，不足 $pageSize 再依次补京东/拼多多/唯品会，
     *    保证用户优先看到主流电商（淘宝/京东）商品，唯品会只在主流平台无货时才出现，避免"全是唯品会"。
     *
     * @param string     $keyword       商品关键词
     * @param int        $pageNum       页码
     * @param int        $pageSize      目标条数
     * @param array|null $platforms     需要搜索的平台数组，null 表示全部
     * @param bool       $fillPriority  是否按平台优先级顺序填充（默认 true，AI 场景）
     */
    public function searchAllPlatforms($keyword, $pageNum = 1, $pageSize = 5, $platforms = null, $fillPriority = true, $filters = array(), $perPlatform = 0) {
        // 比价路线：以淘宝联盟库为主，优先返回淘宝结果；淘宝不足时用京东/拼多多/唯品会补充。
        // $filters: ['price_min'=>int,'price_max'=>int] —— 由 AI 导购意图抽取产出，硬过滤预算。
        $pmin = !empty($filters['price_min']) ? (int)$filters['price_min'] : 0;
        $pmax = !empty($filters['price_max']) ? (int)$filters['price_max'] : 0;
        $allItems = [];
        $byPlat   = ['tb' => [], 'jd' => [], 'pdd' => [], 'vip' => []];

        $wantTb  = $this->dtk && (is_null($platforms) || in_array('tb', (array) $platforms) || in_array('taobao', (array) $platforms));
        $wantJd  = $this->hdk && (is_null($platforms) || in_array('jd', (array) $platforms));
        $wantPdd = $this->hdk && (is_null($platforms) || in_array('pdd', (array) $platforms));
        $wantVip = $this->hdk && (is_null($platforms) || in_array('vip', (array) $platforms));
        $wantHdk = $this->hdk && ($wantJd || $wantPdd || $wantVip);

        // 平台顺序：淘宝(主推) > 京东 > 拼多多 > 唯品会(补充)
        $platPlan = ['tb', 'jd', 'pdd', 'vip'];
        // 若指定了平台范围，则按指定顺序过滤
        if (is_array($platforms)) {
            $platPlan = array_values(array_intersect($platPlan, $platforms));
        }

        // 多平台均衡比价（修复：原"优先填充 break"逻辑在淘宝无结果时，
        // 因京东/拼多多往往无数据，导致唯品会（数据最易返回）霸屏）。
        // 新逻辑：每个可用平台都发起搜索并按"配额"各取若干条做比价，
        // 不再因某一平台填满就停止后续平台；空结果平台标记后跳过，避免无效 API 调用拖慢响应。
        $avaiPlats = [];
        // 平台熔断：某平台连续失败达到阈值后，在冷却期内直接跳过，不再浪费一次 HTTP 请求。
        // 典型场景：拼多多 duoId 行为异常被封、京东 pid 未配置，每次聚合搜索都会白等一个超时。
        $planned = [];
        if ($wantTb)  $avaiPlats[] = 'tb';
        if ($wantJd)  $avaiPlats[] = 'jd';
        if ($wantPdd) $avaiPlats[] = 'pdd';
        if ($wantVip) $avaiPlats[] = 'vip';
        $avaiPlats = array_values(array_intersect($avaiPlats, $platPlan));

        // 剔除处于熔断冷却期的平台（失败次数达阈值且未过冷却时间）
        foreach ($avaiPlats as $idx => $p) {
            if ($this->platBreakerOpen($p)) {
                unset($avaiPlats[$idx]);
            }
        }
        $avaiPlats = array_values($avaiPlats);

        $platCount = count($avaiPlats);
        // 每平台目标配额（至少 1 条），结果不足时允许某平台超额补足
        $quota = $platCount > 0 ? max(1, intval(ceil($pageSize / $platCount))) : $pageSize;
        // 固定每平台数量模式（导购固定格式：每平台 $perPlatform 个，空平台跳过）
        $perPlatCap = $perPlatform > 0 ? (int)$perPlatform : 0;

        foreach ($platPlan as $p) {
            if (!in_array($p, $avaiPlats, true)) continue;
            $want = $perPlatCap > 0 ? $perPlatCap : ($fillPriority ? $quota : $pageSize);
            if ($p === 'tb') {
                // 主搜：大淘客；若大淘客无结果/未配置，用好单库超级搜索（淘宝）兜底
                $result = null;
                if ($this->dtk) {
                    // 透传预算区间（pmin/pmax），让"预算"在淘宝主搜层真正硬过滤
                    $result = $this->dtk->SearchGoods($keyword, $pageNum, $want, $pmin, $pmax);
                }
                if (!($result['code'] == 1 && !empty($result['items'])) && $this->hdk) {
                    $result = $this->hdk->SearchGoods($keyword, $want, 1);
                }
                if ($result['code'] == 1 && !empty($result['items'])) {
                    foreach ($result['items'] as $item) {
                        $item['item_from'] = 'tb';
                        $byPlat['tb'][] = $item;
                    }
                    $this->platBreakerRecord('tb', true);
                } else {
                    $this->platBreakerRecord('tb', false);
                }
            } elseif ($p === 'jd') {
                $jd = $this->hdk->SearchJdGoods($keyword, $want, 1);
                if ($jd['code'] == 1 && !empty($jd['items'])) {
                    foreach ($jd['items'] as $item) {
                        $item['item_from'] = 'jd';
                        $byPlat['jd'][] = $item;
                    }
                    $this->platBreakerRecord('jd', true);
                } else {
                    $this->platBreakerRecord('jd', false, $jd['message'] ?? '');
                }
            } elseif ($p === 'pdd') {
                $pdd = $this->hdk->SearchPddGoods($keyword, $want, 1);
                if ($pdd['code'] == 1 && !empty($pdd['items'])) {
                    foreach ($pdd['items'] as $item) {
                        $item['item_from'] = 'pdd';
                        $byPlat['pdd'][] = $item;
                    }
                    $this->platBreakerRecord('pdd', true);
                } else {
                    $this->platBreakerRecord('pdd', false, $pdd['message'] ?? '');
                }
            } elseif ($p === 'vip') {
                $vip = $this->hdk->SearchVipGoods($keyword, $want, 1);
                if ($vip['code'] == 1 && !empty($vip['items'])) {
                    foreach ($vip['items'] as $item) {
                        $item['item_from'] = 'vip';
                        $byPlat['vip'][] = $item;
                    }
                    $this->platBreakerRecord('vip', true);
                } else {
                    $this->platBreakerRecord('vip', false, $vip['message'] ?? '');
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

        // 预算后过滤（兜底）：对 dtk 偶尔返回的边界价、以及京东/拼多多/唯品会等不支持
        // 价格硬过滤的平台，统一在聚合结果层按价格区间收口。预算为空则跳过。
        if (($pmin > 0 || $pmax > 0) && !empty($allItems)) {
            $priceOf = function ($it) {
                return (float)($it['actualPrice'] ?? $it['zkFinalPrice'] ?? $it['finalPrice'] ?? $it['price'] ?? 0);
            };
            $inBudget = array();
            $outBudget = array();
            foreach ($allItems as $it) {
                $p = $priceOf($it);
                $ok = true;
                if ($pmin > 0 && $p > 0 && $p < $pmin) $ok = false;
                if ($pmax > 0 && $p > $pmax) $ok = false;
                if ($ok) { $inBudget[] = $it; } else { $outBudget[] = $it; }
            }
            // 预算内有货才替换；否则保留全部（避免空结果让导购"没东西")
            if (count($inBudget) >= 1) {
                $allItems = array_merge($inBudget, $outBudget);
            }
        }

        // 均衡截断 / 固定每平台数量
        if ($perPlatCap > 0) {
            // 固定格式：每平台保留 $perPlatCap 条（如导购每个平台 4 件）。
            // 空平台（无数据/权限问题）自动跳过；某平台结果不足时，有几条显示几条。
            $finalItems = [];
            $used = ['tb' => 0, 'jd' => 0, 'pdd' => 0, 'vip' => 0];
            foreach ($allItems as $it) {
                $p = $it['item_from'] ?? 'vip';
                if (!isset($used[$p])) $used[$p] = 0;
                if ($used[$p] < $perPlatCap) {
                    $finalItems[] = $it;
                    $used[$p]++;
                }
            }
        } else {
            // 均衡截断：保证每个有数据的平台都保留代表商品，避免数据量大的平台（如唯品会）
            // 淹没其他平台，实现真正的多平台比价；最终总数不超过 $pageSize。
            $presentPlats = array_values(array_unique(array_column($allItems, 'item_from')));
            $platCnt = count($presentPlats);
            if ($platCnt === 0) {
                $finalItems = array_slice($allItems, 0, $pageSize);
            } else {
                $perPlat = intval(ceil($pageSize / $platCnt));   // 每平台代表数量
                $used = array_fill_keys($presentPlats, 0);
                $finalItems = [];
                // 第一轮：每平台各取 perPlat 条（保证均衡）
                foreach ($allItems as $it) {
                    $p = $it['item_from'] ?? 'vip';
                    if ($used[$p] < $perPlat && count($finalItems) < $pageSize) {
                        $finalItems[] = $it;
                        $used[$p]++;
                    }
                }
                // 第二轮：若还有余额，按原排序（含平台优先级）补足
                if (count($finalItems) < $pageSize) {
                    foreach ($allItems as $it) {
                        if (count($finalItems) >= $pageSize) break;
                        $hit = false;
                        foreach ($finalItems as $fi) {
                            if ((($fi['item_from'] ?? '') === ($it['item_from'] ?? '')) && (($fi['goodsId'] ?? '') === ($it['goodsId'] ?? ''))) { $hit = true; break; }
                        }
                        if (!$hit) $finalItems[] = $it;
                    }
                }
            }
        }

        return [
            'code' => 1,
            'message' => 'success',
            'items' => $finalItems,
            'total' => count($finalItems),
            'debug' => [
                'tb'   => count($byPlat['tb'] ?? []),
                'jd'   => count($byPlat['jd'] ?? []),
                'pdd'  => count($byPlat['pdd'] ?? []),
                'vip'  => count($byPlat['vip'] ?? []),
                // 熔断状态：1=该平台因连续失败被临时跳过（便于排查"某平台无结果"是外部问题还是被熔断）
                'breaker' => [
                    'tb'   => $this->platBreakerOpen('tb') ? 1 : 0,
                    'jd'   => $this->platBreakerOpen('jd') ? 1 : 0,
                    'pdd'  => $this->platBreakerOpen('pdd') ? 1 : 0,
                    'vip'  => $this->platBreakerOpen('vip') ? 1 : 0,
                ],
            ],
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
     * API 结果缓存装饰器（仅用于前端展示型接口，节省外部 API 调用次数）
     *
     * 设计要点：
     *  - $key 已含参数维度（page/cid/rankType 等），不同参数独立缓存；
     *  - $ttl 秒级过期，到点自动失效（CacheService 原生支持）；
     *  - 失败兜底：API 抛异常或返回 code!=1 时，若已有旧缓存则**返回旧数据**（保证前端不崩、SEO 友好），
     *    绝不返回空数组误导页面；
     *  - 实时查询（searchGoods/getGoodsDetail/getPrivilegeLink）不走此方法。
     *
     * @param string   $key      缓存键（建议前缀 api_）
     * @param int      $ttl      过期秒数
     * @param callable $callback 真正调用 API 的闭包，需返回数组且含 'code' 键
     * @return array
     */
    /**
     * 平台熔断：某平台连续失败达到阈值后，冷却期内跳过该平台搜索请求。
     *
     * 背景：拼多多 duoId 异常被封、京东 pid 未配置等外部问题会长期存在，
     * 每次聚合搜索仍逐个发起 HTTP 请求并等待超时，显著拖慢响应（尤其 AI 助手场景）。
     * 熔断仅作用于 searchAllPlatforms 的旁路平台，主搜淘宝不参与熔断（保证主链路可用）。
     *
     * 状态存于缓存，键：tjk_breaker_{platform}，值：['fails'=>连续失败次数, 'until'=>冷却截止时间戳]
     */
    private static $BREAKER_FAILS  = 3;      // 连续失败几次后熔断
    private static $BREAKER_COOLDOWN = 600;  // 熔断冷却秒数（10 分钟）

    private function breakerKey($platform) {
        return 'tjk_breaker_' . preg_replace('/[^a-z0-9_]/i', '', (string) $platform);
    }

    /**
     * 判断某平台是否处于熔断冷却期（淘宝 tb 永不熔断，保证主搜索链路）
     */
    private function platBreakerOpen($platform) {
        if ($platform === 'tb') {
            return false;
        }
        try {
            $st = CacheService::instance(self::$BREAKER_COOLDOWN)->get($this->breakerKey($platform));
            if (!is_array($st)) {
                return false;
            }
            return !empty($st['until']) && time() < intval($st['until']);
        } catch (\Throwable $e) {
            return false; // 缓存异常不阻断主流程
        }
    }

    /**
     * 记录一次平台搜索结果：成功即清零，失败累加并在达阈值时打开熔断。
     *
     * 对"配额耗尽/被限流"这类**确定性**外部故障（当日不会再恢复），
     * 直接一次即熔断，避免每个用户请求都白打一次 API。
     *
     * @param string $platform 平台
     * @param bool   $ok       是否成功
     * @param string $msg      失败原因（用于识别配额类故障）
     */
    private function platBreakerRecord($platform, $ok, $msg = '') {
        if ($platform === 'tb') {
            return;
        }
        try {
            $cache = CacheService::instance(self::$BREAKER_COOLDOWN);
            $key   = $this->breakerKey($platform);
            $st    = $cache->get($key);
            $fails = (is_array($st) ? intval($st['fails'] ?? 0) : 0);
            if ($ok) {
                $fails = 0;
            } else {
                // 配额/限流类故障：一次即熔断（确定性故障，重试无意义）
                $hard = (strpos((string) $msg, '上限') !== false)
                    || (strpos((string) $msg, '限流') !== false)
                    || (stripos((string) $msg, 'quota') !== false)
                    || (stripos((string) $msg, 'exceed') !== false);
                $fails = $hard ? self::$BREAKER_FAILS : $fails + 1;
            }
            $new = [
                'fails' => $fails,
                'until' => ($fails >= self::$BREAKER_FAILS) ? (time() + self::$BREAKER_COOLDOWN) : 0,
            ];
            $cache->set($key, $new, self::$BREAKER_COOLDOWN);
        } catch (\Throwable $e) {
            // 熔断状态写入失败不影响业务
        }
    }

    private function apiCache($key, $ttl, $callback) {
        $cache  = CacheService::instance($ttl);
        $cached = $cache->get($key);
        if ($cached !== null && $cached !== false) {
            return $cached;
        }
        try {
            $result = $callback();
        } catch (\Throwable $e) {
            // API 异常：有旧缓存则返回旧，否则原样返回空结构（由上层决定）
            $old = $cache->get($key);
            if ($old !== null && $old !== false) {
                return $old;
            }
            return ['code' => 0, 'message' => 'API请求异常：' . $e->getMessage()];
        }
        // 仅当 API 正常返回（code 存在，兼容 1/200/y 等成功态）才写入缓存；
        // 失败结果不缓存，下次请求仍打 API 重试。
        $ok = isset($result['code']) ? $result['code'] : 0;
        $isOk = ($ok == 1 || $ok == 200 || $ok === 'y' || $ok === true);
        if ($isOk) {
            $cache->set($key, $result, $ttl);
        } else {
            // 返回失败但有旧缓存，降级返回旧缓存
            $old = $cache->get($key);
            if ($old !== null && $old !== false) {
                return $old;
            }
        }
        return $result;
    }

    public function getGoodsList($pageSize = 50, $pageId = '1', $extra = [], $nocache = false) {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置', 'items' => [], 'total' => 0];
        }
        // 后台联盟列表 / 实时场景传 $nocache=true 跳过缓存；前台列表/首页默认走缓存
        if ($nocache) {
            return $this->dtk->GetGoodsList($pageSize, $pageId, $extra);
        }
        $key = 'api_goodslist_' . md5(json_encode([$pageSize, $pageId, $extra]));
        return $this->apiCache($key, 600, function () use ($pageSize, $pageId, $extra) {
            return $this->dtk->GetGoodsList($pageSize, $pageId, $extra);
        });
    }
    
    public function getBrandList($pageSize = 50, $pageId = '1', $cid = '') {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置', 'total' => 0, 'pageId' => '', 'brands' => []];
        }
        $key = 'api_brandlist_' . md5(json_encode([$pageSize, $pageId, $cid]));
        return $this->apiCache($key, 1800, function () use ($pageSize, $pageId, $cid) {
            return $this->dtk->GetBrandColumnList($pageSize, $pageId, $cid);
        });
    }
    
    public function getBrandGoods($brandId, $pageSize = 50, $pageId = '1') {
        if (!$this->dtk) {
            return ['code' => 0, 'message' => '大淘客API未配置', 'total' => 0, 'pageId' => '', 'goods' => [], 'brandInfo' => []];
        }
        $key = 'api_brandgoods_' . md5(json_encode([$brandId, $pageSize, $pageId]));
        return $this->apiCache($key, 600, function () use ($brandId, $pageSize, $pageId) {
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
        });
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
        $key = 'api_ranking_' . md5(json_encode([$rankType, $cid, $pageSize, $pageId]));
        return $this->apiCache($key, 900, function () use ($rankType, $cid, $pageSize, $pageId) {
            return $this->dtk->GetRankingList($rankType, $cid, $pageSize, $pageId);
        });
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
        // 线报属展示型内容，缓存 5 分钟（首页/线报页高频访问，避免重复打 API）
        $key = 'api_tipoff_' . md5(json_encode([$pageId, $pageSize, $topic, $platform]));
        return $this->apiCache($key, 300, function () use ($pageId, $pageSize, $topic, $platform) {
            return $this->dtk->GetTipOff($pageId, $pageSize, $topic, $platform);
        });
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
        // ---- 好单库(HDK)字段别名兜底：字段名与大淘客差异大，逐项归一化 ----
        if (empty($out['goodsId']))     $out['goodsId']     = $item['itemid'] ?? '';
        if (empty($out['title']))       $out['title']       = $item['goodsname'] ?? $item['itemtitle'] ?? '';
        if (empty($out['mainPic']))     $out['mainPic']     = $item['goodsimage'] ?? $item['itempic'] ?? $item['taobao_image'] ?? '';
        if (empty($out['content']))     $out['content']     = $item['desc'] ?? $item['itemdesc'] ?? '';
        if (empty($out['actualPrice'])) $out['actualPrice'] = $item['itemprice'] ?? $item['itemendprice'] ?? $item['price'] ?? 0;
        if (empty($out['originalPrice'])) $out['originalPrice'] = $item['itemprice'] ?? $item['originalprice'] ?? $item['price'] ?? 0;
        if (empty($out['couponPrice'])) $out['couponPrice'] = $item['couponmoney'] ?? $item['couponprice'] ?? 0;
        if (empty($out['monthSales']))  $out['monthSales']  = $item['itemsale'] ?? $item['sales'] ?? 0;
        if (empty($out['couponConditions'])) $out['couponConditions'] = $item['couponinfo'] ?? $item['min_buy'] ?? '';
        if (empty($out['couponStartTime']) || $out['couponStartTime'] == '0') $out['couponStartTime'] = $item['couponstarttime'] ?? '0';
        if (empty($out['couponEndTime'])   || $out['couponEndTime']   == '0') $out['couponEndTime']   = $item['couponendtime']   ?? '0';
        if (empty($out['shopName']))    $out['shopName']    = $item['shopname'] ?? $item['sellernick'] ?? '';
        if (empty($out['couponLink']))  $out['couponLink']  = $item['couponurl'] ?? $item['coupon_share_url'] ?? '';
        // 商品原始链接兜底：转链失败时用它能保证用户至少可打开商品页。
        // 各平台字段名差异大，需覆盖大淘客(clickurl/couponurl)与好单库(itemurl/goods_url/itemendurl)。
        if (empty($out['itemLink']))    $out['itemLink']    = $item['clickurl'] ?? $item['itemurl'] ?? $item['goods_url'] ?? $item['itemendurl'] ?? $item['couponurl'] ?? '';
        // 券链接兜底（好单库部分接口只回 coupon_share_url / couponurl）
        if (empty($out['couponLink']))  $out['couponLink']  = $item['coupon_share_url'] ?? $item['couponurl'] ?? '';
        if (empty($out['commissionRate'])) $out['commissionRate'] = $item['income_rate'] ?? $item['tkmoney'] ?? 0;
        if (empty($out['estimateAmount'])) $out['estimateAmount'] = $item['income_info'] ?? 0;
        // 店铺类型归一化（好单库 shoptype：1=天猫 2=淘宝）
        if (empty($out['shopType']) && isset($item['shoptype'])) {
            $st = intval($item['shoptype']);
            $out['shopType'] = ($st == 1) ? 1 : 0;
        }
        // 好单库 subcid 可能是字符串数组 JSON，统一转数组再回 JSON 字符串
        if (isset($item['son_category']) && empty($out['subcid'])) {
            $out['subcid'] = is_array($item['son_category'])
                ? json_encode($item['son_category'], JSON_UNESCAPED_UNICODE)
                : $item['son_category'];
        }
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
            'taobao' => 'tb', 'tb' => 'tb', 'dtk' => 'tb', 'haodanku' => 'tb', 'hdk' => 'tb',
            'jd' => 'jd', 'j d' => 'jd',
            'pdd' => 'pdd', 'pinduoduo' => 'pdd',
            'vip' => 'vip', 'vipshop' => 'vip', 'wph' => 'vip',
        );
        $out['item_from'] = $srcMap[strtolower(trim($src))] ?? strtolower(trim($src));
        return $out;
    }

    // ==================== 商品相关性二次过滤 ====================
    // 产品族 → 主商品词（用于识别"用户搜的是哪类主商品"）
    private static $FAMILY_MAIN = array(
        'shoe'   => array('高跟鞋','运动鞋','跑鞋','篮球鞋','板鞋','帆布鞋','休闲鞋','皮鞋','马丁靴','靴子','雪地靴','凉鞋','拖鞋','豆豆鞋','乐福鞋','老爹鞋','平底鞋','单鞋','小白鞋','增高鞋','跳舞鞋','妈妈鞋','婚鞋','松糕鞋','坡跟鞋','牛津鞋','穆勒鞋','女鞋','男鞋','童鞋','雨鞋','洞洞鞋','玛丽珍鞋','芭蕾鞋','切尔西靴','工装鞋','登山鞋','徒步鞋','健步鞋','一脚蹬','渔夫鞋','罗马鞋','尖头鞋','圆头鞋','方头鞋','凉拖','棉鞋','板鞋'),
        'phone'  => array('手机','智能手机','苹果手机','安卓手机','老人机','功能机','游戏手机','iphone','华为','小米','红米','oppo','vivo','三星','荣耀','一加','realme','魅族','中兴','努比亚','苹果'),
        'laptop' => array('笔记本','笔记本电脑','电脑','平板','平板电脑','显示器','台式机','游戏本','轻薄本','一体机','二合一','上网本','主机'),
        'bag'    => array('背包','双肩包','单肩包','手提包','女包','男包','斜挎包','行李箱','拉杆箱','旅行箱','钱包','公文包','妈咪包','书包','胸包','腰包','手包','托特包','水桶包','链条包','包'),
        'watch'  => array('手表','智能手表','手环','腕表','机械表','石英表','电子表','儿童手表','运动手表'),
        'camera' => array('相机','微单','单反','摄像机','运动相机','拍立得','数码相机','胶片相机'),
    );
    // 产品族 → 配件/周边词（这些都是"非主商品"的周边，搜主商品时应剔除）
    private static $FAMILY_ACCESSORIES = array(
        'shoe'   => array('鞋垫','鞋套','鞋油','鞋刷','鞋带','鞋扣','鞋撑','鞋拔','鞋罩','鞋袋','鞋盒','后跟贴','前掌垫','半码垫','全掌垫','增高鞋垫','按摩鞋垫','鞋花','鞋链','鞋钻','鞋楦','鞋夹','鞋贴','鞋内垫','鞋底','鞋掌','鞋钉','鞋带扣','鞋带孔','鞋眼'),
        'phone'  => array('手机壳','手机套','保护套','保护壳','钢化膜','手机膜','贴膜','充电器','数据线','充电线','充电头','快充头','无线充','耳机','手机支架','防尘塞','手机挂绳','手机包','镜头膜','手机环','指环扣','手机指环','磁吸环','手机壳子','软壳','硬壳','透明壳'),
        'laptop' => array('键盘','鼠标','电脑包','内胆包','笔记本贴膜','扩展坞','电源适配器','鼠标垫','电脑支架','屏幕膜','键盘膜','笔记本支架','电脑贴纸','防窥膜','理线器','集线器','转接器','数据线','充电器','触控板','腕托','摄像头'),
        'bag'    => array('包中包','收纳袋','防尘袋','包带','包链','包挂','行李牌','密码锁','箱套','包扣','包夹','包饰','包吊坠','内胆包'),
        'watch'  => array('表带','表膜','表壳','表扣','表盒','表链','表镜','充电底座','表托','表带圈','表针','表冠','表把','表圈','表盘贴','手表膜','表带扣','表带节'),
        'camera' => array('相机包','镜头','内存卡','存储卡','sd卡','电池','充电器','三脚架','滤镜','相机带','相机贴膜','屏幕贴','相机清洁','读卡器','闪光灯','相机手柄','快门线','遮光罩'),
    );

    /**
     * 商品相关性二次过滤：联盟 API 商品标题常 SEO 堆砌关键词
     * （如搜"高跟鞋"却返回"真皮鞋垫…高跟鞋"），这些商品虽标题含关键词，
     * 但并非用户所求的主商品。本函数按"产品族"剔除核心商品是该族配件/周边的条目。
     *
     * 判定（保守，宁放过不误杀）：
     *  1. 由搜索词识别所属产品族（鞋/手机/电脑/箱包/手表/相机…）；
     *  2. 若标题里根本没有本族配件词 → 直接保留（肯定是主商品）；
     *  3. 若标题只有本族配件词、没有主商品词 → 整条就是配件 → 剔除；
     *  4. 若配件词最靠前出现的位置 <= 主商品词最靠前出现的位置 → 核心商品是配件 → 剔除；
     *  5. 过滤后若为空，回退原始列表，保证有结果。
     *
     * @param array  $items   标准化后的商品数组（含 title 字段）
     * @param string $keyword 用户搜索关键词（用于识别产品族）
     * @return array 过滤后的商品
     */
    public static function filterRelevantItems(array $items, string $keyword): array
    {
        if (empty($items) || $keyword === '') {
            return $items;
        }
        $family = self::detectProductFamily($keyword);
        if ($family === null) {
            return $items; // 未知产品族，不过滤，避免误杀
        }
        $accTerms  = self::$FAMILY_ACCESSORIES[$family];
        $mainTerms = self::$FAMILY_MAIN[$family];

        $keep = array();
        foreach ($items as $it) {
            $title = (string) ($it['title'] ?? ($it['name'] ?? ''));
            if ($title === '') {
                $keep[] = $it;
                continue;
            }
            // 不依赖 mbstring：stripos/strtolower 对 UTF-8 中文子串匹配同样有效（仅 ASCII 大小写无关）
            $low = strtolower($title);

            // 收集所有配件词命中的区间 [start, end)
            $accSpans = array();
            foreach ($accTerms as $t) {
                $p = stripos($low, $t);
                if ($p !== false) {
                    $accSpans[] = array($p, $p + strlen($t));
                }
            }
            if (empty($accSpans)) {
                // 标题里根本没有本族配件词 → 肯定是主商品
                $keep[] = $it;
                continue;
            }

            // 主商品词位置：排除与配件词区间重叠/被包含的命中
            // （如"真皮鞋垫"里误命中"皮鞋"——皮(真皮)与鞋(鞋垫)相邻形成重叠，必须剔除，
            //  否则会把"鞋垫"误判成主商品在前而漏过滤）
            $mainPos = null;
            foreach ($mainTerms as $t) {
                $p = stripos($low, $t);
                if ($p === false) {
                    continue;
                }
                $s = $p;
                $e = $p + strlen($t);
                $overlap = false;
                foreach ($accSpans as $am) {
                    if ($s < $am[1] && $e > $am[0]) {
                        $overlap = true;
                        break;
                    }
                }
                if ($overlap) {
                    continue;
                }
                if ($mainPos === null || $p < $mainPos) {
                    $mainPos = $p;
                }
            }

            $accPos = null;
            foreach ($accSpans as $am) {
                if ($accPos === null || $am[0] < $accPos) {
                    $accPos = $am[0];
                }
            }

            if ($mainPos === null) {
                // 只有配件词、没有独立的主商品词 → 整条就是配件
                continue;
            }
            // 配件词出现在主商品词之前（或同位置）→ 核心商品是配件
            if ($accPos <= $mainPos) {
                continue;
            }
            $keep[] = $it;
        }

        // 保守兜底：过滤后若空了，退回原始，保证有结果
        return $keep !== array() ? $keep : $items;
    }

    private static function detectProductFamily(string $keyword): ?string
    {
        $kw = strtolower($keyword);
        foreach (self::$FAMILY_MAIN as $fam => $terms) {
            foreach ($terms as $t) {
                if (stripos($kw, $t) !== false) {
                    return $fam;
                }
            }
        }
        return null;
    }

}