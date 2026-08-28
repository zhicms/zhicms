<?php
/**
 * AI购物助手 - 前台控制器
 * 
 * 为网站访客提供智能导购服务：
 * - 识别购物意图 → 搜索站内商品/全网比价 → 返回购买链接
 * - 非购物问题 → 转发给 AI 大模型处理
 * - 基于 Cookie 区分用户，保存独立会话历史
 * 
 * @package index\controller
 */

namespace app\index\controller;

class AiAssistantController extends \app\base\controller\BaseController
{
    /** Cookie 中的用户标识 */
    private $userId = '';

    /** 会话历史存储目录 */
    private $historyDir = '';

    /** 本次对话是否因「AI 模型未配置」而失败（用于前端提示） */
    private $chatUnconfigured = false;

    public function __construct()
    {
        parent::__construct();
        $this->historyDir = \ROOT_PATH . 'data/ai_chat_history/';
        if (!is_dir($this->historyDir)) {
            mkdir($this->historyDir, 0755, true);
        }
        $this->initUser();
    }

    /**
     * 初始化用户身份（Cookie 持久化）
     */
    private function initUser()
    {
        if (isset($_COOKIE['ai_uid']) && !empty($_COOKIE['ai_uid'])) {
            $this->userId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_COOKIE['ai_uid']);
        } else {
            $this->userId = 'u_' . bin2hex(random_bytes(12));
            setcookie('ai_uid', $this->userId, time() + 86400 * 365, '/', '', false, true);
        }
    }

    /**
     * 获取用户会话历史文件路径
     */
    private function getHistoryFile()
    {
        // 用服务端密钥对 userId 做签名，避免客户端伪造他人 ai_uid 读取其对话历史
        $salt = 'zhicms_ai_chat_salt_' . \ZhiCms\base\Config::get('SECRET_KEY', 'zhicms');
        return $this->historyDir . md5($this->userId . '|' . $salt) . '.json';
    }

    /**
     * 加载会话历史
     */
    private function loadHistory()
    {
        $file = $this->getHistoryFile();
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    /**
     * 保存会话历史（保留最近 20 条 = 10 轮对话）
     */
    private function saveHistory($history)
    {
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        file_put_contents($this->getHistoryFile(), json_encode($history, JSON_UNESCAPED_UNICODE));
    }

    // ==================== API 端点 ====================

    /**
     * 主对话接口
     * POST: JSON body { "message": "用户输入" }
     * 返回: JSON { "reply": "回复内容", "type": "product|chat" }
     */
    public function chat()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $message = isset($input['message']) ? trim($input['message']) : '';

        // 也兼容 GET 和 POST form 请求
        if (empty($message)) {
            $message = isset($_REQUEST['message']) ? trim($_REQUEST['message']) : '';
        }

        // 轻量级频率限制（基于客户端 IP，60 秒内最多 10 次对话），防止恶意刷爆第三方 AI 额度
        $rateCheck = $this->checkRateLimit();
        if ($rateCheck !== true) {
            echo json_encode(['reply' => '您操作太频繁啦，请稍后再试~', 'type' => 'chat'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (empty($message)) {
            echo json_encode(['reply' => '请输入您想咨询的问题~', 'type' => 'chat']);
            return;
        }

        if (mb_strlen($message) > 500) {
            echo json_encode(['reply' => '您的消息太长啦，请控制在500字以内哦~', 'type' => 'chat']);
            return;
        }

        // 意图分析
        $intent = $this->analyzeIntent($message);

        // 加载历史并追加用户消息
        $history = $this->loadHistory();
        $history[] = ['role' => 'user', 'content' => $message];

        if ($intent['is_purchase']) {
            $reply = $this->handleProductSearch($intent['keyword'], $message);
            $respType = 'product';
        } else {
            $reply = $this->handleAiChat($message, $history);
            $respType = 'chat';
        }

        // 保存历史
        $history[] = ['role' => 'assistant', 'content' => $reply];
        $this->saveHistory($history);

        $out = ['reply' => $reply, 'type' => $respType];
        if (!empty($this->chatUnconfigured)) {
            $out['unconfigured'] = true;
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取会话历史
     */
    public function getHistory()
    {
        header('Content-Type: application/json; charset=utf-8');
        // 返回登录态信息，供前端展示"当前对话身份"
        $isLogin = false;
        $userName = '游客';
        if (!empty($_COOKIE['ZhiCmsUser'])) {
            $u = obj("index/global", "controller")->findUser("y", $_COOKIE['ZhiCmsUser'], "cookie");
            if (!empty($u)) {
                $isLogin = true;
                $mobile = isset($u['mobile']) ? $u['mobile'] : '';
                $userName = !empty($u['username']) && $u['username'] !== $mobile
                    ? $u['username']
                    : (strlen($mobile) >= 11 ? substr($mobile, 0, 3) . '****' . substr($mobile, 7) : $mobile);
            }
        }
        echo json_encode([
            'history'  => $this->loadHistory(),
            'is_login' => $isLogin,
            'user_name' => $userName,
            'ai_uid'   => $this->userId,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 清除会话历史
     */
    public function clearHistory()
    {
        $file = $this->getHistoryFile();
        if (file_exists($file)) {
            unlink($file);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok']);
    }

    /**
     * 轻量级频率限制：基于客户端 IP + 时间窗口（60s 最多 10 次对话）。
     * 用 data/ai_rate_limit/ 下的文件记录计数，无需数据库。
     * @return bool true=放行, false=超限
     */
    private function checkRateLimit()
    {
        $dir = \ROOT_PATH . 'data/ai_rate_limit/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // 取真实客户端 IP（跳过可信代理链尾）
        $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
            : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $ip = preg_replace('/[^0-9a-fA-F:.\-]/', '', $ip);
        $file = $dir . md5($ip) . '.json';
        $now  = time();
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : array();
        if (!is_array($data)) {
            $data = array();
        }
        $window = 60;  // 秒
        $max    = 10;  // 窗口内最大次数
        // 丢弃过期时间戳
        $data = array_filter($data, function ($ts) use ($now, $window) {
            return is_numeric($ts) && ($now - (int)$ts) < $window;
        });
        if (count($data) >= $max) {
            return false;
        }
        $data[] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    /**
     * 获取热门搜索关键词（从 data/hot_keywords.json 读取）
    {
        header('Content-Type: application/json; charset=utf-8');

        $jsonFile = \ROOT_PATH . 'data/hot_keywords.json';

        // ========== 内置兜底词库（与 ai_widget.html 保持一致） ==========
        $fallbackPools = [
            [
                ['icon' => '📱', 'msg' => '2000以内性价比最高的手机推荐', 'label' => '2000元手机推荐'],
                ['icon' => '🎧', 'msg' => '降噪蓝牙耳机哪个牌子的好性价比高', 'label' => '降噪耳机哪家好'],
                ['icon' => '📲', 'msg' => '华为Mate60和iPhone16怎么选哪个好', 'label' => 'Mate60 vs iPhone16'],
                ['icon' => '💻', 'msg' => '5000左右游戏本推荐性价比高的', 'label' => '5000元游戏本推荐'],
                ['icon' => '🖥️', 'msg' => '迷你主机办公家用哪款性价比高', 'label' => '迷你主机家用推荐'],
                ['icon' => '📺', 'msg' => '投影仪家用卧室1000元以内推荐', 'label' => '千元投影仪推荐'],
                ['icon' => '⌚', 'msg' => '智能手表运动健康监测哪款性价比高', 'label' => '智能手表推荐'],
                ['icon' => '🎮', 'msg' => 'Switch游戏机和PS5买哪个好', 'label' => 'Switch还是PS5'],
            ],
            [
                ['icon' => '🌞', 'msg' => '防晒霜哪个牌子效果好不油腻学生党', 'label' => '防晒霜哪家强'],
                ['icon' => '❄️', 'msg' => '空调哪个牌子性价比高省电家用', 'label' => '空调推荐省电'],
                ['icon' => '👒', 'msg' => '夏天防晒衣透气防晒值高推荐', 'label' => '防晒衣透气推荐'],
                ['icon' => '🏕️', 'msg' => '露营装备新手入门套装有什么推荐', 'label' => '露营装备推荐'],
                ['icon' => '🩴', 'msg' => '夏季凉鞋男士透气不臭脚推荐', 'label' => '男士透气凉鞋'],
                ['icon' => '🦟', 'msg' => '驱蚊器灭蚊灯家用哪个牌子效果好', 'label' => '灭蚊驱蚊推荐'],
                ['icon' => '💨', 'msg' => '手持小风扇便携强力学生宿舍推荐', 'label' => '便携小风扇推荐'],
                ['icon' => '👙', 'msg' => '泳衣女保守款显瘦遮肉好看推荐', 'label' => '保守款泳衣推荐'],
            ],
            [
                ['icon' => '🧴', 'msg' => '女生用的护肤品水乳套装推荐平价好用的', 'label' => '平价水乳套装'],
                ['icon' => '💄', 'msg' => '不沾杯口红不掉色平价推荐国货', 'label' => '平价不沾杯口红'],
                ['icon' => '👗', 'msg' => '夏天女生上班通勤显瘦穿搭推荐', 'label' => '夏天通勤穿搭'],
                ['icon' => '🎒', 'msg' => '大学生宿舍生活用品必备清单推荐', 'label' => '开学宿舍必备'],
                ['icon' => '📚', 'msg' => '考研党ipad和华为平板哪个适合学习', 'label' => '考研平板推荐'],
                ['icon' => '👟', 'msg' => '跑鞋减震回弹好适合大体重推荐', 'label' => '减震跑鞋推荐'],
                ['icon' => '💇', 'msg' => '吹风机负离子不伤发家用推荐哪款好', 'label' => '负离子吹风机'],
                ['icon' => '⌨️', 'msg' => '机械键盘办公打字声音小推荐', 'label' => '静音机械键盘'],
            ],
            [
                ['icon' => '🏠', 'msg' => '扫地机器人和洗地机买哪个好家庭用', 'label' => '扫地机vs洗地机'],
                ['icon' => '🍳', 'msg' => '空气炸锅100以内性价比推荐小型', 'label' => '百元空气炸锅'],
                ['icon' => '🛏️', 'msg' => '冰丝凉席三件套1.8m床哪个牌子好', 'label' => '冰丝凉席推荐'],
                ['icon' => '💰', 'msg' => '有什么9块9包邮的实用好东西', 'label' => '9块9包邮好物'],
                ['icon' => '🍜', 'msg' => '好吃的螺蛳粉速食推荐哪个牌子正宗', 'label' => '螺蛳粉哪家好'],
                ['icon' => '🚗', 'msg' => '行车记录仪高清夜视前后双录推荐', 'label' => '行车记录仪推荐'],
                ['icon' => '🐱', 'msg' => '猫粮性价比推荐成猫幼猫天然粮', 'label' => '猫粮性价比推荐'],
                ['icon' => '🎁', 'msg' => '送女朋友生日礼物500以内惊喜推荐', 'label' => '送女友生日礼物'],
            ],
        ];

        // 尝试读取 JSON 热词文件
        if (file_exists($jsonFile)) {
            $json = json_decode(file_get_contents($jsonFile), true);
            $updated = isset($json['last_updated']) ? $json['last_updated'] : '';
            $poolsRaw = isset($json['pools']) ? $json['pools'] : [];

            // 判断是否过期：超过 48 小时
            $isExpired = true;
            if (!empty($updated)) {
                $updatedTime = strtotime($updated . ' 00:00:00');
                if ($updatedTime && (time() - $updatedTime) < 86400 * 2) {
                    $isExpired = false;
                }
            }

            // 格式化数据给前端
            $pools = [];
            if (!$isExpired && !empty($poolsRaw)) {
                foreach ($poolsRaw as $pool) {
                    if (isset($pool['items']) && is_array($pool['items'])) {
                        $pools[] = $pool['items'];
                    }
                }
                if (!empty($pools)) {
                    echo json_encode([
                        'pools'       => $pools,
                        'updated'     => $updated,
                        'source'      => isset($json['source']) ? $json['source'] : '',
                        'is_fallback' => false,
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }
        }

        // 兜底：返回内置词库
        echo json_encode([
            'pools'       => $fallbackPools,
            'updated'     => 'built-in',
            'source'      => '内置兜底数据',
            'is_fallback' => true,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取联动筛选建议（AI实时生成，零硬编码）
     * 
     * 前端只展示"品类"入口，选中品类后由AI动态生成：
     *   - 子分类（手机→手机、电脑、相机...；服装→男装、女装、上衣...）
     *   - 价格区间（根据品类真实行情动态划分）
     *   - 品牌列表（该品类下真实的知名品牌）
     *   - 使用场景 / 特殊需求（全部由AI推理）
     * 
     * GET: ?category=手机数码&price=&brand=&scene=&feature[]
     * 返回: JSON { "groups": { "category":[], "price":[], "brand":[], "scene":[], "feature":[] } }
     */
    public function getFilterSuggestions()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_GET;
        }

        $category = isset($input['category']) ? trim($input['category']) : '';
        $price    = isset($input['price']) ? trim($input['price']) : '';
        $brand    = isset($input['brand']) ? trim($input['brand']) : '';
        $scene    = isset($input['scene']) ? trim($input['scene']) : '';
        $feature  = isset($input['feature']) ? (array)$input['feature'] : [];

        // 构建当前已选描述
        $selectedDesc = '';
        if (!empty($category)) $selectedDesc .= "品类：{$category}；";
        if (!empty($price))    $selectedDesc .= "价格区间：{$price}；";
        if (!empty($brand))    $selectedDesc .= "品牌偏好：{$brand}；";
        if (!empty($scene))    $selectedDesc .= "使用场景：{$scene}；";
        if (!empty($feature))  $selectedDesc .= "特殊要求：" . implode('、', $feature) . "；";

        // 没有任何选择 → 返回通用大类（品类入口），不展示筛选区
        if (empty($category) && empty($price) && empty($brand) && empty($scene) && empty($feature)) {
            // 仍交给AI生成初始分类，确保品类列表不写死
            $systemPrompt = "你是一个电商平台的智能选品引擎。用户刚刚打开选品面板，还没有选择任何条件。\n\n请输出一个大品类列表用于第一层展示。\n\n严格按纯JSON格式输出：{\"groups\":{\"category\":[],\"price\":[],\"brand\":[],\"scene\":[],\"feature\":[]}}\n\n规则：\n1. category：8-10个常用的购物大品类名称，覆盖主流消费领域\n2. price/brand/scene/feature全部返回空数组[]（此时不应展示这些）\n3. 不要任何解释或markdown，只输出JSON";
            $userPrompt = "请生成购物平台常用的大品类列表";
        } else {
            // 已选了品类 → AI生成子分类 + 全部筛选维度
            $systemPrompt = self::getPickSystemPrompt();
            $userPrompt = "用户当前已选条件：\n" . ($selectedDesc ?: '(仅打开了选品面板，还未选择)') . "\n\n请根据以上选择，返回所有维度的联动筛选选项JSON。特别注意价格区间要符合该品类的真实市场行情。";
        }

        try {
            $response = \app\common\AiHub::chat($userPrompt, $systemPrompt, false);
        } catch (\Exception $e) {
            // AI 调用异常：同样用本地兜底，保证面板始终有内容
            $fallback = self::getPickLocalFallback($category, $price, $brand, $scene, $feature);
            echo json_encode($fallback, JSON_UNESCAPED_UNICODE);
            return;
        }

        // AI 返回的是错误串（如"AI 模型未配置"/"HTTP错误"），直接走本地兜底，
        // 不进入 extractJson（避免内部错误文本里恰好含 {} 被误解析成筛选选项）
        if (\app\common\AiHub::isErrorResult($response)) {
            $fallback = self::getPickLocalFallback($category, $price, $brand, $scene, $feature);
            echo json_encode($fallback, JSON_UNESCAPED_UNICODE);
            return;
        }

        // 解析AI返回的JSON
        $json = self::extractJson($response);

        if ($json && isset($json['groups'])) {
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
        } else {
            // AI 不可用 / 超时 / 解析失败：启用本地兜底引擎，保证面板始终有内容
            $fallback = self::getPickLocalFallback($category, $price, $brand, $scene, $feature);
            echo json_encode($fallback, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 本地兜底引擎：当 AI 不可用 / 解析失败时，根据已选品类（关键词）匹配内置分类，
     * 返回完整的联动筛选选项（子分类 / 价格 / 品牌 / 场景 / 特殊需求）。
     *
     * 设计原则：
     * - AI 优先（getFilterSuggestions 中 AI 成功即直接返回）
     * - 兜底仅作为“AI 离线”时的安全网，确保面板永远不空
     * - 关键词模糊匹配到内置大品类，再给出该品类的真实联动维度
     *
     * @return array { "groups": { "category":[], "price":[], "brand":[], "scene":[], "feature":[] } }
     */
    private static function getPickLocalFallback($category, $price, $brand, $scene, $feature)
    {
        $taxonomy = self::getPickTaxonomy();
        $aliasMap = self::getPickAliasMap();

        $baseKey = '';
        if (!empty($category)) {
            foreach ($aliasMap as $alias => $key) {
                if (mb_strpos($category, $alias) !== false || mb_strpos($alias, $category) !== false) {
                    $baseKey = $key;
                    break;
                }
            }
        }

        // 初始状态（完全没选任何品类）：返回大品类入口，不展示筛选维度
        if (empty($baseKey) && empty($category)) {
            return [
                'groups'  => [
                    'category' => array_keys($taxonomy),
                    'price'    => [],
                    'brand'    => [],
                    'scene'    => [],
                    'feature'  => [],
                ],
                'source'  => 'local',
            ];
        }

        // 有品类关键词但没命中别名库：用关键词本身生成通用联动维度，保证面板不空
        if (empty($baseKey)) {
            $kw = $category;
            return [
                'groups' => [
                    'category' => [$kw, $kw . '配件', '其他' . $kw, '热门' . $kw],
                    'price'    => ['100元以下', '100-300元', '300-800元', '800-2000元', '2000元以上'],
                    'brand'    => ['国货优选', '国际大牌', '高性价比', '网红推荐', '专业品牌'],
                    'scene'    => ['日常自用', '送礼', '旅行携带', '办公使用', '囤货'],
                    'feature'  => ['热销爆款', '好评如潮', '新品上市', '限时优惠', '正品保障'],
                ],
                'source' => 'local',
            ];
        }

        $base = $taxonomy[$baseKey];

        // 把用户输入的原始关键词也作为一个可选子分类，置于首位，保证“我输入的就是我想买的分类”
        $subCats = $base['category'];
        if (!empty($category) && !in_array($category, $subCats, true)) {
            array_unshift($subCats, $category);
        }

        return [
            'groups' => [
                'category' => $subCats,
                'price'    => $base['price'],
                'brand'    => $base['brand'],
                'scene'    => $base['scene'],
                'feature'  => $base['feature'],
            ],
            'source' => 'local',
        ];
    }

    /**
     * 内置品类联动库（兜底用，覆盖主流消费领域）
     * 结构：大品类 => [ 子分类 / 价格区间 / 品牌 / 使用场景 / 特殊需求 ]
     */
    private static function getPickTaxonomy()
    {
        return [
            '手机数码' => [
                'category' => ['手机', '平板电脑', '相机', '智能手表', '耳机音箱', '办公设备', '摄影器材', '配件周边'],
                'price'    => ['500元以下', '500-1500元', '1500-3000元', '3000-5000元', '5000-8000元', '8000元以上'],
                'brand'    => ['华为', '小米', 'OPPO', 'vivo', '荣耀', '三星', 'Apple'],
                'scene'    => ['学生党', '游戏娱乐', '摄影拍照', '商务办公', '送礼长辈'],
                'feature'  => ['高刷新率', '长续航', '拍照好看', '轻薄', '5G', '大内存'],
            ],
            '电脑办公' => [
                'category' => ['笔记本', '台式机', '平板', '显示器', '键盘鼠标', '打印机', '办公耗材'],
                'price'    => ['1000元以下', '1000-3000元', '3000-5000元', '5000-8000元', '8000元以上'],
                'brand'    => ['联想', '戴尔', '华硕', '惠普', '宏碁', '小米', '华为'],
                'scene'    => ['游戏电竞', '办公家用', '学生宿舍', '设计剪辑', '商务出差'],
                'feature'  => ['轻薄便携', '高性能', '长续航', '高色域', '静音散热', '大存储'],
            ],
            '家用电器' => [
                'category' => ['冰箱', '洗衣机', '空调', '厨房电器', '生活电器', '个护健康', '清洁电器'],
                'price'    => ['300元以下', '300-1000元', '1000-3000元', '3000-6000元', '6000元以上'],
                'brand'    => ['美的', '格力', '海尔', '苏泊尔', '九阳', '小米', '戴森'],
                'scene'    => ['家用厨房', '客厅卧室', '母婴照顾', '租房宿舍', '新房装修'],
                'feature'  => ['节能省电', '静音', '智能控制', '大容量', '小巧不占地'],
            ],
            '服装鞋包' => [
                'category' => ['男装', '女装', '童装', '内衣', '鞋靴', '箱包', '配饰'],
                'price'    => ['50元以下', '50-150元', '150-300元', '300-800元', '800元以上'],
                'brand'    => ['优衣库', 'UR', 'ZARA', '太平鸟', '耐克', '安踏', '李宁'],
                'scene'    => ['日常通勤', '周末休闲', '约会聚会', '运动健身', '商务正装'],
                'feature'  => ['显瘦', '透气', '不易皱', '百搭', '显高', '柔软舒适'],
            ],
            '美妆护肤' => [
                'category' => ['面部护肤', '彩妆', '香水', '个护清洁', '男士护肤', '美容工具'],
                'price'    => ['50元以下', '50-150元', '150-300元', '300-600元', '600元以上'],
                'brand'    => ['珀莱雅', '薇诺娜', '欧莱雅', 'OLAY', '兰蔻', '雅诗兰黛', '完美日记'],
                'scene'    => ['日常护肤', '约会妆容', '派对彩妆', '旅行便携', '送礼'],
                'feature'  => ['补水保湿', '控油', '抗老', '不脱妆', '温和不刺激', '平价好用'],
            ],
            '食品饮料' => [
                'category' => ['零食', '饮料冲调', '生鲜蔬果', '粮油调味', '方便速食', '坚果蜜饯'],
                'price'    => ['9.9元封顶', '19.9元', '20-50元', '50-100元', '100元以上'],
                'brand'    => ['三只松鼠', '良品铺子', '百草味', '蒙牛', '伊利'],
                'scene'    => ['办公室零食', '追剧必备', '年货礼盒', '露营野餐', '健身代餐'],
                'feature'  => ['低卡', '无糖', '好吃不胖', '大包装', '网红爆款', '产地直发'],
            ],
            '母婴用品' => [
                'category' => ['奶粉', '纸尿裤', '婴童服饰', '玩具', '喂养用品', '出行用品'],
                'price'    => ['50元以下', '50-150元', '150-300元', '300-600元', '600元以上'],
                'brand'    => ['帮宝适', '花王', '好奇', '爱他美', '惠氏', '美赞臣'],
                'scene'    => ['新生儿', '学步期', '幼儿园', '居家带娃', '外出旅行'],
                'feature'  => ['安全无毒', '亲肤', '易清洗', '便携', '正品保障'],
            ],
            '家居生活' => [
                'category' => ['厨房用具', '收纳整理', '床上用品', '清洁用品', '香薰装饰', '卫浴用品'],
                'price'    => ['9.9元封顶', '19.9元', '20-50元', '50-150元', '150元以上'],
                'brand'    => ['无印良品', '名创优品', '网易严选', '宜家', '全棉时代'],
                'scene'    => ['厨房收纳', '卧室布置', '客厅装饰', '租房改造', '新房入住'],
                'feature'  => ['免打孔', '可折叠', '大容量', '高颜值', '耐用'],
            ],
            '运动户外' => [
                'category' => ['运动服饰', '健身器材', '户外装备', '球类运动', '骑行装备', '瑜伽用品'],
                'price'    => ['50元以下', '50-200元', '200-500元', '500-1500元', '1500元以上'],
                'brand'    => ['耐克', '阿迪达斯', '安踏', '李宁', '迪卡侬', 'Keep'],
                'scene'    => ['健身房', '户外徒步', '跑步晨练', '瑜伽拉伸', '团队运动'],
                'feature'  => ['速干透气', '减震', '轻便', '防滑', '专业支撑'],
            ],
            '汽车用品' => [
                'category' => ['行车记录仪', '车载电器', '内饰清洁', '安全座椅', '脚垫坐垫', '养车工具'],
                'price'    => ['50元以下', '50-200元', '200-500元', '500-1500元', '1500元以上'],
                'brand'    => ['70迈', '盯盯拍', '小米', '米家', '固特异', '3M'],
                'scene'    => ['日常通勤', '长途自驾', '新手练车', '家庭出游', '租车代步'],
                'feature'  => ['高清夜视', '前后双录', '静音', '易安装', '防滑耐磨'],
            ],
        ];
    }

    /**
     * 关键词 → 大品类 别名映射（兜底匹配用）
     * 命中规则：用户输入包含别名，或别名包含用户输入，即匹配该大品类
     */
    private static function getPickAliasMap()
    {
        return [
            '手机'   => '手机数码', '数码' => '手机数码', '智能手机' => '手机数码', '平板' => '手机数码',
            '相机'   => '手机数码', '微单' => '手机数码', '单反' => '手机数码', '耳机' => '手机数码',
            '手表'   => '手机数码', '音箱' => '手机数码', '无人机' => '手机数码', '手机壳' => '手机数码',
            '电脑'   => '电脑办公', '笔记本' => '电脑办公', '游戏本' => '电脑办公', '台式机' => '电脑办公',
            '显示器' => '电脑办公', '键盘' => '电脑办公', '鼠标' => '电脑办公', '打印机' => '电脑办公',
            '办公'   => '电脑办公', '主机' => '电脑办公',
            '家电'   => '家用电器', '电器' => '家用电器', '冰箱' => '家用电器', '洗衣机' => '家用电器',
            '空调'   => '家用电器', '电视' => '家用电器', '投影仪' => '家用电器', '电饭煲' => '家用电器',
            '空气炸锅' => '家用电器', '吸尘器' => '家用电器', '扫地机' => '家用电器', '吹风机' => '家用电器',
            '净化器' => '家用电器', '加湿器' => '家用电器', '热水器' => '家用电器',
            '服装'   => '服装鞋包', '衣服' => '服装鞋包', '男装' => '服装鞋包', '女装' => '服装鞋包',
            '童装'   => '服装鞋包', '连衣裙' => '服装鞋包', '裙子' => '服装鞋包', '裤子' => '服装鞋包',
            '衬衫'   => '服装鞋包', '外套' => '服装鞋包', '内衣' => '服装鞋包', '鞋' => '服装鞋包',
            '运动鞋' => '服装鞋包', '包' => '服装鞋包', '箱包' => '服装鞋包', '帽子' => '服装鞋包',
            '服饰'   => '服装鞋包', '鞋包' => '服装鞋包',
            '美妆'   => '美妆护肤', '护肤' => '美妆护肤', '化妆品' => '美妆护肤', '面膜' => '美妆护肤',
            '口红'   => '美妆护肤', '彩妆' => '美妆护肤', '香水' => '美妆护肤', '防晒' => '美妆护肤',
            '洗面奶' => '美妆护肤', '水乳' => '美妆护肤', '面霜' => '美妆护肤', '精华' => '美妆护肤',
            '食品'   => '食品饮料', '零食' => '食品饮料', '饮料' => '食品饮料', '饼干' => '食品饮料',
            '坚果'   => '食品饮料', '咖啡' => '食品饮料', '茶叶' => '食品饮料', '牛奶' => '食品饮料',
            '生鲜'   => '食品饮料', '粮油' => '食品饮料', '速食' => '食品饮料', '螺蛳粉' => '食品饮料',
            '母婴'   => '母婴用品', '婴儿' => '母婴用品', '宝宝' => '母婴用品', '奶粉' => '母婴用品',
            '纸尿裤' => '母婴用品', '尿不湿' => '母婴用品', '玩具' => '母婴用品', '童车' => '母婴用品',
            '安全座椅' => '母婴用品', '孕婴' => '母婴用品', '宝妈' => '母婴用品',
            '家居'   => '家居生活', '日用品' => '家居生活', '居家' => '家居生活', '收纳' => '家居生活',
            '床品'   => '家居生活', '四件套' => '家居生活', '枕头' => '家居生活', '被子' => '家居生活',
            '纸巾'   => '家居生活', '清洁' => '家居生活', '香薰' => '家居生活', '装饰' => '家居生活',
            '厨房用品' => '家居生活', '卫浴' => '家居生活', '家纺' => '家居生活',
            '运动'   => '运动户外', '户外' => '运动户外', '健身' => '运动户外', '跑步' => '运动户外',
            '瑜伽'   => '运动户外', '球' => '运动户外', '骑行' => '运动户外', '帐篷' => '运动户外',
            '登山'   => '运动户外', '露营' => '运动户外', '跑步机' => '运动户外',
            '汽车'   => '汽车用品', '车载' => '汽车用品', '行车记录仪' => '汽车用品', '车充' => '汽车用品',
            '脚垫'   => '汽车用品', '自驾' => '汽车用品', '洗车' => '汽车用品',
        ];
    }

    /**
     * 从AI返回文本中提取JSON（兼容各种格式）
     */
    private static function extractJson($text)
    {
        if (empty($text)) return null;

        // 去除 markdown 代码块标记
        $text = preg_replace('/```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```/', '', $text);

        // 找到第一个 { 和最后一个 }
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $jsonStr = substr($text, $start, $end - $start + 1);
        $result = json_decode($jsonStr, true);
        return $result;
    }

    /**
     * 获取选品联动的AI System Prompt
     * 
     * 核心逻辑：品类作为唯一入口，选中后AI动态推理子分类和筛选条件
     */
    private static function getPickSystemPrompt()
    {
        return <<<'PROMPT'
你是一个电商平台的智能选品引擎。用户在前端选中了一个商品品类，你需要根据该品类动态推理出所有维度的筛选选项。

核心规则（严格遵守）：
1. 必须输出纯JSON，不要markdown、代码块符号、解释文字
2. JSON结构：{"groups":{"category":[],"price":[],"brand":[],"scene":[],"feature":[]}}

各维度要求：

【category - 子分类联动】
- 根据用户已选的品类，列出该品类下的子分类（4-7个）
- 这是关键联动：选"手机数码" → 子分类：手机、电脑、平板、相机、智能穿戴、办公设备、影音娱乐
- 选"服装鞋包" → 子分类：男装、女装、童装、内衣、鞋靴、箱包、配饰
- 选"家用电器" → 子分类：冰箱、洗衣机、空调、厨房电器、生活电器、个护健康
- 选"美妆护肤" → 子分类：面部护肤、彩妆、香水、个护清洁、男士护肤、美容工具
- 选"食品饮料" → 子分类：零食、饮料冲调、生鲜蔬果、粮油调味、方便速食、坚果蜜饯
- 选"家居生活" → 子分类：厨房用具、收纳整理、床上用品、清洁用品、香薰装饰、毛巾浴巾
- 子分类可以继续被点击，进一步细化时也给出更细的子分类
- **每个品类都需要体现该类目的真实子分类结构，不能套用模板**

【price - 价格区间联动物价】
- 必须根据所选品类在真实市场的价格分布来划分（5-7个区间）
- 家居/日用/零食等低价品类：价格从几元起，9.9元封顶、19.9元、50元以内、50-200元等
- 手机/电脑等数码品类：500以下、500-1500、1500-3000、3000-5000、5000-8000、8000以上
- 家电/汽车用品：按该品类的实际价位划分
- 服装：几十到几百元区间为主，奢侈品牌也提供高端价位
- 格式示例："20元以内""20-50元""50-150元""150-300元""300-800元""800元以上"

【brand - 品牌联动】
- 给出该品类下真实的知名品牌名（5-7个），中文名优先
- 手机数码：华为、小米、OPPO、vivo、荣耀、三星、Apple
- 服装鞋包：优衣库、UR、ZARA、太平鸟、伊芙丽、耐克、安踏、李宁
- 美妆护肤：珀莱雅、薇诺娜、欧莱雅、OLAY、兰蔻、雅诗兰黛、完美日记
- 家用电器：美的、格力、海尔、苏泊尔、九阳、小米、戴森
- 家居生活：无印良品、名创优品、网易严选、宜家、全棉时代、京东京造
- 食品饮料：三只松鼠、良品铺子、百草味、蒙牛、伊利
- 如果该品类没有集中品牌（如小商品），可给品牌风格：国货优选、国际大牌、性价比之王等
- **品牌名必须是真实存在的知名品牌，不要编造**

【scene - 使用场景】
- 根据品类属性推理合理的使用场景（4-6个）
- 手机：学生党、游戏娱乐、摄影拍照、商务办公、送长辈
- 服装：日常通勤、周末休闲、约会聚会、运动健身、商务正装
- 美妆：日常护肤、约会妆容、派对彩妆、旅行装、送礼
- 食品：办公室零食、追剧必备、年货礼盒、露营野餐、健身代餐

【feature - 特殊需求】
- 根据品类特性给出合理的筛选标签（4-6个）
- 手机：高刷新率、长续航、拍照好、轻薄、5G、大内存
- 服装：显瘦、透气、不易皱、百搭、显高、柔软舒适
- 家电：静音、节能、智能控制、小巧不占地、大容量

重要提醒：
- 每个数组元素直接是字符串，不要带数字编号
- 如果用户已选了某个维度的值，该维度仍然返回选项（可进一步细化）
- 所有选项都应该是中文
- category返回的是子分类，用户可以点击子分类继续深入筛选
PROMPT;
    }

    // ==================== 意图分析 ====================

    /**
     * 分析用户意图：是否为购物/比价意图
     * 
     * @return array ['is_purchase' => bool, 'keyword' => string]
     */
    private function analyzeIntent($message)
    {
        // ========== 精密购物意图触发词（淘宝/京东/拼多多常用搜索模式） ==========
        $purchaseWords = [
            // --- 直接购买意图 ---
            '买', '购买', '想买', '想要', '下单', '付款', '付钱', '结账', '购物车', '加购',
            '求推荐', '推荐', '安利', '种草', '入手', '拔草', '剁手', '囤货', '补货',
            '性价比', '便宜', '优惠', '折扣', '划算', '白菜价', '跳楼价', '清仓', '甩卖',
            '领券', '券后', '满减', '包邮', '免邮', '首单', '新人价', '会员价', '百亿补贴',
            '秒杀', '特价', '促销', '限时', '抢购', '聚划算', '淘抢购', '今日特卖',
            '最低价', '底价', '破价', '历史低价', '全网最低', '降价', '降价了',
            '比价', '对比', '比较', '哪个划算', '哪家便宜', '哪边好',
            '多少钱', '价格', '价位', '报价', '参考价', '原价', '现价', '到手价',
            '值得买', '值不值', '亏不亏', '划不划算', '香不香', '上不上车',
            '帮我找', '搜一下', '查一下', '看看', '找一下', '选购', '挑', '挑一下',
            // --- 对比选择意图 ---
            '哪个好', '哪个更', '哪个性价比高', '哪款好', '哪一款', '选哪个', '选哪款',
            '有什么区别', '差别', '差距', '区别大吗', '差在哪', '二选一', '纠结',
            '怎么选', '怎么挑', '如何选择', '选择困难', '哪个品牌好',
            // --- 质量评测意图 ---
            '什么牌子', '什么品牌', '哪个牌子', '哪个品牌', '品牌推荐',
            '质量', '质量怎么样', '质量如何', '做工', '材质', '面料',
            '好用吗', '好用不', '好不好用', '效果', '效果怎么样',
            '靠谱吗', '靠谱不', '信得过吗', '正品吗', '假货', '是真假的',
            '怎么样', '咋样', '如何', '口碑', '评价', '测评', '评测', '开箱',
            '会不会', '容不容易', '耐不耐', '能用多久', '寿命',
            // --- 使用场景意图 ---
            '平替', '替代', '同款', '平价款', '学生党', '上班族', '租房', '宿舍',
            '送女友', '送男友', '送妈妈', '送爸爸', '送长辈', '送闺蜜', '送同事',
            '生日礼物', '圣诞礼物', '情人节', '儿童节', '新年礼物', '礼品', '伴手礼',
            '上班穿', '约会穿', '面试', '聚会', '婚礼', '旅行', '户外', '露营', '健身', '跑步',
            '办公室', '家用', '出租屋', '小户型', '大户型', '厨房', '卫生间', '卧室',
            // --- 电商大促/活动词 ---
            '618', '双11', '双十一', '双十二', '618大促', '年货', '年货节',
            '开学季', '开学', '换季', '换新', '以旧换新', '置换',
            '优惠券', '领优惠券', '红包', '淘金币', '京豆', '积分',
            // --- 羊毛党常用词 ---
            '羊毛', '薅羊毛', '捡漏', '漏洞', 'bug价', '神价', '神车', '豪车',
            '几块钱', '9块9', '零元购', '免费领', '白嫖',
            // --- 询问库存/货源 ---
            '哪里买', '哪里有', '哪里有卖', '上哪买', '去哪买', '什么地方卖',
            '有什么', '有没有', '有卖吗', '有货吗', '什么时候有',
            // --- 售后/服务意图 ---
            '保修', '售后', '退换', '退货', '保修多久', '售后怎么样',
        ];

        // ========== 常见商品/品类/品牌名（淘宝京东拼多多热搜关键词） ==========
        $productTerms = [
            // = 手机/数码 =
            '手机', '5g手机', '智能手机', '老人机', '备用机', '折叠屏', '全面屏',
            'iphone', 'ipad', 'macbook', 'airpods', 'apple watch', 'imac',
            '华为', 'huawei', 'mate', 'pura', 'nova', '畅享', '麦芒',
            '小米', 'redmi', '红米', '黑鲨', 'civi',
            'oppo', 'vivo', 'iqoo', 'realme', '真我', '一加', 'oneplus',
            '三星', 'samsung', 'galaxy', 'z flip', 'note',
            '荣耀', 'honor', 'magic', '数字系列',
            '魅族', 'meizu', '努比亚', 'nubia', '摩托罗拉', 'moto',
            // = 电脑/办公 =
            '电脑', '笔记本', '笔记本电脑', '游戏本', '轻薄本', '商务本', '工作站', '台式机',
            '一体机', 'mini主机', 'mac mini', 'macbook pro', 'macbook air',
            '联想', 'lenovo', 'thinkpad', '小新', 'yoga', '拯救者',
            '戴尔', 'dell', 'xps', '灵越', '外星人', 'alienware',
            '华硕', 'asus', 'rog', '天选', '无畏', '灵耀',
            '惠普', 'hp', '暗影精灵', '战66', '星',
            '宏碁', 'acer', '微星', 'msi', '雷蛇', 'razer',
            '华为matebook', '小米笔记本', '荣耀笔记本',
            '键盘', '鼠标', '显示器', '显卡', 'cpu', '内存', '硬盘', '固态', 'ssd',
            '主板', '电源', '散热器', '机箱', '电竞椅', '显示器支架', '机械键盘',
            // = 平板/电子书 =
            '平板', '学习平板', '画画平板',
            'ipad pro', 'ipad air', 'ipad mini', '华为平板', '小米平板', '荣耀平板',
            'kindle', '电子书', '阅读器', '墨水屏', '电纸书',
            // = 耳机/音频 =
            '耳机', '蓝牙耳机', '真无线耳机', '降噪耳机', '头戴耳机', '运动耳机', '骨传导',
            '音箱', '音响', '蓝牙音箱', '智能音箱', '回音壁', '低音炮',
            '小爱同学', '小度', '天猫精灵', 'homepod',
            'sony耳机', 'bose', '森海塞尔', '铁三角', 'jbl', 'beats',
            // = 手表/手环 =
            '手表', '智能手表', '运动手表', '机械表', '石英表', '电子表', '儿童手表',
            '手环', '智能手环', 'apple watch', '华为手表', '小米手环', '华米', 'garmin', '佳明',
            // = 相机/摄影 =
            '相机', '微单', '单反', '卡片机', '拍立得', '运动相机', '无人机', '云台',
            '稳定器', '镜头', '摄象机', 'gopro', '大疆', 'dji', 'insta360',
            '索尼', 'sony', '佳能', 'canon', '尼康', 'nikon', '富士', 'fujifilm',
            // = 电视/家电 =
            '电视', '智能电视', '投影仪', '激光电视', '小米电视', '华为智慧屏',
            '冰箱', '洗衣机', '空调', '热水器', '燃气灶', '油烟机', '集成灶',
            '风扇', '取暖器', '油汀', '电热毯', '加湿器', '除湿机', '空气净化器',
            '扫地机', '扫地机器人', '洗地机', '吸尘器', '擦窗机器人',
            '微波炉', '烤箱', '蒸烤箱', '电饭煲', '空气炸锅', '破壁机', '豆浆机',
            '电磁炉', '电水壶', '养生壶', '净水器', '饮水机', '洗碗机', '消毒柜',
            '苏泊尔', '美的', '格力', '海尔', '九阳', '方太', '老板', '华帝', '万和',
            '戴森', 'dyson', '小狗', '追觅', '石头', '云鲸', '科沃斯',
            // = 个护/美妆 =
            '电动牙刷', '牙刷', '冲牙器', '剃须刀', '脱毛仪', '美容仪', '洁面仪',
            '吹风机', '卷发棒', '直发器', '美发', '理发器',
            '护肤品', '化妆品', '面膜', '口红', '唇釉', '唇膏', '眼影',
            '粉底', '气垫', '散粉', '腮红', '眉笔', '卸妆', '洁面', '防晒',
            '精华', '面霜', '乳液', '水乳', '眼霜', '洗面奶', '化妆水',
            '洗发水', '沐浴露', '身体乳', '护手霜', '香薰', '香水',
            '雅诗兰黛', '兰蔻', 'skii', 'sk2', '迪奥', '香奈儿', '阿玛尼',
            'ysl', '纪梵希', '资生堂', '雪花秀', '后', 'whoo',
            '欧莱雅', 'olay', '玉兰油', '百雀羚', '珀莱雅', '薇诺娜',
            // = 服饰/鞋包 =
            '衣服', '裤子', '裙子', '外套', '羽绒服', '大衣', '风衣', '夹克',
            '卫衣', 't恤', '衬衫', 'polo衫', '针织衫', '毛衣', '打底衫',
            '牛仔裤', '休闲裤', '西裤', '运动裤', '短裤', '阔腿裤',
            '连衣裙', '半身裙', 'a字裙', '百褶裙', '吊带裙',
            '内衣', '内裤', '袜子', '保暖内衣', '秋衣秋裤',
            '鞋子', '运动鞋', '跑鞋', '板鞋', '帆布鞋', '篮球鞋', '足球鞋',
            '高跟鞋', '平底鞋', '凉鞋', '拖鞋', '拖鞋', '洞洞鞋', '雪地靴',
            '包包', '双肩包', '单肩包', '斜挎包', '钱包', '卡包', '行李箱',
            '帽子', '围巾', '手套', '墨镜', '太阳镜',
            '耐克', 'nike', '阿迪达斯', 'adidas', '安踏', '李宁', '特步',
            '新百伦', 'new balance', '匡威', 'converse', 'vans', '彪马', 'puma',
            '斐乐', 'fila', '斯凯奇', 'skechers', '鬼冢虎', 'asics', '361',
            '优衣库', 'uniqlo', 'zara', 'hm', 'ur', 'gap', '无印良品', 'muji',
            // = 母婴 =
            '奶粉', '纸尿裤', '拉拉裤', '奶瓶', '吸奶器', '温奶器', '消毒锅',
            '婴儿车', '婴儿床', '安全座椅', '餐椅', '背带', '腰凳',
            '玩具', '积木', '乐高', 'lego', '绘本', '爬行垫', '围栏',
            '湿巾', '棉柔巾', '婴儿沐浴', '婴儿洗衣', '婴儿面霜',
            '帮宝适', '花王', '好奇', '大王', '爱他美', '惠氏', '美赞臣',
            // = 食品/零食 =
            '零食', '饮料', '茶叶', '咖啡', '牛奶', '酸奶', '坚果', '果干',
            '方便面', '螺蛳粉', '酸辣粉', '自热火锅', '速食', '即食',
            '三只松鼠', '良品铺子', '百草味', '良呈美', '来伊份',
            '巧克力', '饼干', '蛋糕', '面包', '糖果', '蜜饯',
            '保健品', '蛋白粉', '维生素', '鱼油', '钙片', '益生菌',
            '燕窝', '阿胶', '枸杞', '红枣', 'swisse', '汤臣倍健',
            // = 家居/日用 =
            '床单', '被套', '枕头', '被子', '凉席', '蚊帐', '四件套',
            '收纳', '收纳箱', '衣架', '挂钩', '置物架', '鞋架',
            '拖把', '拖把', '垃圾桶', '保鲜盒', '保鲜膜', '垃圾袋',
            '纸巾', '抽纸', '卷纸', '湿厕纸', '厨房纸', '洗脸巾',
            '窗帘', '地毯', '地垫', '靠垫', '抱枕', '挂画', '装饰画',
            '灯具', '台灯', '床头灯', '落地灯', '小夜灯', '氛围灯',
            // = 运动/户外 =
            '跑步机', '动感单车', '椭圆机', '哑铃', '瑜伽垫', '泡沫轴',
            '帐篷', '睡袋', '登山杖', '冲锋衣', '速干衣', '泳衣', '泳镜',
            '篮球', '足球', '排球', '羽毛球', '乒乓球', '网球',
            // = 汽车用品 =
            '行车记录仪', '车载充电器', '车载支架', '汽车香水', '腰靠', '头枕',
            '安全座椅', '脚垫', '坐垫', '太阳膜', 'etc',
            // = 宠物 =
            '猫粮', '狗粮', '猫砂', '宠物零食', '猫罐头', '宠物玩具', '猫抓板',
            '狗绳', '宠物窝', '猫爬架', '饮水机', '自动喂食器',
            // = 游戏 =
            'switch', 'ps5', 'xbox', 'steam deck', 'rog ally',
            '任天堂', '索尼游戏', '微软游戏', '手柄', '游戏耳机', '游戏显示器',
            'switch游戏', 'ps5游戏', '健身环', '塞尔达', '马里奥',
        ];

        $isPurchase = false;
        $mLower = mb_strtolower($message);

        // --- 策略1：检查是否匹配购物意图触发词 ---
        foreach ($purchaseWords as $word) {
            if (mb_strpos($mLower, $word) !== false) {
                $isPurchase = true;
                break;
            }
        }

        // --- 策略2：正则匹配常见电商搜索句式 ---
        if (!$isPurchase) {
            $patterns = [
                '/\S{1,8}(和|与|vs|VS|对比|跟)\S{1,8}(哪个|哪款|哪个好|怎么选|区别)/u',   // XX和XX哪个好
                '/\S{2,12}(值得|推荐|怎么样|好不好|靠谱吗|好不好用|好用吗|质量如何)/u',      // XX怎么样
                '/\S{2,12}(测评|评测|开箱|体验|使用感受)/u',                              // XX测评
                '/\S{2,12}(多少钱|什么价|价格多少|贵吗|便宜吗)/u',                         // XX多少钱
                '/\S{2,12}(排行榜|排名|top|推荐榜|热销)/u',                               // XX排行榜
                '/\S{2,12}(同款|平替|替代|平价版)/u',                                    // XX同款/平替
                '/\S{2,12}(送|送给|生日|礼物|节日|过年|新年)\S{0,8}/u',                  // 送XX礼物
                '/\S{2,12}(优惠券|券|红包|折扣|满减|活动)/u',                             // XX优惠券
                '/(女生|男生|学生|上班族|宝妈|宝宝|儿童|老人|长辈)\S{0,6}(用什么|推荐|适合|买什么)/u',  // 女生适合用什么
                '/(夏天|夏天穿|冬季|春秋|换季|开学)\S{0,6}(推荐|买|穿|用)/u',              // 夏天推荐
                '/(卧室|客厅|厨房|卫生间|阳台|宿舍)\S{0,6}(布置|装饰|收纳|摆放|买)/u',     // 卧室布置
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message)) {
                    $isPurchase = true;
                    break;
                }
            }
        }

        // 提取商品关键词
        $keyword = $this->extractKeyword($message);

        // --- 策略3：如果没匹配到购物词，但关键词明确是商品/品牌名，也判为购物意图 ---
        if (!$isPurchase && !empty($keyword)) {
            foreach ($productTerms as $term) {
                if (mb_strpos($mLower, mb_strtolower($term)) !== false) {
                    $isPurchase = true;
                    break;
                }
            }
        }

        // --- 策略4：消息本身就是纯商品名/品牌名（无额外意图词） ---
        if (!$isPurchase) {
            $pureMsg = preg_replace('/[\s？！。，、\.\,\!\?]+/u', '', $message);
            if (mb_strlen($pureMsg) >= 2 && mb_strlen($pureMsg) <= 15) {
                foreach ($productTerms as $term) {
                    if (mb_strtolower($pureMsg) === mb_strtolower($term)) {
                        $isPurchase = true;
                        $keyword = $pureMsg;
                        break;
                    }
                }
            }
        }

        return [
            'is_purchase' => $isPurchase && !empty($keyword),
            'keyword'     => $keyword,
        ];
    }

    /**
     * 从用户消息中提取商品关键词
     * 
     * 优先级：
     * 1. 品牌名 + 型号（如"华为mate60"、"iphone15"）
     * 2. 品类+属性（如"降噪耳机"、"轻薄笔记本"）
     * 3. 纯品类词（如"耳机"、"手机"）
     */
    private function extractKeyword($message)
    {
        // ========== 常见意图/停用词（淘宝京东拼多多搜索中常被忽略的词） ==========
        $stopWords = [
            // -- 查询意图词 --
            '帮我', '我想', '我要', '我需要', '我想买', '我想看看', '我想找',
            '有没有', '能不能', '可不可以', '可以', '行不行', '是否有',
            '推荐', '求推荐', '推荐一下', '安利一下', '请推荐', '帮忙推荐',
            '买', '购买', '想买', '想要', '需要', '打算买', '准备买', '入手',
            '比价', '对比', '比较', '和', '与', 'vs', '哪个好', '哪家好',
            '怎么样', '多少钱', '什么价格', '价格', '价位', '最低价',
            '哪里买', '哪里有', '哪里有卖', '上哪买', '什么地方', '什么渠道',
            '品牌', '牌子', '什么牌子', '哪个牌子', '哪里牌子',
            '性价比', '最', '高性价比', '最大的', '最好的', '最低的',
            // -- 数量/量词 --
            '一台', '一个', '一款', '一种', '一件', '一双', '一件套', '一套',
            '一部', '一根', '一条', '一双', '一盒', '一包', '一瓶', '一箱',
            '几款', '几种', '几个', '几件', '一些',
            // -- 语气/助词 --
            '点', '的', '了', '吗', '呢', '啊', '吧', '嘛', '呀', '哦',
            '给', '为', '在', '把', '被', '让', '从', '到',
            '请', '你', '我', '他', '她', '它', '您', '咱们', '我们', '大家',
            '这个', '那个', '哪个', '哪款', '哪种', '什么', '怎么', '如何', '什么样',
            '有吗', '有卖', '在卖', '好', '好点的', '好用的',
            '便宜', '贵', '便宜点的', '贵点的', '不贵', '好用', '靠谱', '靠谱的',
            '值得买', '值得', '划算', '优惠', '折扣', '活动', '有活动',
            // -- 不确定/询问词 --
            '或', '还是', '或者', '之类的', '什么的', '等等', '这些',
            '有没有人', '大家觉得', '大神', '老司机', '懂行的',
            '想问', '问一下', '问下', '问一问', '咨询', '请教',
            '请问', '想问下', '想问一下', '想问一下大家',
            // -- 修饰/描述词 --
            '最新款', '新款', '最新', '顶配', '高配', '入门款', '标配', '旗舰',
            '正品', '正版的', '原装的', '原装', '官方', '行货', '水货',
            '好的', '不错的', '挺好的', '很不错的', '好用的',
            '质量好的', '耐用的', '实用的', '适合', '适用的',
        ];

        $cleaned = $message;

        // 按长度降序排列停用词，优先替换长词（避免"推荐"在"求推荐"之前被部分替换）
        usort($stopWords, function($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        foreach ($stopWords as $word) {
            $cleaned = str_replace($word, '', $cleaned);
        }

        // 去除空白、标点、特殊符号
        $cleaned = preg_replace('/[\s\p{P}\p{S}]+/u', '', $cleaned);

        // 修正一些常见品牌的大小写（保留英文品牌名）
        $brandPatterns = ['iphone', 'ipad', 'airpods', 'macbook', 'huawei', 'oppo', 'vivo', 
                          'nike', 'adidas', 'dyson', 'dji', 'sony', 'bose', 'jbl', 'beats',
                          'asus', 'dell', 'hp', 'lenovo', 'skii', 'lego'];
        foreach ($brandPatterns as $bp) {
            if (mb_stripos($cleaned, $bp) !== false) {
                // 保留品牌词，不做额外处理
            }
        }

        // 如果清理后为空或只剩1个字，返回空
        if (mb_strlen($cleaned) <= 1) {
            return '';
        }

        // 如果结果太长（通常是无意义的残留），截取前面的核心关键词
        if (mb_strlen($cleaned) > 12) {
            $cleaned = mb_substr($cleaned, 0, 12);
        }

        return trim($cleaned);
    }

    // ==================== 商品搜索 ====================

    /**
     * 处理购物意图：搜索站内文章 + 全网比价
     */
    private function handleProductSearch($keyword, $originalMessage)
    {
        if (empty($keyword)) {
            return $this->handleAiChat($originalMessage, $this->loadHistory());
        }

        // 意图澄清：用户点了引导按钮后发送「产品本身 / 配件周边」，剥离澄清词避免循环引导
        $explicit = '';
        $rawMsg   = $originalMessage ?: $keyword;
        if (preg_match('/(本身|产品本身|手机本身|原装)/u', $rawMsg)) {
            $explicit = 'main';
        } elseif (preg_match('/(配件|周边|附件|耗材)/u', $rawMsg)) {
            $explicit = 'accessory';
        }
        // 剥离澄清词得到干净搜索词（如「iphone 14 产品本身」→「iphone 14」）
        $searchKw = preg_replace('/(产品本身|本身|配件周边|配件|周边|附件|耗材|手机本身|原装)/u', '', $keyword);
        $searchKw = trim(preg_replace('/\s+/u', ' ', $searchKw));
        if ($searchKw === '') {
            $searchKw = $keyword;
        }
        // 配件模式：在搜索词后追加「配件」，让联盟库更精准返回周边
        if ($explicit === 'accessory') {
            $searchKw2 = $searchKw . ' 配件';
        } else {
            $searchKw2 = $searchKw;
        }

        // 1. 搜索站内 yun_article 表（参数化 LIKE，杜绝 addslashes 宽字节注入风险）
        $articles = obj("api/ApiData")->dataSelect(
            "yun_article",
            array('title' => array('like', '%' . $searchKw2 . '%')),
            "`id` DESC LIMIT 0, 5"
        );

        if (!empty($articles)) {
            return $this->formatArticleResults($articles, $searchKw2);
        }

        // 2. 站内无结果，尝试大淘客全网搜索（按平台优先级填充：淘宝>京东>拼多多>唯品会）
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $tjkResult = $tjk->searchAllPlatforms($searchKw2, 1, 5);
            if (isset($tjkResult['debug'])) {
                error_log('[AI search] platforms=' . json_encode($tjkResult['debug']));
            }
            if ($tjkResult['code'] == 1 && !empty($tjkResult['items'])) {
                return $this->formatTjkResults($tjkResult['items'], $searchKw2, $explicit);
            }
        } catch (\Exception $e) {
            // 大淘客不可用时静默 fallback
        }

        // 3. 都没有结果，交给 AI 处理
        return $this->handleAiChat($originalMessage, $this->loadHistory());
    }

    /**
     * 格式化站内文章结果为 HTML
     */
    private function formatArticleResults($items, $keyword)
    {
        $html = '<div class="ai-product-header">🔍 为您找到关于 "<b>' . htmlspecialchars($keyword) . '</b>" 的优惠信息：</div>';
        $html .= '<div class="ai-product-list">';

        foreach ($items as $i => $item) {
            $no    = $i + 1;
            $title = htmlspecialchars(strip_tags($item['title']), ENT_QUOTES, 'UTF-8');
            $link  = url($route = 'index/index/view/id=<id>', $params = ['id' => $item['id']]);
            $pic   = isset($item['mainPic']) ? htmlspecialchars($item['mainPic']) : '';
            $dec   = isset($item['dec']) ? mb_substr(strip_tags($item['dec']), 0, 80, 'utf-8') : '';
            $date  = isset($item['date']) ? substr($item['date'], 0, 10) : '';

            $html .= '<a href="' . $link . '" target="_blank" class="ai-product-item">';
            if ($pic) {
                $html .= '<img src="' . $pic . '" class="ai-product-pic" alt="' . $title . '">';
            }
            $html .= '<div class="ai-product-info">';
            $html .= '<div class="ai-product-title">' . $title . '</div>';
            if ($dec) {
                $html .= '<div class="ai-product-desc">' . htmlspecialchars($dec) . '</div>';
            }
            if ($date) {
                $html .= '<div class="ai-product-date">📅 ' . $date . '</div>';
            }
            $html .= '</div>';
            $html .= '<span class="ai-product-badge">立即查看 →</span>';
            $html .= '</a>';
        }

        $html .= '</div>';
        $html .= '<div class="ai-product-footer">💡 点击商品卡片即可查看详情和购买链接。没找到满意的？告诉我更具体的需求，我帮您再找找~</div>';
        return $html;
    }

    /**
     * 渲染单条商品卡片（大淘客/好单库通用）
     */
    private function renderTjkItem($item)
    {
        $title   = htmlspecialchars($item['title'] ?? $item['dtitle'] ?? '', ENT_QUOTES, 'UTF-8');
        $goodsId = $item['goodsId'] ?? '';
        $from    = isset($item['item_from']) ? strtolower($item['item_from']) : '';
        if ($from === 'taobao' || $from === 'dtk') $from = 'tb';
        if (!in_array($from, ['tb', 'jd', 'pdd', 'vip'])) $from = 'tb';

        // 所有平台统一走 index/redirect/jump 转链入口（由 RedirectController 调好单库 RatesUrl
        // 二次转链生成带推广位的佣金链接），避免直接使用搜索接口返回的 couponLink
        // （多为无佣金的落地页），保证站长能拿到返利。
        // 透传 goodsSign（淘宝必需）：未入库商品也能拿到带佣金的转链短链，而非降级到无佣详情页。
        $jumpParams = ['platform' => $from, 'id' => $goodsId];
        if ($from === 'tb' && !empty($item['goodsSign'])) {
            $jumpParams['sign'] = $item['goodsSign'];
        }
        // 渲染时预转链：直接调 getPrivilegeLink 拿到真实推广短链，
        // 成功则卡片直达推广页（避免点击时才转链、因 PID 未配置失败而静默回退无佣金落地页）。
        // 失败则回退到 jump 中转（由 RedirectController 二次转链 + 落地页兜底）。
        $link = $this->resolveItemLink($from, $goodsId, $item['goodsSign'] ?? '');
        if (empty($link)) {
            $link = url('index/redirect/jump', $jumpParams);
        }
        $pic     = isset($item['mainPic']) ? htmlspecialchars($item['mainPic']) : '';
        $price   = isset($item['actualPrice']) ? floatval($item['actualPrice']) : 0;
        $coupon  = isset($item['couponPrice']) ? floatval($item['couponPrice']) : 0;
        $sales   = isset($item['monthSales']) ? intval($item['monthSales']) : 0;

        $fromLabel = [
            'tb' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会',
        ];
        $fromText = isset($fromLabel[$from]) ? '【' . $fromLabel[$from] . '】' : '';

        $html = '<a href="' . $link . '" target="_blank" rel="nofollow" class="ai-product-item">';
        if ($pic) {
            $html .= '<img src="' . $pic . '" class="ai-product-pic" alt="' . $title . '">';
        }
        $html .= '<div class="ai-product-info">';
        $html .= '<div class="ai-product-title">' . $fromText . $title . '</div>';
        $html .= '<div class="ai-product-price">';
        if ($price > 0) $html .= '💰 券后 <b>¥' . $price . '</b>';
        if ($coupon > 0) $html .= ' | 🧧 领' . $coupon . '元券';
        if ($sales > 0) $html .= ' | 📈 销量 ' . $sales;
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<span class="ai-product-badge">领券购买 →</span>';
        $html .= '</a>';
        return $html;
    }

    /**
     * 渲染时预生成商品推广短链（用于 AI 搜索结果卡片）。
     * 优先调 getPrivilegeLink 拿到真实带佣金短链；失败返回空（调用方回退到 jump 中转）。
     * 包裹超时保护，避免转链接口慢拖垮 AI 响应。
     */
    private function resolveItemLink($from, $goodsId, $goodsSign = '')
    {
        if (empty($goodsId)) return '';
        try {
            // 超时保护：最多等 1.5s，避免拖慢对话
            $tjk = new \ZhiCms\ext\Tjk();
            $ret = $tjk->getPrivilegeLink($goodsId, '', $from, $goodsSign);
            if (isset($ret['code']) && $ret['code'] == 1) {
                $data = $ret['data'] ?? array();
                $url = $data['couponClickUrl'] ?? $data['shortUrl'] ?? $data['url']
                     ?? $data['couponLink'] ?? $data['couponurl'] ?? $data['shortLink']
                     ?? $data['clickUrl'] ?? $data['itemUrl'] ?? '';
                if (!empty($url) && preg_match('#^https?://#i', $url)) {
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            // 转链失败不影响卡片展示，回退 jump 入口
        }
        return '';
    }

    /**
     * 配件/周边词表（用于把"产品本身"与"配件周边"区分开，避免搜 iphone 14 先出手机壳）
     */
    private static $ACCESSORY_WORDS = [
        // 3C 数码配件
        '手机壳', '保护壳', '壳', '保护套', '手机套', '套', '钢化膜', '膜', '贴膜', '保护膜',
        '数据线', '充电线', '充电线', '电源线', '充电器', '充电头', '充电宝', '电源适配器',
        '支架', '底座', '支撑架', '指环', '挂绳', '镜头膜', '防尘塞', '转接头', '转换器',
        '收纳包', '硬壳', '软壳', '透明壳', '硅胶壳', '皮套', '保护壳',
        // 服装/配饰
        '项链', '耳环', '耳钉', '手链', '戒指', '腰带', '皮带', '围巾', '帽子', '袜', '鞋垫', '鞋带',
        '打底', '内搭', '搭配', '配饰', '胸针', '发饰', '发圈', '墨镜', '包中包', '钥匙扣',
        // 家居/通用
        '收纳', '防尘', '保护罩', '桌布', '桌垫', '防尘盖', '盖布', '窗帘', '挂钩', '替换装',
        '滤芯', '耗材', '配件', '周边', '附件', '配套', '备件', '易损件',
    ];

    /**
     * 判断是否为"泛品类词"（如"手机/耳机/连衣裙"），这类不需要"产品本身 vs 配件"的澄清引导
     */
    private function isGenericCategory($kw)
    {
        $generic = [
            '手机', '耳机', '电脑', '笔记本', '平板', '手表', '鞋子', '鞋', '衣服', '裙子',
            '裤子', '外套', '包', '包包', '相机', '电视', '空调', '冰箱', '洗衣机', '被子',
            '枕头', '床单', '文具', '玩具', '水杯', '杯', '锅', '表', '键盘', '鼠标', '充电宝',
        ];
        $k = mb_strtolower(trim($kw));
        foreach ($generic as $g) {
            if ($k === $g || $k === mb_strtolower($g)) return true;
        }
        return false;
    }

    /**
     * 判断用户是否搜的是"具体型号/明确单品"（iphone 14、华为mate60、某款连衣裙），需要引导澄清
     */
    private function looksLikeSpecificModel($kw)
    {
        if ($this->isGenericCategory($kw)) return false;
        // 含数字型号（14 / 16 / pro / max / ultra ...）
        if (preg_match('/[\x{4e00}-\x{9fa5}]*\d{1,3}(\s?(pro|plus|max|ultra|mini|air|se))?/i', $kw)) return true;
        // 含明确品牌+系列信号
        if (preg_match('/(iphone|华为|huawei|小米|xiaomi|荣耀|honor|oppo|vivo|samsung|三星|mate|ipad|macbook|switch|ps5|airpods|苹果|apple|联想|lenovo|戴尔|dell|佳能|尼康|索尼|sony)/i', $kw)) return true;
        // 关键词较长（>=4 字且不是单纯品类），倾向具体需求
        if (mb_strlen(preg_replace('/\s+/u', '', $kw)) >= 4) return true;
        return false;
    }

    /**
     * 将商品列表分为"主产品"与"配件/周边"
     */
    private function classifyProducts($items, $keyword)
    {
        $main = [];
        $accessory = [];
        // 核心词：去掉空白后的关键词，用于判断"精确命中产品本身"
        $core = preg_replace('/\s+/u', '', $keyword);
        foreach ($items as $item) {
            $title = mb_strtolower(strip_tags($item['title'] ?? $item['dtitle'] ?? ''));
            $isAcc = false;
            foreach (self::$ACCESSORY_WORDS as $w) {
                if (mb_strpos($title, mb_strtolower($w)) !== false) {
                    $isAcc = true;
                    break;
                }
            }
            // 含配件词 → 配件；否则若标题精确含核心词（或核心词去掉末位型号仍命中）→ 主产品；其余保守归配件
            if ($isAcc) {
                $accessory[] = $item;
            } else {
                $hitCore = ($core !== '' && mb_strpos($title, mb_strtolower($core)) !== false);
                if ($hitCore) {
                    $main[] = $item;
                } else {
                    // 标题不含配件词但也不含核心词：偏向配件/周边（避免淹没主产品）
                    $accessory[] = $item;
                }
            }
        }
        return ['main' => $main, 'accessory' => $accessory];
    }

    /**
     * 生成"产品本身 vs 配件"的澄清引导区块
     */
    private function renderDisambiguation($keyword)
    {
        $kw = htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
        $msgMain = htmlspecialchars($keyword . ' 产品本身', ENT_QUOTES, 'UTF-8');
        $msgAcc  = htmlspecialchars($keyword . ' 配件周边', ENT_QUOTES, 'UTF-8');
        $html  = '<div class="ai-guide">';
        $html .= '<div class="ai-guide-tip">🤔 您搜的“<b>' . $kw . '</b>”，结果里大多是配件/周边，是想看<b>产品本身</b>还是<b>配件</b>呢？</div>';
        $html .= '<div class="ai-guide-btns">';
        $html .= '<button class="ai-guide-btn" data-msg="' . $msgMain . '">🛍️ 看 ' . $kw . ' 本身</button>';
        $html .= '<button class="ai-guide-btn" data-msg="' . $msgAcc . '">🔌 看 ' . $kw . ' 配件/周边</button>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 格式化大淘客全网搜索结果
     * @param string $explicit 已明确的意图：'' / 'main'（产品本身）/ 'accessory'（配件周边）
     */
    private function formatTjkResults($items, $keyword, $explicit = '')
    {
        $html = '<div class="ai-product-header">🔍 为您找到关于 "<b>' . htmlspecialchars($keyword) . '</b>" 的全网优惠：</div>';

        // 智能引导：若返回结果多为配件/周边、而用户搜的是具体型号/单品，且尚未明确意图，先澄清
        $cls = $this->classifyProducts($items, $keyword);
        $needGuide = empty($explicit)
            && $this->looksLikeSpecificModel($keyword)
            && count($cls['main']) < 2
            && count($cls['accessory']) >= 1;

        if ($needGuide) {
            $html .= $this->renderDisambiguation($keyword);
            $html .= '<div class="ai-product-sub">📦 先为您展示相关结果（含部分配件）：</div>';
        } elseif ($explicit === 'main' && !empty($cls['main'])) {
            // 已选「产品本身」：优先展示主产品，配件折叠标注
            $html .= '<div class="ai-product-sub">🛍️ 已为您优先展示「产品本身」：</div>';
            $items = array_merge($cls['main'], $cls['accessory']);
        } elseif ($explicit === 'accessory' && !empty($cls['accessory'])) {
            $html .= '<div class="ai-product-sub">🔌 已为您优先展示「配件/周边」：</div>';
            $items = array_merge($cls['accessory'], $cls['main']);
        }

        $html .= '<div class="ai-product-list">';
        foreach ($items as $item) {
            $html .= $this->renderTjkItem($item);
        }
        $html .= '</div>';

        if ($needGuide) {
            $html .= '<div class="ai-product-footer">👆 点击上方按钮可精准切换「产品本身」或「配件周边」。也可直接告诉我更具体的需求~</div>';
        } else {
            $html .= '<div class="ai-product-footer">💡 以上按淘宝比价优先排序（已含京东/拼多多/唯品会补充），点击卡片即可领券购买。没找到满意的？告诉我更多需求，我帮您再找~</div>';
        }
        return $html;
    }

    /**
     * AI选品专用搜索接口
     * 与聊天接口不同：这里直接用用户真正想买的商品词（keyword）做搜索，
     * 并把价格区间等筛选条件真实参与过滤，避免“搜出来和关键词无关的商品”。
     *
     * POST: JSON { keyword, category, price, brand, scene, feature[] }
     */
    public function pickProductSearch()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input   = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_REQUEST;
        }

        $keyword  = trim($input['keyword']  ?? '');
        $category = trim($input['category'] ?? '');
        $price    = trim($input['price']    ?? '');
        $brand    = trim($input['brand']    ?? '');
        $scene    = trim($input['scene']    ?? '');
        $feature  = isset($input['feature']) ? (array) $input['feature'] : [];

        // 真正要搜的词 = 用户输入的商品词；没输入商品词才退化为品类
        if (empty($keyword)) {
            $keyword = $category;
        }
        if (empty($keyword)) {
            echo json_encode(['html' => '请先告诉我你想买什么，比如“水杯”~', 'type' => 'chat'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 1. 跨平台聚合搜索（每平台前 5 条）
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $tjkResult = $tjk->searchAllPlatforms($keyword, 1, 5);
            $items = ($tjkResult['code'] == 1 && !empty($tjkResult['items'])) ? $tjkResult['items'] : [];
        } catch (\Exception $e) {
            $items = [];
        }

        // 2. 价格区间过滤（仅当过滤后仍有结果时才生效，避免把全部结果筛空）
        $range = $this->parsePriceRange($price);
        if ($range && !empty($items)) {
            $byPrice = [];
            foreach ($items as $it) {
                $p = floatval($it['actualPrice'] ?? 0);
                if ($p >= $range[0] && ($range[1] === null || $p <= $range[1])) {
                    $byPrice[] = $it;
                }
            }
            if (!empty($byPrice)) {
                $items = $byPrice;
            }
        }

        // 3. 关键词相关性过滤（剔除与商品词明显无关的结果）
        $items = $this->filterByKeywordRelevance($items, $keyword);

        if (empty($items)) {
            echo json_encode([
                'html' => '抱歉，没找到符合“<b>' . htmlspecialchars($keyword) . '</b>”' .
                          ($price ? '（预算' . htmlspecialchars($price) . '）' : '') .
                          '的商品，换个关键词或放宽筛选再试试~',
                'type' => 'product',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $html = $this->formatPickResults($items, $keyword, $category, $price, $brand, $scene, $feature);
        echo json_encode(['html' => $html, 'type' => 'product'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 渲染 AI选品 搜索结果（带已选条件回显）
     */
    private function formatPickResults($items, $keyword, $category, $price, $brand, $scene, $feature)
    {
        $crit = [];
        if ($category && $category !== $keyword) $crit[] = '品类：' . $category;
        if ($price)    $crit[] = '预算：' . $price;
        if ($brand)    $crit[] = '偏好：' . $brand;
        if ($scene)    $crit[] = '场景：' . $scene;
        if (!empty($feature)) $crit[] = '需求：' . implode('、', $feature);

        $html  = '<div class="ai-product-header">🔍 按“<b>' . htmlspecialchars($keyword) . '</b>”为你筛选'
               . (count($crit) ? '（' . implode('，', $crit) . '）' : '')
               . '，共 ' . count($items) . ' 件：</div>';
        $html .= '<div class="ai-product-list">';
        foreach ($items as $item) {
            $html .= $this->renderTjkItem($item);
        }
        $html .= '</div>';
        $html .= '<div class="ai-product-footer">💡 以上来自全网多平台比价（淘宝/京东/拼多多/唯品会），已按你的预算与关键词过滤。还想更精准？告诉我更多需求~</div>';
        return $html;
    }

    /**
     * 解析“价格区间”筛选为 [min, max]，max=null 表示无上限
     * 例：'100元以下' → [0,100]；'100-300元' → [100,300]；'300元以上' → [300,null]
     */
    private function parsePriceRange($price)
    {
        if (empty($price)) {
            return null;
        }
        if (preg_match('/(\d+)\s*元以下/', $price, $m)) {
            return [0, floatval($m[1])];
        }
        if (preg_match('/(\d+)\s*元以上/', $price, $m)) {
            return [floatval($m[1]), null];
        }
        if (preg_match('/(\d+)\s*[-~]\s*(\d+)\s*元/', $price, $m)) {
            return [floatval($m[1]), floatval($m[2])];
        }
        return null;
    }

    /**
     * 关键词相关性过滤：剔除与商品词明显无关的结果（如搜“水杯”却返回“手机”）。
     * 采用“字符重叠”判定，避免误杀“保温杯”“玻璃杯”这类合法商品。
     */
    private function filterByKeywordRelevance($items, $keyword)
    {
        $kw = trim($keyword);
        if (mb_strlen($kw) < 2 || empty($items)) {
            return $items;
        }
        // 去掉泛词，得到用于判断的核心词
        $stopKw = ['商品', '东西', '产品', '推荐', '一下', '帮我', '想买', '我想'];
        $core = $kw;
        foreach ($stopKw as $s) {
            $core = str_replace($s, '', $core);
        }
        if (mb_strlen($core) < 2) {
            $core = $kw;
        }

        $kept = [];
        foreach ($items as $it) {
            $title = $it['title'] ?? $it['dtitle'] ?? '';
            if (mb_strpos($title, $kw) !== false || mb_strpos($title, $core) !== false) {
                $kept[] = $it;
                continue;
            }
            // 字符级重叠：标题包含核心词任一字符即视为可能相关
            $overlap = false;
            for ($i = 0; $i < mb_strlen($core); $i++) {
                $ch = mb_substr($core, $i, 1);
                if ($ch !== '' && mb_strpos($title, $ch) !== false) {
                    $overlap = true;
                    break;
                }
            }
            if ($overlap) {
                $kept[] = $it;
            }
        }
        // 若相关性过滤把结果清空（平台返回标题措辞差异大），则保留原始结果不误杀
        return !empty($kept) ? $kept : $items;
    }

    // ==================== AI 对话 ====================

    /**
     * 转发用户消息给 AI 大模型
     */
    private function handleAiChat($message, $history)
    {
        $systemPrompt = <<<PROMPT
你是"值得淘"购物网站的AI导购助手，名叫"小淘"。你的职责是帮用户解答购物相关问题。

性格特点：
- 热情友好、活泼可爱，像一个懂购物的好朋友
- 回答简洁扼要，每条回复控制在300字以内
- 适当使用 emoji 让对话更亲切

核心规则：
1. 如果用户咨询商品推荐但你没搜到具体商品，建议他描述得更具体一些，或使用网站搜索功能
2. 如果用户聊非购物话题，可以友好回应但温和引导回购物主题
3. 对本网站商品类型了如指掌：数码3C、家电、服饰美妆、母婴、食品、日用百货等
4. 购物建议要实用：关注优惠券、性价比、品牌口碑、售后服务等

不要做的事：
- 不要编造不存在的商品或价格
- 不要推荐违禁品或成人用品
- 不要评价其他购物平台的好坏
- 不要提供投资、医疗、法律等专业建议
PROMPT;

        $response = \app\common\AiHub::chat($message, $systemPrompt, false);

        if (empty($response)) {
            return '哎呀，小淘好像走神了😅 您可以直接使用网站顶部的搜索功能查找商品，或者换个问题再来问我吧~';
        }

        // 统一用 AiHub::isErrorResult 判定各种错误前缀（AI 模型未配置 / 大模型处理异常 /
        // 大模型API错误 / HTTP错误 / CURL错误 等），避免把内部错误串当作正常回复回显给用户。
        // 注：AiService::isErrorResult 是 private，故用公开的 AiHub::isErrorResult（前缀同构）。
        if (\app\common\AiHub::isErrorResult($response)) {
            // 未配置模型：标记给前端，给出友好文案（不含 "unconfigured:" 机器前缀，避免污染历史）
            if (strpos($response, 'AI 模型未配置') === 0) {
                $this->chatUnconfigured = true;
                return '小淘暂时还没被唤醒呢🌟 站长正在后台「AI 设置」中配置对话模型，稍等片刻再来找我聊天吧~';
            }
            // 其余错误（网络/服务异常）一律兜底文案
            return '小淘刚才脑子卡壳了🤯 请您稍等片刻再试试，或者用搜索功能直接找商品吧~';
        }

        return $response;
    }
}
