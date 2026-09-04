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

    /** 复用搜索阶段已初始化的 Tjk 实例（含完整配置/推广位 PID），供转链复用 */
    private $tjk = null;

    /** 当前请求的筛选条件（供商品卡「逐条推荐理由」使用，避免在多处改方法签名） */
    private $curFilters = array();

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

    /**
     * 把助手回复压缩为「纯文本摘要」用于持久化历史。
     * 商品卡/文章卡是一大段 HTML，若原样写入历史，下一轮：
     *  - analyzeIntent 的指代解析会从 HTML 标签文字里误提关键词，扰乱意图；
     *  - 透传给 AI 的上下文会被 HTML 噪声污染，导致「连问几次就断/反复提问」。
     * 因此历史里只留一句人类可读的摘要，既保留连贯性又干净。
     *
     * @param string $reply    原始回复（可能含 HTML）
     * @param string $respType product | chat
     * @return string
     */
    private function summarizeReply($reply, $respType)
    {
        $text = trim(strip_tags($reply));
        if ($respType === 'product') {
            // 尝试从商品卡里抓一个代表词（如「保温杯」）作为摘要主语
            if (preg_match('/ai-product-title[^>]*>(.*?)<\/div>/u', $reply, $m)) {
                $kw = mb_substr(strip_tags($m[1]), 0, 12, 'utf-8');
                return '已为你推荐相关商品：' . $kw . '（可在对话框查看卡片）';
            }
            return '已为你推荐相关商品（可在对话框查看卡片）';
        }
        // 聊天回复：截断到 120 字，避免历史过长
        if (mb_strlen($text, 'utf-8') > 120) {
            $text = mb_substr($text, 0, 120, 'utf-8') . '…';
        }
        return $text;
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

        // ===== 快捷指令解析（让对话更直接、避免「绕圈才出产品」）=====
        //  #关键词  → 直接搜商品（跳过 AI 闲聊，立即返回商品卡，不废话）
        //  @关键词  → 全网比价（强制走大淘客全网搜，给出跨平台比价）
        //  ?关键词  → 纯 AI 问答（即使像购物词也走聊天，适合问「怎么选」等）
        $forceMode = '';   // '' | 'search' | 'compare' | 'chat'
        if (preg_match('/^([#@?])\s*(.+)$/u', $message, $m)) {
            $prefix = $m[1];
            $message = trim($m[2]);
            if ($prefix === '#')      $forceMode = 'search';
            elseif ($prefix === '@')   $forceMode = 'compare';
            elseif ($prefix === '?')   $forceMode = 'chat';
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

        // 复用统一的导购主流程（移动端 guide 接口也调用同一份逻辑，保证双端一致）
        $out = $this->chatLogic($message, $forceMode);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 导购主流程（桌面端与移动端共用一份逻辑，避免双端行为不一致）
     * 返回数组：['reply'=>HTML片段, 'type'=>product|chat, 'unconfigured'=>bool?]
     *
     * @param string $message   用户输入（已剥离快捷指令前缀）
     * @param string $forceMode '' | 'search' | 'compare' | 'chat'
     * @return array
     */
    public function chatLogic($message, $forceMode = '')
    {
        // 加载历史并追加用户消息（历史内容已做 HTML 清洗，见 saveHistory）
        $history = $this->loadHistory();
        $history[] = ['role' => 'user', 'content' => $message];

        // 快捷指令优先：直接决定走搜索/比价/聊天，跳过意图猜疑，避免「多轮才出产品」
        if ($forceMode === 'search' || $forceMode === 'compare') {
            $keyword = $this->extractKeyword($message);
            if (empty($keyword)) {
                $keyword = $message; // 取不到关键词就用原文（如"#手机"→"手机"）
            }
            $filters = $this->extractPurchaseFilters($message, $keyword);
            $reply = $this->handleProductSearch($keyword, $message, $history, true, $forceMode === 'compare', $filters);
            $respType = 'product';
        } else {
            // 对比意图优先：识别"iphone 和 小米 哪个好 / A vs B"等，走双品对比引擎
            $cmp = $this->detectCompare($message);
            if ($cmp !== false) {
                $reply = $this->handleCompare($cmp['a'], $cmp['b'], $history);
                $respType = 'product';
            } else {
            // 意图分析（含上下文：能理解"便宜点的""第二个"等指代）
            $intent = $this->analyzeIntent($message, $history);

            if ($intent['is_purchase']) {
                // 求推荐 / 求对比 / 含筛选诉求 → 走"商品卡 + AI 导购点评"，更贴近真人导购
                $needAdvice = $intent['need_advice'];
                $filters = $intent['filters'] ?? array();
                $reply = $this->handleProductSearch($intent['keyword'], $message, $history, $needAdvice, false, $filters);
                $respType = 'product';
            } else {
                // 纯聊天模式（? 指令或确实非购物）
                $reply = $this->handleAiChat($message, $history);
                $respType = 'chat';
            }
            }
        }

        // 兜底：判定为 product 但实际未产出任何商品卡/导购点评/澄清引导（如搜不到该词的商品，
        // handleProductSearch 已 fallback 到 AI 聊天），此时降级为 chat，避免前端按商品渲染却无卡片。
        if ($respType === 'product'
            && strpos($reply, 'ai-product') === false
            && strpos($reply, 'ai-advice') === false
            && strpos($reply, 'ai-clarify') === false
            && strpos($reply, 'ai-compare') === false
            && strpos($reply, 'ai-guide') === false) {
            $respType = 'chat';
        }

        // 保存历史（assistant 侧存「纯文本摘要」，避免把整段商品卡 HTML 写入历史，
        // 否则下一轮意图分析会被 HTML 标签文字干扰、AI 上下文也被污染 → 连问几次就断/反复提问）
        $history[] = ['role' => 'assistant', 'content' => $this->summarizeReply($reply, $respType)];
        $this->saveHistory($history);

        $out = ['reply' => $reply, 'type' => $respType];
        if (!empty($this->chatUnconfigured)) {
            $out['unconfigured'] = true;
        }
        return $out;
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
     * 增强点（更贴近真人导购）：
     *  - 支持多轮上下文指代（"便宜点的""第二个""红色的"），从上一轮提取真实关键词
     *  - 区分"纯搜商品"与"求推荐/求对比/带筛选诉求"，后者 need_advice=true 走 AI 导购点评
     *
     * @param string $message 当前消息
     * @param array  $history 上下文历史（role/content）
     * @return array ['is_purchase'=>bool,'keyword'=>string,'need_advice'=>bool]
     */
    private function analyzeIntent($message, $history = array())
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

        // --- 策略2.5：歧义词上下文消歧（苹果=水果/手机 等）---
        // 必须在语义匹配之前做：若原句出现歧义词且上下文足以判定义项（如"苹果手机"→手机、
        // "红富士苹果"→水果），直接归一为标准品类词并跳过后续易误判的语义向量匹配。
        // 仅当歧义词出现在句中且能判定具体义项时才生效；否则交回语义匹配兜底。
        $disCat = \ZhiCms\ext\Vector\Bm25Index::disambiguateFromMessage($message);
        if ($disCat !== '') {
            $isPurchase = true;
            $keyword = $disCat;
        }

        // --- 类目歧义检测（必须在语义归一化之前）---
        // 用户只说泛类（"裙子""裤子""鞋子"…）时，不直接猜一个子类（极易猜错，如裙子≠连衣裙），
        // 而是返回候选子类让用户确认（"想他所想"）。含品牌/型号/已指明具体子类则不过问。
        // 提前返回，避免下方 semanticMatchCategory 把"裙子"错归一成"连衣裙"。
        $ambig = $this->detectCategoryAmbiguity($keyword, $message);
        if ($ambig['ambiguous']) {
            return [
                'is_purchase' => true,
                'keyword'     => $ambig['term'],
                'need_advice' => false,
                'filters'     => array(),
                'ambiguous'   => true,
                'term'        => $ambig['term'],
                'candidates'  => $ambig['candidates'],
            ];
        }

        // --- 策略3：语义向量/BM25 增强（解决纯字符串匹配漏识别，如"保温杯"不在词表也能认出）---
        // 语义匹配只用于"识别购物意图"（命中即视为已识别品类，is_purchase=true），
        // 但【不再用命中词覆盖原关键词】——覆盖会触发"把具体子类错归到泛代表亲"的坑
        // （皮鞋→运动鞋、榴莲→梨、旗袍→垃圾袋…）。关键词归一只走受控别名白名单
        // （normalizePurchaseKeyword），其余具体词一律保留原词，反而更精准。
        // 注意：语义命中本身不足以判定为购物——"今天天气真好"会因共享单字(气→空气净化器)
        // 被 BM25 误命中，故最终是否算购物意图，由下方「意图真实性校验」用 isProductNoun 把关。
        if (!empty($keyword)) {
            $semKw = $this->semanticMatchCategory($keyword, $message);
            if ($semKw !== '') {
                $isPurchase = true;
            }
            // 仅按受控别名表归一（如 保温杯→水杯、跑步鞋→运动鞋）；具体词保留原词
            $norm = $this->normalizePurchaseKeyword($keyword);
            if ($norm !== $keyword) {
                $keyword = $norm;
            }
        }

        // --- 多轮上下文指代解析 ---
        // 若当前消息无明显商品词，但含"更便宜/第二个/红色的/那个"等指代/筛选词，
        // 且上一轮是购物意图，则沿用上一轮的真实关键词，让助手"听懂"延续对话。
        $refineWords = ['便宜', '贵', '第二个', '第一个', '第1个', '第2个', '红色', '黑色', '白色',
            '蓝色', '那个', '这款', '这款', '另一', '其它', '其他', '再推荐', '还有', '更多', '换个'];
        $hasRefine = false;
        foreach ($refineWords as $w) {
            if (mb_strpos($mLower, $w) !== false) { $hasRefine = true; break; }
        }
        if ((!$isPurchase || empty($keyword)) && $hasRefine && !empty($history)) {
            // 从最近一条「用户」消息里找回真实购物意图（跳过 assistant 摘要，
            // 避免把"已为你推荐相关商品"这类历史文本误当成关键词）。
            for ($i = count($history) - 1; $i >= 0; $i--) {
                if (empty($history[$i]['role']) || $history[$i]['role'] !== 'user') {
                    continue;
                }
                $prev = isset($history[$i]['content']) ? $history[$i]['content'] : '';
                if ($prev) {
                    $prevIntent = $this->analyzeIntentStandalone($prev);
                    if ($prevIntent['is_purchase'] && !empty($prevIntent['keyword'])) {
                        $keyword = $prevIntent['keyword'];
                        $isPurchase = true;
                        // 指代本身也算"求筛选/求更多"，需要导购点评
                        break;
                    }
                }
            }
        }

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

        // --- 策略5：关键词命中泛品类词表（isGenericCategory），也判为购物意图 ---
        // 维护说明：$productTerms 与 isGenericCategory() 的 $generic 是两份品类词表，
        // 这里以 isGenericCategory 兜底，避免"水杯"这类词因漏列而不被识别。
        if (!$isPurchase && !empty($keyword) && $this->isGenericCategory($keyword)) {
            $isPurchase = true;
        }

        // --- 是否需要 AI 导购点评（求推荐/求对比/带偏好筛选/多轮延续） ---
        // 仅在「确为购物意图」的前提下判定，避免闲聊（如"今天天气怎么样"）被误判购物。
        $adviceWords = ['推荐', '值得', '哪个好', '怎么选', '区别', '对比', '排行',
            '预算', '平价', '性价比', '高端', '入门', '适合', '送礼', '礼物', '学生', '上班族',
            '便宜', '贵', '更多', '还有', '换个', '另一', '红色', '黑色', '白色', '蓝色',
            '牌子', '牌子好', '哪款好', '怎么挑', '求推荐', '帮忙选', '帮选'];
        $needAdvice = false;
        if ($isPurchase) {
            foreach ($adviceWords as $w) {
                if (mb_strpos($mLower, $w) !== false) { $needAdvice = true; break; }
            }
        }

        // --- 购物意图真实性校验 ---
        // 语义命中 / 关键词可能由 BM25 单字重叠误判（如"天气"的"气"→"空气净化器"、"心情"的"天"→"天幕"），
        // 故最终是否算购物意图，必须确认「原关键词本身是商品名词」。否则「今天天气真好」
        // 「今天心情不错」这类纯闲聊会被误判为购物。
        // isProductNoun 综合：泛品类 / 具体型号 / 品类词表 / 具体子类 / 别名表 / SYNONYMS 键 / 商品字根。
        if ($isPurchase && !empty($keyword) && !$this->isProductNoun($keyword)) {
            $isPurchase = false;
        }

        // 结构化抽取筛选维度（预算/用途场景/人群/品牌/特殊需求），
        // 仅当确为购物意图时才抽取，避免闲聊误抽。
        $filters = $isPurchase && !empty($keyword)
            ? $this->extractPurchaseFilters($message, $keyword)
            : array();

        return [
            'is_purchase' => $isPurchase && !empty($keyword),
            'keyword'     => $keyword,
            'need_advice' => $needAdvice,
            'filters'     => $filters,
        ];
    }

    /**
     * 无上下文的纯意图判定（供多轮指代时递归解析上一轮，避免无限递归）
     */
    private function analyzeIntentStandalone($message)
    {
        $purchaseWords = ['买', '购买', '下单', '想要', '需要', '求', '找', '搜', '搜一下', '查', '查一下',
            '哪里买', '哪个好', '怎么买', '链接', '优惠券', '券', '优惠', '折扣', '活动', '促销',
            '推荐', '值得', '怎么样', '测评', '价格', '多少钱', '排行', '对比', '同款', '平替', '送礼'];

        $isPurchase = false;
        $mLower = mb_strtolower(strip_tags($message));
        foreach ($purchaseWords as $word) {
            if (mb_strpos($mLower, $word) !== false) { $isPurchase = true; break; }
        }
        $keyword = $this->extractKeyword($message);
        // 与 analyzeIntent 保持一致：纯品类词（水杯/保温杯等）也算购物意图，
        // 否则多轮指代时历史里的"保温杯"会被误判为非购物，导致无法沿用上一轮关键词。
        if (!$isPurchase && !empty($keyword) && $this->isGenericCategory($keyword)) {
            $isPurchase = true;
        }
        return ['is_purchase' => $isPurchase && !empty($keyword), 'keyword' => $keyword];
    }

    /**
     * 语义匹配品类：用本地语义 SDK（ZhiCms\ext\VectorService）判断关键词是否
     * 语义上属于某电商品类，并返回归一化后的标准品类词。
     *
     * 解决纯字符串匹配的两大短板：
     *  1. 词表覆盖不全（"保温杯"不在 productTerms，但语义上≈"水杯"簇）→ 仍能识别；
     *  2. 同义词/别名（"跑步鞋"≈"运动鞋"、"游戏本"≈"电脑"）→ 统一归一为标准词，
     *     便于后续用同一关键词搜索/转链，避免同物不同词导致结果漂移。
     *
     * @param string $keyword 已提取的候选关键词
     * @return string 归一化品类词（命中）或 ''（不相关）
     */
    /**
     * 精选主流电商品类词库（与 productTerms 语义簇对齐），用于语义匹配 + 意图真实性校验白名单。
     * 注意：不能用 VectorService 内置 SYNONYMS 的全部 223 个同义词作词库，
     * 那样太宽会导致"讲笑话"→"麦克风"这类非购物词被误判为购物。
     */
    private function getCategoryVocab()
    {
        static $vocab = array(
            '水杯', '保温杯', '手机', '电脑', '笔记本', '平板', '耳机', '手表', '鞋子', '运动鞋',
            '衣服', '连衣裙', '裤子', '外套', '包', '相机', '电视', '空调', '冰箱', '洗衣机',
            '被子', '枕头', '床单', '文具', '玩具', '充电宝', '键盘', '鼠标', '雨伞', '毛巾',
            '洗发水', '牙膏', '零食', '咖啡', '奶粉', '面膜', '口红', '防晒', '扫地机器人',
            '电饭煲', '吹风机', '剃须刀', '行李箱', '抱枕', '窗帘', '灯具', '加湿器',
            '除湿机', '空气净化器', '净水器', '饮水机', '破壁机', '空气炸锅', '凉席', '滑雪板',
            '水果', '蔬菜', '牛肉', '猪肉', '鸡肉', '羊肉', '海鲜', '鸡蛋', '牛奶', '酸奶',
            '咖啡', '茶', '酒', '白酒', '水', '饮料', '奶粉', '纸尿裤', '辅食', '玩具', '绘本',
            '猫粮', '狗粮', '猫砂', '行车记录仪', '脚垫', '打印纸', '墨盒', '电池',
            '四件套', '被套', '保鲜膜', '垃圾袋', '调料', '维生素', '口罩', '体温计', '血压计',
            '鱼', '三文鱼', '坚果', '零食',
            '杂粮', '大米', '美妆', '饰品', '鲜花', '滋补', '家居', '汽车',
        );
        return $vocab;
    }

    /**
     * 语义匹配品类：用本地语义 SDK（ZhiCms\ext\VectorService）判断关键词是否
     * 语义上属于某电商品类，并返回归一化后的标准品类词。
     *
     * 解决纯字符串匹配的两大短板：
     *  1. 词表覆盖不全（"保温杯"不在 productTerms，但语义上≈"水杯"簇）→ 仍能识别；
     *  2. 同义词/别名（"跑步鞋"≈"运动鞋"、"游戏本"≈"电脑"）→ 统一归一为标准词，
     *     便于后续用同一关键词搜索/转链，避免同物不同词导致结果漂移。
     *
     * @param string $keyword 已提取的候选关键词
     * @return string 归一化品类词（命中）或 ''（不相关）
     */
    private function semanticMatchCategory($keyword, $contextText = '')
    {
        // 歧义词（苹果=水果/手机 等）优先用上下文消歧，避免纯语义向量偏到错误义项
        if ($contextText !== '') {
            $dis = \ZhiCms\ext\Vector\Bm25Index::disambiguate($keyword, $contextText);
            if ($dis !== '') {
                return $dis;
            }
        }
        static $vs = null;
        if ($vs === null) {
            try {
                $vs = new \ZhiCms\ext\VectorService();
            } catch (\Throwable $e) {
                return '';
            }
        }
        // 并入具体子类词表：避免"百褶裙"因与"连衣裙"字面相似被错归一成后者，
        // 让已明确的子类在语义匹配中保持原样（同时供意图真实性校验白名单复用）。
        $vocab = array_merge($this->getCategoryVocab(), $this->getAllSubTypes());
        try {
            // 第一步：在精选品类白名单上匹配（BM25 阈值 >=6.0 / 语义 >=0.45）
            $res = $vs->matchCategory($keyword, $vocab);
            if ($res['hit']) {
                if ($res['method'] === 'bm25' && $res['score'] >= 6.0) {
                    return $res['keyword'];
                }
                if ($res['method'] === 'semantic' && $res['score'] >= 0.45) {
                    return $res['keyword'];
                }
            }
            // 第二步：回退到全量同义词标准词（覆盖品牌词/具体单品，如"茅台"→"白酒"、
            // "三只松鼠"→"零食"）。全量词典更宽，故仅接受 BM25 字面/同义强匹配（>=6.0），
            // 不使用语义相似度，避免把闲聊词误判为品类（闲聊词与品类词字面无重叠，BM25=0 自然不过）。
            $allCats = array_keys(\ZhiCms\ext\Vector\Bm25Index::SYNONYMS);
            $res2 = $vs->matchCategory($keyword, $allCats);
            if ($res2['hit'] && $res2['method'] === 'bm25' && $res2['score'] >= 6.0) {
                return $res2['keyword'];
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }

    /**
     * 通用语义匹配：在任意标准词表上用本地语义 SDK（VectorService = BM25 + 语义向量）做匹配，
     * 返回命中的标准词（或 ''）。供「场景 / 人群 / 特殊需求」等维度的自然语言 → 标准词归一。
     *
     * 与 semanticMatchCategory 同源，但词表可自由传入（不再限定品类白名单），
     * 从而把"送女友""学生党""防水"这类表达映射到标准筛选维度值。
     *
     * @param string $text   待匹配文本（通常是用户原话或关键词）
     * @param array  $vocab  标准词表
     * @param string $contextText 上下文（用于歧义消歧，可空）
     * @return string 命中的标准词 / ''
     */
    private function semanticMatchFromVocab($text, array $vocab, $contextText = '')
    {
        $text = trim($text);
        if ($text === '' || empty($vocab)) {
            return '';
        }
        // 歧义消歧（如"苹果"在场景语境下的偏向）优先
        if ($contextText !== '') {
            $dis = \ZhiCms\ext\Vector\Bm25Index::disambiguate($text, $contextText);
            if ($dis !== '' && in_array($dis, $vocab, true)) {
                return $dis;
            }
        }
        static $vs = null;
        if ($vs === null) {
            try {
                $vs = new \ZhiCms\ext\VectorService();
            } catch (\Throwable $e) {
                return '';
            }
        }
        try {
            $res = $vs->matchCategory($text, $vocab);
            if ($res['hit']) {
                if ($res['method'] === 'bm25' && $res['score'] >= 6.0) {
                    return $res['keyword'];
                }
                if ($res['method'] === 'semantic' && $res['score'] >= 0.45) {
                    return $res['keyword'];
                }
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }

    /**
     * 当前季节（随真实日期动态变化）。用于"反季不主动推荐"：
     * 现在是夏天就不主动推冬装，除非用户明确要冬装。
     */
    /**
     * 当前季节（以二十四节气近似划分，比单纯按月份更贴近生活实际）。
     * 立春~立夏=春，立夏~立秋=夏，立秋~立冬=秋，立冬~次年立春=冬。
     * 例如 8 月下旬（立秋后）即算秋季，符合"现在正是秋天"的体感。
     */
    private function getSeason()
    {
        $m = (int)date('n');
        $d = (int)date('j');
        $md = $m * 100 + $d;
        if ($md >= 204 && $md < 505) return '春';   // 2/4 立春 ~ 5/5 立夏
        if ($md >= 505 && $md < 807) return '夏';   // 5/5 立夏 ~ 8/7 立秋
        if ($md >= 807 && $md < 1107) return '秋';  // 8/7 立秋 ~ 11/7 立冬
        return '冬';                                // 11/7 立冬 ~ 次年2/4 立春
    }

    /**
     * 泛品类 → 具体子类的歧义映射。
     * 用于：用户只说"裙子/裤子/鞋子"等泛类时，不直接猜一个（容易猜错，如裙子≠连衣裙），
     * 而是列出候选子类让用户确认，做到"想他所想"。
     */
    private function getCategorySubTypes()
    {
        static $map = array(
            '裙子' => ['连衣裙', '半身裙', '百褶裙', '吊带裙', 'a字裙', '短裙', '长裙', '鱼尾裙', '牛仔裙', '蛋糕裙', '伞裙'],
            '裤子' => ['牛仔裤', '休闲裤', '运动裤', '西裤', '短裤', '阔腿裤'],
            '鞋子' => ['运动鞋', '板鞋', '高跟鞋', '凉鞋', '靴子', '帆布鞋', '拖鞋', '皮鞋', '马丁靴', '乐福鞋', '玛丽珍鞋', '商务鞋', '老爹鞋', '切尔西靴', '雪地靴', '豆豆鞋', '穆勒鞋', '小白鞋', '篮球鞋', '跑步鞋', '休闲鞋'],
            '鞋'   => ['运动鞋', '板鞋', '高跟鞋', '凉鞋', '靴子', '帆布鞋'],
            '户外' => ['帐篷', '睡袋', '天幕', '登山杖', '冲锋衣', '速干衣', '露营灯', '野餐垫', '登山包', '折叠椅'],
            '包'   => ['双肩包', '单肩包', '斜挎包', '手提包', '钱包', '行李箱'],
            '衣服' => ['外套', '卫衣', 't恤', '衬衫', '毛衣', '连衣裙', '裤子'],
            '上衣' => ['卫衣', 't恤', '衬衫', 'polo衫', '针织衫', '毛衣'],
            '电脑' => ['笔记本', '台式机', '游戏本', '轻薄本', '一体机'],
            '手机' => ['智能手机', '游戏手机', '拍照手机', '老人机', '折叠屏手机'],
            '表'   => ['智能手表', '机械表', '石英表', '运动手表'],
            '手表' => ['智能手表', '机械表', '石英表', '运动手表'],
            '耳机' => ['降噪耳机', '运动耳机', '头戴耳机', '真无线耳机'],
            '相机' => ['微单', '单反', '卡片机', '运动相机', '无人机'],
            '外套' => ['羽绒服', '大衣', '风衣', '夹克', '冲锋衣', '卫衣'],
            // 水果/食品：按当季应季排序（吃喝也要贴近生活，反季大棚口感差）
            '水果' => ['苹果', '橘子', '橙子', '柚子', '梨', '葡萄', '西瓜', '草莓', '车厘子', '柿子', '冬枣', '石榴', '猕猴桃', '哈密瓜', '芒果', '荔枝', '桃子', '香蕉'],
        );
        return $map;
    }

    /** 所有具体子类（扁平、小写），用于判断关键词是否已是明确子类 */
    private function getAllSubTypes()
    {
        static $all = null;
        if ($all === null) {
            $all = array();
            foreach ($this->getCategorySubTypes() as $subs) {
                foreach ($subs as $s) { $all[] = mb_strtolower($s); }
            }
            $all = array_values(array_unique($all));
        }
        return $all;
    }

    /** 判断关键词是否为已知品类（白名单 + 具体子类），用于意图真实性校验 */
    private function isKnownCategory($kw)
    {
        return in_array($kw, $this->getCategoryVocab(), true)
            || in_array(mb_strtolower($kw), $this->getAllSubTypes(), true);
    }

    /**
     * 受控「商品别名归一」白名单。
     *
     * 为什么不用 VectorService/Bm25Index 的 SYNONYMS 做大范围归一：
     * 那张词表里混了大量「品类枚举键」（如 水果=>[苹果,梨,榴莲]、鞋子=>[运动鞋,高跟鞋]），
     * 它们的列表是「该品类的成员清单」而非「同义词」。若据此把命中词覆盖原词，
     * 就会把「同品类的不同具体词」误判为同义，导致：
     *   皮鞋→运动鞋、榴莲→梨、高跟鞋→运动鞋、旗袍→垃圾袋（场景簇）…… 一大类错归一。
     *
     * 因此归一化只在下面这份「我们确实想要的别名」白名单内发生；其余具体词一律保留原词，
     * 反而更精准（电商搜索/转链用原词命中率更高）。语义匹配仍用于「识别购物意图」，
     * 只是不再用于覆盖关键词。
     *
     * @param string $kw 已提取的关键词
     * @return string 归一后的标准词（无别名则返回原词）
     */
    private function normalizePurchaseKeyword($kw)
    {
        $map = $this->getAliasMap();
        return isset($map[$kw]) ? $map[$kw] : $kw;
    }

    /**
     * 受控「商品别名归一」白名单（与 normalizePurchaseKeyword 共用）。
     * 为什么不用 VectorService/Bm25Index 的 SYNONYMS 做大范围归一：那张词表里混了大量
     * 「品类枚举键」（水果=>[苹果,梨,榴莲]、鞋子=>[运动鞋,高跟鞋]），它们的列表是成员清单而非同义词，
     * 据此把命中词覆盖原词会把「同品类的不同具体词」误判为同义（皮鞋→运动鞋、榴莲→梨、高跟鞋→运动鞋…）。
     * 所以归一化只在下面这份「我们确实想要的别名」内发生；其余具体词一律保留原词，反而更精准。
     *
     * @return array
     */
    private function getAliasMap()
    {
        static $map = array(
            // 杯具
            '保温杯' => '水杯', '焖烧杯' => '水杯', '吸管杯' => '水杯', '玻璃杯' => '水杯',
            // 数码别名
            '游戏本' => '电脑', '轻薄本' => '电脑', '笔记本' => '电脑',
            '跑步鞋' => '运动鞋', '篮球鞋' => '运动鞋', '板鞋' => '运动鞋', '球鞋' => '运动鞋', '老爹鞋' => '运动鞋',
            '蓝牙耳机' => '耳机', '真无线耳机' => '耳机', '头戴耳机' => '耳机', '降噪耳机' => '耳机',
            '智能手环' => '智能手表',
            // 美妆/个护
            '防晒霜' => '防晒', '防晒乳' => '防晒', '防晒喷雾' => '防晒',
            // 家居
            '扫地机' => '扫地机器人', '扫拖机' => '扫地机器人', '扫拖机器人' => '扫地机器人',
            // 品牌/专有 → 品类（仅保留少数高确定性映射）
            '茅台' => '白酒', '飞天茅台' => '白酒', '三只松鼠' => '零食', '良品铺子' => '零食',
        );
        return $map;
    }

    /**
     * 判断关键词是否「商品名词」——购物意图真实性校验的核心。
     * 仅当关键词确属商品/品牌/型号时才算，避免「今天天气真好」这类闲聊被语义 BM25 单字重叠误判为购物。
     * 综合以下信号：
     *  - 泛品类词（isGenericCategory）
     *  - 具体型号/品牌（looksLikeSpecificModel）
     *  - 品类词表（getCategoryVocab）/ 具体子类（getAllSubTypes，已补全皮鞋/马丁靴/帐篷…真实子类）
     *  - 别名白名单键（getAliasMap）
     *  - SYNONYMS 的全部键（品牌/单品词，如 茅台/三只松鼠/榴莲/牛油果/公文包）
     *  - 商品字根兜底（鞋/衣/裤/裙/果…，用于识别 novel 商品词如"小白鞋""空气炸锅"）
     */
    private function isProductNoun($kw)
    {
        $kw = mb_strtolower(trim($kw));
        if ($kw === '') {
            return false;
        }
        if ($this->isProductNounCore($kw)) {
            return true;
        }
        // 容错：关键词可能带残留的意图动词（"帐篷选""皮鞋推荐""防晒霜哪个牌子好"→"防晒霜哪个牌子"），
        // 去掉首尾一个非商品字再试一次，避免把真实商品需求误杀为闲聊。
        $n = mb_strlen($kw);
        if ($n > 2) {
            if ($this->isProductNounCore(mb_substr($kw, 0, -1))) {
                return true; // 去尾字
            }
            if ($this->isProductNounCore(mb_substr($kw, 1))) {
                return true; // 去首字
            }
        }
        return false;
    }

    /** isProductNoun 的核心判定（不含首尾容错），供 isProductNoun 多次调用 */
    private function isProductNounCore($kw)
    {
        if ($kw === '') {
            return false;
        }
        if ($this->isGenericCategory($kw)) {
            return true;
        }
        if ($this->looksLikeSpecificModel($kw)) {
            return true;
        }
        if (in_array($kw, $this->getCategoryVocab(), true)) {
            return true;
        }
        if (in_array($kw, $this->getAllSubTypes(), true)) {
            return true;
        }
        if (isset($this->getAliasMap()[$kw])) {
            return true;
        }
        if (in_array($kw, array_keys(\ZhiCms\ext\Vector\Bm25Index::SYNONYMS), true)) {
            return true;
        }
        if ($this->hasProductRoot($kw)) {
            return true;
        }
        return false;
    }

    /** 是否包含「商品名词字根」，用于兜底识别 novel 商品词（如"小白鞋""空气炸锅""牛油果"） */
    private function hasProductRoot($kw)
    {
        static $roots = array(
            '鞋', '靴', '衣', '裤', '裙', '包', '杯', '机', '器', '表', '镜', '帽', '袜', '巾', '床', '灯', '锅', '箱',
            '奶', '果', '茶', '妆', '肤', '发', '牙', '纸', '笔', '车', '琴', '椅', '架', '盆', '碗', '瓶', '罐', '粮', '砂', '绳', '窝',
            '衫', '袄', '袍', '帕', '袋', '桶', '篮', '盘', '碟', '勺', '刀', '锁', '墨', '棋', '球', '拍', '铃', '钟', '鼓', '号', '笛', '筝',
            '霜', '褥', '枕', '毯', '帘', '刷', '皂', '梳', '粉', '膏', '膜', '乳', '盐', '醋', '酱', '糖', '饼', '糕', '肠', '蛋', '肉', '鱼',
            '虾', '蟹', '贝', '菜', '瓜', '橘', '橙', '柚', '桃', '莓', '蕉', '萝', '茄', '椒', '菇', '豆', '米', '面', '油', '蜜', '枣', '杏',
            '荔', '芒', '李', '梅', '饺', '馄', '饮', '露', '领', '袖', '兜', '纽', '扣', '链', '牌', '章', '印',
        );
        foreach ($roots as $r) {
            if (mb_strpos($kw, $r) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 全域「应季画像」：覆盖吃喝住行（服饰/鞋包/水果/食品饮料/家居日用/户外旅行），
     * 每个品类在四季分别给出「应季关键词」(boost) 与「反季关键词」(down)。
     * 用于：① 搜索结果按当季主次软排序；② 歧义澄清选项按当季过滤/排序。
     * 这样用户说"裙子/水果"时，默认出现的是当季该买的款式，贴近真实生活。
     */
    private function getSeasonProfiles()
    {
        static $p = array(
            '服装' => array(
                '春' => array('boost' => ['春', '薄外套', '风衣', '针织', '卫衣', '衬衫', '长袖', '牛仔裤', '休闲裤', '开衫'], 'down' => ['冬', '羽绒', '加厚', '棉服', '厚毛衣']),
                '夏' => array('boost' => ['夏', '薄', '透气', '雪纺', '短袖', '凉', '冰丝', '网纱', '吊带', '防晒', '清爽', '短裙', 't恤', '短裤', '连衣裙', '半身裙', '凉感'], 'down' => ['冬', '羽绒', '加绒', '保暖', '毛', '棉服', '毛呢', '大衣', '风衣', '针织', '加厚', '毛衣']),
                '秋' => array('boost' => ['秋', '针织', '风衣', '薄外套', '长袖', '卫衣', '外套', '衬衫', '阔腿裤', '休闲裤', '毛衣', '针织裙'], 'down' => ['夏', '短袖', '雪纺', '吊带', '凉鞋', '短裤', '冰丝', '夏装']),
                '冬' => array('boost' => ['冬', '羽绒', '加绒', '保暖', '毛', '棉服', '毛呢', '大衣', '风衣', '针织', '加厚', '毛衣', '靴子', '冬裙', '加绒裤'], 'down' => ['夏', '薄', '透气', '雪纺', '短袖', '凉', '冰丝', '网纱', '吊带', '防晒', '清爽', '短裙', 't恤', '短裤', '夏装']),
            ),
            '鞋包' => array(
                '春' => array('boost' => ['小白鞋', '帆布鞋', '乐福鞋', '单鞋', '双肩包', '针织鞋'], 'down' => ['雪地靴', '棉鞋', '毛绒靴']),
                '夏' => array('boost' => ['凉鞋', '帆布鞋', '运动鞋', '单鞋', '草编', '拖鞋', '斜挎包', '编织包', '网面鞋'], 'down' => ['靴子', '雪地靴', '棉鞋', '毛绒', '厚底靴', '雪地']),
                '秋' => array('boost' => ['乐福鞋', '短靴', '马丁靴', '帆布鞋', '双肩包', '风衣鞋'], 'down' => ['凉鞋', '拖鞋', '夏款']),
                '冬' => array('boost' => ['靴子', '雪地靴', '棉鞋', '加绒鞋', '毛绒', '皮靴', '保暖鞋', '雪地'], 'down' => ['凉鞋', '拖鞋', '单鞋', '夏款']),
            ),
            '水果' => array(
                '春' => array('boost' => ['草莓', '樱桃', '菠萝', '芒果', '枇杷', '桑葚', '李子', '春见'], 'down' => ['西瓜', '荔枝', '柿子']),
                '夏' => array('boost' => ['西瓜', '荔枝', '桃子', '葡萄', '哈密瓜', '芒果', '杨梅', '李子', '甜瓜', '火龙果', '百香果', '香瓜'], 'down' => ['柚子', '柿子', '梨', '苹果', '冬枣']),
                '秋' => array('boost' => ['柚子', '石榴', '梨', '苹果', '柿子', '冬枣', '橘子', '猕猴桃', '葡萄', '板栗', '山楂'], 'down' => ['西瓜', '荔枝', '杨梅', '香瓜']),
                '冬' => array('boost' => ['橙子', '橘子', '砂糖橘', '车厘子', '草莓', '柚子', '苹果', '猕猴桃', '冬枣'], 'down' => ['西瓜', '荔枝', '桃子', '杨梅']),
            ),
            '食品饮料' => array(
                '春' => array('boost' => ['春茶', '新茶', '清淡', '野菜', '春笋', '青团'], 'down' => ['冰镇', '冷饮']),
                '夏' => array('boost' => ['冷饮', '冰镇', '绿豆', '酸梅汤', '西瓜', '凉茶', '冰淇淋', '气泡水', '冰粉', '椰汁'], 'down' => ['热饮', '姜茶', '热可可', '滋补', '进补', '火锅']),
                '秋' => array('boost' => ['润燥', '梨', '蜂蜜', '银耳', '秋补', '滋补', '火锅', '板栗', '蟹', '桂圆'], 'down' => ['冰镇', '冷饮', '凉茶']),
                '冬' => array('boost' => ['热饮', '姜茶', '热可可', '滋补', '进补', '火锅', '羊肉', '鸡汤', '红枣', '桂圆', '热咖啡'], 'down' => ['冰镇', '冷饮', '凉茶', '冰淇淋']),
            ),
            '家居日用' => array(
                '春' => array('boost' => ['除螨', '晒被', '换季', '收纳', '新风', '春被'], 'down' => ['电热毯', '羽绒被', '暖气']),
                '夏' => array('boost' => ['凉席', '空调', '风扇', '防晒', '驱蚊', '灭蚊', '冰垫', '竹席', '夏凉被', '凉感'], 'down' => ['电热毯', '羽绒被', '暖气', '暖宝宝', '厚被']),
                '秋' => array('boost' => ['润燥', '加湿器', '秋被', '薄被', '换季收纳', '除螨', '棉被'], 'down' => ['凉席', '冰垫', '夏凉被']),
                '冬' => array('boost' => ['电热毯', '羽绒被', '暖气', '暖宝宝', '加湿器', '厚被', '毛毯', '取暖器', '棉被'], 'down' => ['凉席', '冰垫', '夏凉被', '凉感']),
            ),
            '户外旅行' => array(
                '春' => array('boost' => ['春游', '踏青', '赏花', '露营', '风筝'], 'down' => []),
                '夏' => array('boost' => ['避暑', '海岛', '泳衣', '防晒衣', '帐篷', '溯溪', '漂流', '水上'], 'down' => ['滑雪', '温泉']),
                '秋' => array('boost' => ['秋游', '踏青', '登山', '露营', '赏枫', '徒步', '骑行'], 'down' => []),
                '冬' => array('boost' => ['滑雪', '温泉', '保暖装备', '避寒', '冰雪'], 'down' => ['泳衣', '溯溪', '漂流']),
            ),
            // 手机数码及配件：季节敏感度低（不像衣服那样反季），主要随「开学/年货/换新」等场景走，
            // 故画像只做极轻量方向性信号，避免把手机/耳机按季节误排。场景（送礼/学生）才是排序主力。
            '手机数码' => array(
                '春' => array('boost' => ['新款', '春季新品', '返校'], 'down' => []),
                '夏' => array('boost' => ['散热', '降温', '防晒壳', '便携', '充电宝', '防水'], 'down' => []),
                '秋' => array('boost' => ['新款', '开学', '返校', '换新'], 'down' => []),
                '冬' => array('boost' => ['保暖', '暖手', '充电宝', '加绒壳', '年货'], 'down' => []),
            ),
            // 生活杂物（收纳/清洁/工具/家杂）：有一定季节感（夏驱蚊降温、冬取暖收纳）
            '生活杂物' => array(
                '春' => array('boost' => ['换季收纳', '清洁', '除螨', '晾晒', '整理'], 'down' => ['取暖', '暖宝宝']),
                '夏' => array('boost' => ['驱蚊', '灭蚊', '降温', '凉感', '清洁', '除湿', '除霉'], 'down' => ['取暖', '暖手', '厚']),
                '秋' => array('boost' => ['换季收纳', '除螨', '加湿', '清洁', '整理'], 'down' => ['凉席', '冰垫', '夏凉']),
                '冬' => array('boost' => ['取暖', '保暖', '加湿', '收纳', '除螨', '清洁'], 'down' => ['驱蚊', '凉席', '冰垫', '夏凉']),
            ),
            // 日常快消品（纸巾/洗护/日化/个护）：季节感弱，仅夏防晒驱蚊、冬护手润唇等轻微信号
            '快消品' => array(
                '春' => array('boost' => ['换季', '敏感肌', '保湿'], 'down' => []),
                '夏' => array('boost' => ['防晒', '驱蚊', '清凉', '洗发', '沐浴', '止汗'], 'down' => []),
                '秋' => array('boost' => ['保湿', '滋润', '护手'], 'down' => []),
                '冬' => array('boost' => ['护手', '润唇', '身体乳', '保湿', '滋润'], 'down' => []),
            ),
            // 特产 / 土特产 / 手工特产：高季节性 + 强地域性（大闸蟹秋、腊肉冬、春茶春、手工非遗常年可送礼）
            '特产手工' => array(
                '春' => array('boost' => ['春茶', '新茶', '明前', '青团', '野菜', '春笋', '手工', '非遗', '老字号', '地方'], 'down' => ['腊肉', '腊味', '大闸蟹', '冬补']),
                '夏' => array('boost' => ['消暑', '绿豆', '凉茶', '冰粉', '水果干', '手工', '非遗', '地方'], 'down' => ['火锅', '滋补', '腊肉', '冬补']),
                '秋' => array('boost' => ['大闸蟹', '蟹', '秋补', '板栗', '柿子', '石榴', '柚子', '月饼', '特产', '手工', '非遗', '礼盒', '养生', '地标'], 'down' => ['冰镇', '冷饮']),
                '冬' => array('boost' => ['腊肉', '腊味', '年货', '坚果', '滋补', '进补', '特产', '手工', '非遗', '礼盒', '暖身', '地标'], 'down' => ['冷饮', '冰镇']),
            ),
            // 通用：无明确品类时，仅用「服饰+家居」的强反季信号做软降权，避免误伤食品等
            '通用' => array(
                '春' => array('boost' => [], 'down' => ['羽绒', '加厚', '棉服']),
                '夏' => array('boost' => ['夏', '薄', '透气', '凉', '防晒'], 'down' => ['冬', '羽绒', '加绒', '保暖', '棉服', '毛呢', '大衣', '加厚']),
                '秋' => array('boost' => ['秋', '针织', '风衣', '外套'], 'down' => ['夏装', '短袖', '雪纺', '吊带']),
                '冬' => array('boost' => ['冬', '羽绒', '加绒', '保暖', '棉服', '大衣'], 'down' => ['夏', '薄', '透气', '雪纺', '短袖', '凉', '冰丝']),
            ),
        );
        return $p;
    }

    /** 根据搜索关键词判定所属「应季品类」，用于选择对应的季节画像 */
    private function detectSeasonCategory($keyword)
    {
        $k = mb_strtolower(trim($keyword));
        $map = array(
            '服装'     => ['裙子', '裤', '衣服', '上衣', '外套', '毛衣', '卫衣', '衬衫', '连衣裙', '半身裙', 't恤', '针织', '大衣', '风衣', '羽绒', '打底', '裙'],
            '鞋包'     => ['鞋', '靴', '包', '凉鞋', '运动鞋', '双肩包', '单肩包', '斜挎', '手提包', '钱包', '行李箱'],
            '水果'     => ['水果', '苹果', '梨', '橘', '橙', '柚子', '西瓜', '葡萄', '草莓', '车厘子', '柿子', '桃', '芒果', '荔枝', '香蕉', '猕猴桃', '哈密瓜', '冬枣', '石榴', '菠萝', '枇杷', '樱桃', '杨梅', '香瓜', '火龙果'],
            '食品饮料' => ['零食', '饮料', '茶', '咖啡', '酒', '水', '牛奶', '酸奶', '冰淇淋', '冷饮', '热饮', '果汁', '坚果', '滋补', '干货', '月饼', '粽子'],
            '家居日用' => ['被子', '枕', '凉席', '空调', '风扇', '加湿器', '取暖', '暖', '蚊', '收纳', '床', '四件套', '被套', '抱枕', '窗帘', '灯具', '毛巾', '拖把'],
            '手机数码' => ['手机', '数码', '电脑', '笔记本', '平板', 'ipad', 'iphone', '耳机', '相机', '手机壳', '充电器', '充电宝', '数据线', '键盘', '鼠标', '智能手表', '手环', '无人机', '显示器', '路由', '音箱', '内存', '固态', '硬盘', '显卡', 'cpu', '配件', '周边'],
            '生活杂物' => ['收纳', '清洁', '拖把', '扫把', '垃圾袋', '保鲜', '五金', '工具', '胶带', '挂钩', '杂物', '日用', '家杂', '抹布', '刷子', '水桶', '衣架', '置物', '整理'],
            '快消品' => ['纸巾', '抽纸', '洗护', '牙膏', '牙刷', '洗发', '沐浴', '洗衣', '洗衣液', '日化', '个护', '湿巾', '棉柔巾', '香皂', '洗洁精', '快消', '囤货', '耗材'],
            '特产手工' => ['特产', '土特产', '手工', '非遗', '农副', '地标', '老字号', '匠心', '原产', '地方', '伴手', '养生', '滋补品', '坚果礼', '糕点', '卤味', '腊味', '大闸蟹'],
            '户外旅行' => ['帐篷', '露营', '登山', '徒步', '旅行', '户外', '泳衣', '滑雪', '温泉', '踏青', '春游', '秋游'],
        );
        foreach ($map as $cat => $keys) {
            foreach ($keys as $key) {
                if (mb_stripos($k, $key) !== false) return $cat;
            }
        }
        return '通用';
    }

    /**
     * 取某品类在某季节的应季/反季关键词；支持南北差异：
     *  - north（北方偏冷）：叠加「更冷一季」的应季信号 + 压低「更暖一季」的反季信号；
     *  - south（南方偏暖）：叠加「更暖一季」的应季信号 + 压低「更冷一季」的反季信号。
     * 关键去重：凡是同时出现在 boost 里的词（如"风衣"既在秋应季又在冬反季），一律从 down 剔除，
     * 避免把当季该推的款误伤；而真正的反季词（加绒/毛呢/夏款等）仍正常压低。
     */
    private function getSeasonKeywords($category, $season, $bias = '')
    {
        $profiles = $this->getSeasonProfiles();
        $cat = isset($profiles[$category]) ? $category : '通用';
        $prof = $profiles[$cat][$season] ?? array('boost' => array(), 'down' => array());
        $boost = $prof['boost'];
        $down = $prof['down'];
        if ($bias === 'north') {
            $colder = $this->neighborSeason($season, 'cold');
            $warmer = $this->neighborSeason($season, 'warm');
            $boost = array_merge($boost, $profiles[$cat][$colder]['boost'] ?? array());
            $down  = array_merge($down, $profiles[$cat][$warmer]['down'] ?? array());
        } elseif ($bias === 'south') {
            $warmer = $this->neighborSeason($season, 'warm');
            $colder = $this->neighborSeason($season, 'cold');
            $boost = array_merge($boost, $profiles[$cat][$warmer]['boost'] ?? array());
            $down  = array_merge($down, $profiles[$cat][$colder]['down'] ?? array());
        }
        // 去重：boost 中的词绝不再进 down（如"风衣"），避免当季款被反季词误伤
        $boostSet = array_fill_keys(array_unique($boost), true);
        $down = array_values(array_unique(array_filter($down, function ($w) use ($boostSet) {
            return empty($boostSet[$w]);
        })));
        return array(
            'boost' => array_values(array_unique($boost)),
            'down'  => $down,
        );
    }

    /** 季节邻近（cold=更冷一季，warm=更暖一季；已到极端则保持不变） */
    private function neighborSeason($season, $dir)
    {
        $next = array('春' => '夏', '夏' => '秋', '秋' => '冬', '冬' => '冬');
        $prev = array('冬' => '秋', '秋' => '夏', '夏' => '春', '春' => '春');
        return $dir === 'cold' ? $next[$season] : $prev[$season];
    }

    /** 从消息中识别南北地域偏好（贴近"南北差异"的真实体感） */
    private function detectRegion($msg)
    {
        if (preg_match('/(北方|东北|华北|西北|北京|天津|河北|山西|内蒙古|黑龙江|吉林|辽宁|山东|河南|陕西|甘肃|宁夏|新疆|哈尔滨|沈阳|长春|石家庄|太原|西安|兰州|银川|乌鲁木齐|青岛|济南)/u', $msg)) {
            return 'north';
        }
        if (preg_match('/(南方|华南|华东|西南|广东|海南|福建|广西|云南|贵州|深圳|广州|厦门|海口|三亚|南宁|昆明|贵阳|杭州|上海|南京|武汉|长沙|成都|重庆|南昌|合肥|苏州|无锡|宁波|温州)/u', $msg)) {
            return 'south';
        }
        return '';
    }

    /**
     * 按季节过滤/排序澄清候选子类：用应季画像打分，剔除明显反季项（至少保留 3 个），
     * 并把应季项排前；候选过多时最多展示 8 个，避免选项炸裂。
     */
    private function getSeasonFilteredCandidates($term, $season, $bias = '')
    {
        $map = $this->getCategorySubTypes();
        if (!isset($map[$term])) return array();
        $subs = $map[$term];
        $cat = $this->detectSeasonCategory($term);
        $sk = $this->getSeasonKeywords($cat, $season, $bias);
        $scored = array();
        foreach ($subs as $s) {
            $low = mb_strtolower($s);
            $sc = 0;
            foreach ($sk['boost'] as $b) {
                if (mb_stripos($low, $b) !== false) { $sc += 1; break; }
            }
            foreach ($sk['down'] as $d) {
                if (mb_stripos($low, $d) !== false) { $sc -= 2; break; }
            }
            $scored[] = array('name' => $s, 'sc' => $sc);
        }
        $kept = array_filter($scored, function ($x) { return $x['sc'] >= 0; });
        if (count($kept) < 3) {
            $kept = $scored;
        }
        usort($kept, function ($a, $b) { return $b['sc'] <=> $a['sc']; });
        if (count($kept) > 8) {
            $kept = array_slice($kept, 0, 8);
        }
        return array_map(function ($x) { return $x['name']; }, $kept);
    }

    /**
     * 类目歧义检测：用户只说泛类（如"裙子"）时，不直接归一成某个子类（易猜错），
     * 而是返回候选子类让用户确认。若消息已含具体子类/品牌/型号，则不歧义。
     *
     * @return array ['ambiguous'=>bool,'term'=>string,'candidates'=>array]
     */
    private function detectCategoryAmbiguity($keyword, $message)
    {
        $term = mb_strtolower(trim($keyword));
        if ($term === '') {
            return array('ambiguous' => false);
        }
        // 去掉前缀量词（"条裙子"→"裙子"、"个手机"→"手机"），避免量词残留导致误判为已明确
        $term = preg_replace('/^[条个只双件款件套把枚顶盏块片张]/u', '', $term);
        if ($term === '') {
            return array('ambiguous' => false);
        }
        $map = $this->getCategorySubTypes();
        $allSubs = $this->getAllSubTypes();
        // 关键词本身已是明确子类（如"百褶裙"）→ 不歧义
        if (in_array($term, $allSubs, true)) {
            return array('ambiguous' => false);
        }
        // 关键词是泛类（如"裙子"）→ 歧义（除非消息已指明具体子类，或含品牌/型号）
        if (isset($map[$term])) {
            foreach ($map[$term] as $sub) {
                if (mb_stripos($message, $sub) !== false) {
                    return array('ambiguous' => false, 'resolved' => $sub);
                }
            }
            // 含品牌/型号 → 视为明确需求，不再追问（如"华为手机""iphone15"）
            if (preg_match('/(iphone|ipad|huawei|华为|小米|红米|oppo|vivo|honor|荣耀|samsung|三星|联想|lenovo|dell|戴尔|hp|惠普|asus|华硕|nike|耐克|adidas|阿迪|安踏|李宁|优衣库|uniqlo|zara|无印良品|muji|dyson|戴森|sony|索尼|bose|skii|兰蔻|雅诗兰黛|迪奥|香奈儿)/iu', $message)
                || preg_match('/\d{2,4}/u', $message)) {
                return array('ambiguous' => false);
            }
            $season = $this->getSeason();
            $bias = $this->detectRegion($message);
            return array(
                'ambiguous' => true,
                'term'      => $term,
                'candidates' => $this->getSeasonFilteredCandidates($term, $season, $bias),
            );
        }
        return array('ambiguous' => false);
    }

    /**
     * 从用户消息中结构化抽取导购筛选维度（预算 / 用途场景 / 人群 / 品牌 / 特殊需求）。
     *
     * 这是「AI 导购听懂需求」的核心：把自然语言 → 可下发的结构化 filter，
     * 让预算/用途/场景真正参与搜索（而不只是写进 AI 文案）。
     *
     * 设计要点（满足"预算/用途随意图动态改变且有效"）：
     *  - 预算：先抓显式金额（"预算2000""300块"），无显式金额时按场景/人群语义推导档位
     *    （送礼物→中高档、学生党→低档、高端/旗舰→高档），做到"随意图动态改变"。
     *  - 用途/场景：用语义 SDK 在场景词表上归一（"送女友"→送礼、"出差"→商务出差），
     *    并据场景反向微调预算档位。
     *  - 品牌 / 特殊需求：正则 + 语义兜底。
     *
     * @param string $message 用户原始消息
     * @param string $keyword 已提取的商品关键词（用于品牌/特殊需求补匹配）
     * @return array ['price_min'=>int,'price_max'=>int,'scene'=>string,'audience'=>string,
     *                'brand'=>string,'feature'=>string]
     */
    private function extractPurchaseFilters($message, $keyword = '')
    {
        $msg  = $message;
        $low  = mb_strtolower($msg);
        $f = array(
            'price_min' => 0,
            'price_max' => 0,
            'scene'     => '',
            'audience'  => '',
            'brand'     => '',
            'feature'   => '',
            'color'     => '',
            'spec'      => '',
        );

        // ---------- 1. 显式预算金额（兼容 单值/区间/以下/以上/口语量词）----------
        $explicitBudget = 0;
        $price_min = 0;
        $price_max = 0;
        // (a) 区间：X到Y / X-Y / X~Y
        if (preg_match('/(\d{2,6})\s*[-~到至]\s*(\d{2,6})\s*(?:元|块|块钱|¥|￥|\$)?/u', $msg, $m)) {
            $price_min = (int)$m[1];
            $price_max = (int)$m[2];
        }
        // (b) 以下/以内/不超过/不到
        elseif (preg_match('/(?:不(?:超|高|多)过|不超过|至多|低于|不到|小于|以内|以下|封顶)\s*(\d{2,6})\s*(?:元|块|块钱|¥|￥|\$)?/u', $msg, $m)
                || preg_match('/(\d{2,6})\s*(?:元|块|块钱|¥|￥|\$)\s*(?:以内|以下|封顶)/u', $msg, $m)) {
            $price_max = (int)$m[1];
        }
        // (c) 以上/起/至少
        elseif (preg_match('/(?:至少|最少|不低于|高于|大于|以上|往上|起)\s*(\d{2,6})\s*(?:元|块|块钱|¥|￥|\$)?/u', $msg, $m)
                || preg_match('/(\d{2,6})\s*(?:元|块|块钱|¥|￥|\$)\s*(?:以上|起)/u', $msg, $m)) {
            $price_min = (int)$m[1];
        }
        // (d) 口语量词：两三百 / 三四千 / 一千 / 五千 ...
        elseif (preg_match('/([两二三三四五六七八九])\s*([百千])\s*(?:元|块|块钱|左右|多|出头)?/u', $msg, $m)) {
            $n = array('两'=>2,'二'=>2,'三'=>3,'四'=>4,'五'=>5,'六'=>6,'七'=>7,'八'=>8,'九'=>9)[$m[1]] ?? 2;
            $u = $m[2] === '千' ? 1000 : 100;
            $price_min = $n * $u;
            $price_max = ($n + 1) * $u;
        }
        // (e) 单值（原逻辑兜底）
        else {
            if (preg_match('/(?:预算|价位|价格|价钱|花|准备|打算)\s*[:：]?\s*(\d{2,6})\s*(?:元|块|块钱|¥|￥|\$)?/u', $msg, $m)
                || preg_match('/(\d{2,6})\s*(?:元|块|块钱|¥|￥|\$)\s*(?:以内|以下|左右|预算|的|的预算)?/u', $msg, $m)) {
                $explicitBudget = (int)$m[1];
            }
        }

        // ---------- 2. 用途/场景（语义归一） ----------
        $sceneVocab = array(
            '送礼', '日常自用', '旅行携带', '商务办公', '学生宿舍', '母婴照顾', '运动健身',
            '厨房烹饪', '卧室居家', '客厅娱乐', '户外露营', '通勤上班', '聚会约会', '新房装修', '车载出行',
        );
        // 先规则粗匹配，再语义兜底，确保"送女友/出差/健身"等都能落到标准场景
        $sceneRules = array(
            '送礼'     => '/(送|礼物|礼品|生日|节日|新年|圣诞|情人节|闺蜜|长辈|父母|对象|女友|男友|老婆|老公|亲戚|朋友)/u',
            '旅行携带' => '/(旅行|旅游|出差|出门|户外|露营|自驾|行李|便携)/u',
            '商务办公' => '/(办公|商务|开会|职场|出差办公|工作|公司|写字楼)/u',
            '学生宿舍' => '/(学生|上学|宿舍|考研|考研党|校园|课本|自习)/u',
            '母婴照顾' => '/(婴儿|宝宝|母婴|宝妈|小孩|儿童|孩子|孕妇|新生儿|幼儿园)/u',
            '运动健身' => '/(运动|健身|跑步|瑜伽|锻炼|球类|训练|健身房)/u',
            '厨房烹饪' => '/(厨房|烹饪|做饭|烘焙|下厨|煮饭|食材)/u',
            '卧室居家' => '/(卧室|居家|家里|家用|客厅|房间|休息|睡眠)/u',
            '客厅娱乐' => '/(客厅|电视|影院|游戏|娱乐|影音)/u',
            '户外露营' => '/(户外|露营|野餐|登山|徒步| Camping|爬山)/u',
            '通勤上班' => '/(通勤|上班|上下班|地铁|公交|代步)/u',
            '聚会约会' => '/(约会|聚会|派对|聚餐|相亲|婚礼)/u',
            '新房装修' => '/(装修|新房|搬家|入住|焕新|乔迁)/u',
            '车载出行' => '/(车载|开车|自驾游|车里|副驾|后座)/u',
        );
        foreach ($sceneRules as $std => $pat) {
            if (preg_match($pat, $msg)) { $f['scene'] = $std; break; }
        }
        if ($f['scene'] === '') {
            $f['scene'] = $this->semanticMatchFromVocab($keyword ?: $msg, $sceneVocab, $msg);
        }

        // ---------- 3. 人群 ----------
        $audienceVocab = array('女性', '男性', '学生', '上班族', '母婴', '长辈', '儿童', '运动人群', '宠物');
        $audienceRules = array(
            '女性'   => '/(女生|女的|她|闺蜜|女友|老婆|妻子|姐妹|女性|女生用|女士)/u',
            '男性'   => '/(男生|男的|他|男友|老公|丈夫|兄弟|男性|男士|汉子)/u',
            '学生'   => '/(学生|上学|考研|校园|宿舍|中学生|大学生|高中生)/u',
            '上班族' => '/(上班|职场|工作|白领|打工人|通勤)/u',
            '母婴'   => '/(婴儿|宝宝|母婴|宝妈|孕妇|新生儿|儿童|小孩|孩子)/u',
            '长辈'   => '/(老人|长辈|父母|爸妈|中老年|爷爷奶奶|外公外婆)/u',
            '儿童'   => '/(儿童|小孩|孩子|宝宝|小学生)/u',
            '宠物'   => '/(猫|狗|宠物|主子|猫主子|狗子)/u',
        );
        foreach ($audienceRules as $std => $pat) {
            if (preg_match($pat, $msg)) { $f['audience'] = $std; break; }
        }
        if ($f['audience'] === '') {
            $f['audience'] = $this->semanticMatchFromVocab($keyword ?: $msg, $audienceVocab, $msg);
        }
        // 送礼场景若未识别具体人群，默认"通用送礼"
        if ($f['scene'] === '送礼' && $f['audience'] === '') {
            $f['audience'] = '通用';
        }

        // ---------- 4. 品牌 ----------
        // 复用商品词表中的品牌片段，命中即取（保持与搜索关键词拼 brand 一致）
        $brandHints = array(
            '华为', 'huawei', '小米', 'mi', '红米', 'redmi', '苹果', 'apple', 'iphone', 'ipad',
            'oppo', 'vivo', '荣耀', 'honor', '三星', 'samsung', '联想', 'lenovo', '戴尔', 'dell',
            '华硕', 'asus', '惠普', 'hp', '美的', 'midea', '格力', '海尔', 'haier', '九阳',
            '耐克', 'nike', '阿迪达斯', 'adidas', '安踏', '李宁', '优衣库', 'uniqlo', '雅诗兰黛',
            '兰蔻', 'skii', 'sk2', '迪奥', '香奈儿', '欧莱雅', '珀莱雅', '华为', '索尼', 'sony', 'bose', 'dyson', '戴森',
        );
        foreach ($brandHints as $b) {
            if (mb_stripos($low, $b) !== false) { $f['brand'] = $b; break; }
        }

        // ---------- 5. 特殊需求 / 功能特征 ----------
        $featureVocab = array('防水', '便携', '大容量', '智能', '静音', '轻薄', '高颜值', '续航长', '快充', '护眼', '儿童适用', '可水洗', '折叠', '迷你');
        $featureRules = array(
            '防水'   => '/(防水|防泼溅|ipx|生活防水|游泳)/u',
            '便携'   => '/(便携|轻便|好带|小巧|迷你|口袋)/u',
            '大容量' => '/(大容量|大杯|大号|大屏幕|大内存|大空间)/u',
            '智能'   => '/(智能|ai|语音|联网|app控制|自动)/u',
            '静音'   => '/(静音|无声|低噪|安静)/u',
            '轻薄'   => '/(轻薄|超薄|轻巧|纤薄)/u',
            '高颜值' => '/(高颜值|好看|漂亮|颜值|美观|ins风|简约)/u',
            '续航长' => '/(续航|待机|长续航|电池耐用|一天一充)/u',
            '快充'   => '/(快充|闪充|充电快)/u',
            '护眼'   => '/(护眼|不伤眼|防蓝光|低蓝光)/u',
            '儿童适用' => '/(儿童|宝宝|婴儿|小孩|母婴)/u',
            '可水洗' => '/(可水洗|能洗|防水洗)/u',
            '折叠'   => '/(折叠|翻盖|对折)/u',
        );
        foreach ($featureRules as $std => $pat) {
            if (preg_match($pat, $msg)) { $f['feature'] = $std; break; }
        }
        if ($f['feature'] === '') {
            $f['feature'] = $this->semanticMatchFromVocab($keyword ?: $msg, $featureVocab, $msg);
        }

        // ---------- 5.5 排除/否定意图（不要/除了/以外/别买/非）----------
        // 让"不要苹果""除了华为都行"这类诉求真正生效：从搜索词剥离并剔除命中结果
        $f['exclude_brand'] = '';
        $f['exclude_feature'] = '';
        if (preg_match_all('/(?:不要|别买|别要|除了|除开|排除|以外|别选|不想要|不考虑|非)\s*([^，。,；;？?！!]+)/u', $msg, $negMs, PREG_SET_ORDER)) {
            foreach ($negMs as $nm) {
                $negTxt = trim($nm[1], ' 的');
                $hit = false;
                foreach ($brandHints as $b) {
                    if (mb_stripos($negTxt, $b) !== false) { $f['exclude_brand'] = $b; $hit = true; break; }
                }
                if ($hit) continue;
                foreach ($featureVocab as $fv) {
                    if (mb_stripos($negTxt, $fv) !== false) { $f['exclude_feature'] = $fv; break; }
                }
            }
        }

        // ---------- 5.6 颜色 / 规格属性抽取（让"红色""256g""55寸"也能参与排序匹配）----------
        $colorWords = array('红', '黑', '白', '蓝', '粉', '金', '银', '灰', '绿', '紫', '黄', '橙', '棕', '青', '香槟', '驼', '卡其', '莫兰迪');
        foreach ($colorWords as $c) {
            if (mb_strpos($msg, $c . '色') !== false) { $f['color'] = $c; break; }
        }
        if ($f['color'] === '') {
            foreach ($colorWords as $c) {
                if (preg_match('/(^|[^\x{4e00}-\x{9fa5}])' . $c . '(?=[^\x{4e00}-\x{9fa5}]|$)/u', $msg)) { $f['color'] = $c; break; }
            }
        }
        // 规格：容量/尺寸/功率等（如 256g、1t、500ml、55寸、42码、20000mah）
        if (preg_match('/(\d{1,4}\s*(?:g|gb|t|tb|ml|l|升|mah|ah|wh|w|寸|英寸|cm|毫米|mm|码|xl|xxl))/iu', $msg, $sm)) {
            $f['spec'] = strtolower(str_replace(' ', '', $sm[1]));
        } elseif (preg_match('/(大容量|大号|加长|加宽|加厚|超大|超薄|加绒)/u', $msg, $sm)) {
            $f['spec'] = $sm[1];
        }

        // ---------- 6. 预算档位推导（随意图动态改变） ----------
        // 无显式金额时，按场景/人群/价格档位词推导，让预算"随意图变化且有效"
        $tier = 0; // 0未定 1低档 2中档 3高档 4旗舰
        if (preg_match('/(便宜|实惠|平价|性价比|白菜价|省钱|低价|入门|学生价|低预算)/u', $msg)) $tier = 1;
        elseif (preg_match('/(中端|主流|正常|差不多|一般|中等)/u', $msg)) $tier = 2;
        elseif (preg_match('/(高端|旗舰|顶配|高配|高逼格|轻奢|品质|专业级|进口)/u', $msg)) $tier = 3;
        elseif (preg_match('/(极致|顶级|至臻|奢|最好|天花板|顶级的)/u', $msg)) $tier = 4;

        // 场景/人群对档位的语义微调
        if ($f['scene'] === '送礼' && $tier === 0) $tier = 3;          // 送礼默认中高档
        if ($f['audience'] === '学生' && $tier === 0) $tier = 1;       // 学生默认低档
        if ($f['audience'] === '长辈' && $tier === 0) $tier = 2;       // 长辈默认中档实用
        if ($f['scene'] === '母婴照顾' && $tier === 0) $tier = 2;

        // 预算：优先区间/以下/以上解析，其次单值，最后档位推导
        if ($price_max > 0 || $price_min > 0) {
            $f['price_max'] = $price_max;
            $f['price_min'] = $price_min;
            if ($price_max > 0 && $price_min == 0) {
                $f['price_min'] = (int)round($price_max * 0.6);
            }
            if ($price_min > 0 && $price_max == 0) {
                $f['price_max'] = (int)round($price_min * 1.8);
            }
        } elseif ($explicitBudget > 0) {
            // 单值作为上限，给出 0.6~1.0 的下限区间
            $f['price_max'] = $explicitBudget;
            $f['price_min'] = (int)round($explicitBudget * 0.6);
        } elseif ($tier > 0) {
            $ranges = array(
                1 => array(0, 300),
                2 => array(300, 1500),
                3 => array(1500, 5000),
                4 => array(5000, 0),
            );
            list($f['price_min'], $f['price_max']) = $ranges[$tier];
        }

        // ---------- 7. 季节性（随当前真实时间动态变化） ----------
        // 现在是秋天就以秋装/秋果为主，冬天就自动以冬装为主（除非用户明确指定反季）。
        // 同时识别南北地域：北方偏冷、南方偏暖，对同一季节的体感做差异化（南北差异）。
        // season：当前/用户指定的季节；season_explicit：用户是否明确指定了反季（指定后只加权不压低）；region：南北偏好。
        $season = $this->getSeason();
        $seasonExplicit = false;
        if (preg_match('/(冬|羽绒|加绒|棉服|保暖|雪地靴|毛呢|大衣|风衣|针织)/u', $msg)) {
            $season = '冬'; $seasonExplicit = true;
        } elseif (preg_match('/(春装|春款|春天)/u', $msg)) {
            $season = '春'; $seasonExplicit = true;
        } elseif (preg_match('/(秋装|秋款|秋天|换季)/u', $msg)) {
            $season = '秋'; $seasonExplicit = true;
        } elseif (preg_match('/(夏装|夏款|夏天|夏季|防晒|清凉)/u', $msg)) {
            $season = '夏'; $seasonExplicit = true;
        }
        $f['season'] = $season;
        $f['season_explicit'] = $seasonExplicit;
        $f['region'] = $this->detectRegion($msg);
        // 类目（供季节画像/排序选择）：优先从关键词判定；AI 增强可在后续补齐(见 enrichFiltersWithAi)
        $f['category'] = $this->detectSeasonCategory($keyword ?: $msg);

        // ---------- 8. 用户长期偏好回填（仅作软排序微调，绝不污染导购建议）----------
        // 仅回填"颜色"：风险极低（只影响排序里"颜色对味"的轻微加分，不进入 buildAdvice 文案）。
        // 注意：人群(audience)/场景(scene)等语义强的维度【不】自动回填——
        // 避免"上次送女友"被误套到本次"买游戏本"，导致点评写出"适合女性"这种错误结论。
        $prefs = $this->loadPrefs();
        if (!empty($prefs['color']) && $f['color'] === '') {
            $f['color'] = $prefs['color'];
        }

        return $f;
    }

    /**
     * 是否需要调用大模型做意图增强。
     * 只在「本地规则 + 语义都判不准」时触发，避免每次查询都打一次 LLM（省成本/省延迟）：
     *  - 规则连类目都判不出（category=通用）；
     *  - 关键词过短（≤2字，基本是废词）；
     *  - 消息含明确「场景/用途」模糊词（送长辈、开学、办公室、囤货、解压…）且关键词不算长。
     * 明确的「品牌+型号」类诉求（如 iphone15 红色 256g）不会触发。
     */
    private function needsAiEnrich(array $filters, $message, $keyword)
    {
        if (($filters['category'] ?? '') === '通用') {
            return true;
        }
        if (mb_strlen($keyword) <= 2) {
            return true;
        }
        $vaguePt = '/(送|买点|囤|添置|准备点|需要点|想买点|挑点|来点|整点|配点|缺|补点|家里|办公室|出差|旅游|旅行|开学|搬家|乔迁|装修|收纳|解压|实用|好物|小物件|东西|物件|礼|养生|孝敬|看望|孝敬长辈)/u';
        if (preg_match($vaguePt, $message) && mb_strlen($keyword) <= 8) {
            return true;
        }
        return false;
    }

    /**
     * 判断清理后的搜索词是否"过于模糊"，需要让 AI 给出更精准的搜索词。
     * 明确的具体词（iphone15、连衣裙）返回 false，保持原有搜索行为不被 AI 改写。
     */
    private function isVagueKeyword($keyword)
    {
        $kw = trim($keyword);
        if ($kw === '' || mb_strlen($kw) <= 2) {
            return true;
        }
        if (preg_match('/^(好物|东西|实用|物件|用品|礼物|礼品|特产|杂物|百货|小东西|玩意|物品|货|什么|啥|里|家)$/u', $kw)) {
            return true;
        }
        // "养生好物 / 办公好物" 这类含"好物"的也算模糊，应交由 AI 给出精准搜索词
        if (mb_stripos($kw, '好物') !== false) {
            return true;
        }
        return false;
    }

    /**
     * AI 辅助意图理解（大模型补全）。
     *
     * 与「本地规则 + 向量语义」互补：规则/语义擅长"品牌+型号/显式预算/明确反季"等硬信号，
     * 但面对"送奶奶的养生好物 / 办公室解压小物件 / 开学给孩子添置点东西"这类
     * 「场景 + 用途 + 人群」都模糊的诉求就力不从心——这正是 AI 的用武之地。
     *
     * 让大模型输出结构化 JSON（类目/场景/人群/特征/季节/地域/预算/搜索词/意图），
     * 然后以"规则优先、AI 补缺"的原则合并回 filters：
     *  - 显式预算、明确反季、已识别品牌：规则优先（用户说了算）；
     *  - 类目、场景、人群、特征、地域、搜索词：规则空/模糊时由 AI 补。
     *
     * 安全：模型未配置 / 返回错误 / 非 JSON 时一律返回 null，由上层回退到纯规则，绝不抛错。
     *
     * @return array|null ['filters'=>array,'search_keyword'=>string] 或 null（不可用）
     */
    private function enrichFiltersWithAi(array $filters, $message, $keyword)
    {
        if (!$this->needsAiEnrich($filters, $message, $keyword)) {
            return null;
        }
        $systemPrompt = <<<PROMPT
你是一个电商导购意图理解引擎。根据用户自然语言购买诉求，抽取结构化字段。只输出一个 JSON 对象，不要解释、不要 markdown 代码块、不要多余字符。

可选类目(category)，选最贴切的一个：
服装, 鞋包, 手机数码, 生活杂物, 快消品, 水果, 食品饮料, 特产手工, 家居日用, 户外旅行, 通用

字段定义：
- category: 上述类目之一
- scene: 用途场景，取值之一：送礼 / 日常自用 / 旅行携带 / 商务办公 / 学生宿舍 / 母婴照顾 / 运动健身 / 厨房烹饪 / 卧室居家 / 客厅娱乐 / 户外露营 / 通勤上班 / 聚会约会 / 新房装修 / 车载出行 / 办公解压 / 收纳整理
- audience: 适用人群，取值之一：女性 / 男性 / 学生 / 上班族 / 母婴 / 长辈 / 儿童 / 宠物 / 通用
- feature: 特殊需求，取值之一：防水 / 便携 / 大容量 / 智能 / 静音 / 轻薄 / 高颜值 / 续航长 / 快充 / 护眼 / 儿童适用 / 可水洗 / 折叠 / 迷你 / 养生 / 解压 / 实用
- brand: 品牌（无则空串）
- season: 期望季节 春 / 夏 / 秋 / 冬 / auto（auto=按当前季节即可）
- region: 地域偏好 north / south / 空串
- intent: 意图 gift(送礼) / self(自用) / compare(对比) / restock(囤货) / other
- budget_min, budget_max: 预算整数区间（无则0）
- search_keyword: 用于商品搜索的精简中文关键词（去掉语气词/疑问词，保留核心品类+属性+品牌，如"养生礼盒 长辈 特产"）
- reason: 一句话判定理由（可空）

示例：
输入："送奶奶点养生的好东西，预算300"
输出：{"category":"特产手工","scene":"送礼","audience":"长辈","feature":"养生","brand":"","season":"auto","region":"","intent":"gift","budget_min":0,"budget_max":300,"search_keyword":"养生礼盒 长辈 特产","reason":""}
PROMPT;

        try {
            $resp = \app\common\AiHub::chat($message, $systemPrompt, false);
        } catch (\Throwable $e) {
            return null;
        }
        if (empty($resp) || \app\common\AiHub::isErrorResult($resp)) {
            return null;
        }
        // 解析 JSON（容错：剥离可能的代码块/前后文）
        $json = $resp;
        if (($pos = strpos($json, '{')) !== false) {
            $json = substr($json, $pos);
            if (($end = strrpos($json, '}')) !== false) {
                $json = substr($json, 0, $end + 1);
            }
        }
        $ai = json_decode($json, true);
        if (!is_array($ai)) {
            return null;
        }
        // 合并：规则优先，AI 补缺
        $merged = $filters;
        $cat = isset($ai['category']) ? trim($ai['category']) : '';
        if ($cat !== '' && ($merged['category'] === '通用' || $merged['category'] === '')) {
            $merged['category'] = $cat;
        }
        foreach (array('scene', 'audience', 'feature') as $k) {
            $v = isset($ai[$k]) ? trim($ai[$k]) : '';
            if ($v !== '' && $v !== '通用' && empty($merged[$k])) {
                $merged[$k] = $v;
            }
        }
        if (empty($merged['brand']) && !empty($ai['brand'])) {
            $merged['brand'] = trim($ai['brand']);
        }
        // 季节：用户未明确指定反季时，允许 AI 微调（如过渡期/上下文暗示）
        if (empty($merged['season_explicit']) && isset($ai['season']) && in_array($ai['season'], array('春', '夏', '秋', '冬'), true)) {
            $merged['season'] = $ai['season'];
        }
        if (empty($merged['region']) && isset($ai['region']) && in_array($ai['region'], array('north', 'south'), true)) {
            $merged['region'] = $ai['region'];
        }
        // 预算：规则没推导出档位时，用 AI 的预算
        if (empty($merged['price_max']) && !empty($ai['budget_max'])) {
            $merged['price_max'] = (int)$ai['budget_max'];
            $merged['price_min'] = !empty($ai['budget_min']) ? (int)$ai['budget_min'] : 0;
        }
        $searchKw = isset($ai['search_keyword']) ? trim($ai['search_keyword']) : '';
        return array('filters' => $merged, 'search_keyword' => $searchKw);
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
            // 单独量词：用户常写"买个/买只/来款"，复合量词删完后量词本身会残留
            // （如"我想买个保温杯"→删"我想买"剩"个保温杯"），需单独清理，否则搜索词不精确。
            // 注意：这里放的是「可独立成词或明显冗余」的量词，且下方改用「数字+量词」正则清理，
            // 避免把商品名里的字素误删（如「本」会毁掉「游戏本/笔记本」，故不在此列）。
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
            // -- 动词/量词前缀（残留会污染搜索词，如"挑双皮鞋""买个保温杯""来台游戏本"）--
            // 仅用「短语」级别匹配，避免误删商品名中的字素（如 双肩包 的"双"、笔记本 的"本"）。
            '挑', '挑双', '挑个', '挑一款', '挑一双', '挑几双', '帮忙', '选',
            '来台', '来个', '来一款', '来只',
            '要个', '拿个', '带个', '配个', '搞个', '整个', '整双', '整对',
            '个',
        ];

        $cleaned = $message;

        // 按长度降序排列停用词，优先替换长词（避免"推荐"在"求推荐"之前被部分替换）
        usort($stopWords, function($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        foreach ($stopWords as $word) {
            $cleaned = str_replace($word, '', $cleaned);
        }

        // 删除「数字+量词」组合（如"一台""五个""五本"），避免量词残留；
        // 但不在词内删除单字量词（如"本"在"游戏本/笔记本"中是词素，不能删）。
        $cleaned = preg_replace('/\d+[个只支款台部双套把瓶盒件条根张本副顶盏块片]/u', '', $cleaned);

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
     *
     * 增强：
     *  - 支持多轮上下文（$history），便于 AI 导购点评时结合用户偏好
     *  - $needAdvice=true 时（求推荐/对比/带预算场景），在商品卡前追加 AI 导购点评，
     *    像真人导购一样给出"为什么推这些 / 怎么选"，卡片不再冷冰冰。
     *
     * @param string $keyword         已提取的商品关键词
     * @param string $originalMessage 用户原始消息（用于生成导购点评）
     * @param array  $history         对话历史
     * @param bool   $needAdvice      是否需要 AI 导购点评
     * @param bool   $compareAll      是否强制全网比价（@ 指令）：优先跨平台比价而非站内
     */
    private function handleProductSearch($keyword, $originalMessage, $history = array(), $needAdvice = false, $compareAll = false, $filters = array())
    {
        if (empty($keyword)) {
            return $this->handleAiChat($originalMessage, $history);
        }

        // AI 辅助意图理解：规则/本地语义判不出的类目、场景、用途、人群，交给大模型补充。
        // 仅按需触发（见 needsAiEnrich），模型未配置时 enrichFiltersWithAi 返回 null 自动跳过，零副作用。
        $aiRefinedKw = '';
        $enr = $this->enrichFiltersWithAi($filters, $originalMessage, $keyword);
        if ($enr !== null) {
            $filters = $enr['filters'];
            $aiRefinedKw = $enr['search_keyword'];
        }

        // 类目歧义：用户只说泛类（裙子/裤子/鞋子…）时，先让用户确认具体子类，
        // 避免"猜错连衣裙"。优先级高于下方的预算/场景澄清引导（猜错类目比缺预算更严重）。
        $ambig = $this->detectCategoryAmbiguity($keyword, $originalMessage);
        if ($ambig['ambiguous']) {
            return $this->renderCategoryClarify($ambig['term'], $ambig['candidates']);
        }

        // 模糊需求澄清（懂车帝式"先问清楚再推荐"）：用户只说"推荐个手机/帮我挑笔记本"这类
        // 泛品类、无具体型号/预算/场景的诉求时，先给出结构化澄清引导，引导其用筛选器或点快捷标签
        // 补全需求，而不是直接甩出一屏幕泛结果。避免"推荐了个寂寞"的体验。
        if ($this->isGenericCategory($keyword)
            && !$this->looksLikeSpecificModel($keyword)
            && !preg_match('/(\d{2,5})\s*(元|块|块钱|\$|¥)|预算|价位|送|礼物|女生|男生|学生|宝妈|老人|长辈|夏天|冬季|新手|入门/iu', $originalMessage)) {
            return $this->renderClarifyGuide($keyword);
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
        // AI 精炼搜索词：当规则清理出的关键词过于模糊（如"养生好物/东西"）时，
        // 用大模型给出的更精准词去搜（如"养生礼盒 长辈 特产"），让模糊诉求也能出对货。
        if ($aiRefinedKw !== '' && $this->isVagueKeyword($keyword)) {
            $searchKw2 = $aiRefinedKw;
        }

        // 品牌维度：若抽取到品牌且搜索词尚未包含该品牌，则拼入关键词，
        // 让联盟库（大淘客/好单库）按品牌收敛结果（与 searchGoods 的 brand 处理一致）。
        if (!empty($filters['brand']) && mb_stripos($searchKw2, $filters['brand']) === false) {
            $searchKw2 = trim($searchKw2 . ' ' . $filters['brand']);
        }
        // 排除品牌：从搜索词剥离，避免搜到自己刚说"不要"的品牌
        if (!empty($filters['exclude_brand']) && mb_stripos($searchKw2, $filters['exclude_brand']) !== false) {
            $searchKw2 = trim(preg_replace('/' . preg_quote($filters['exclude_brand'], '/') . '/u', '', $searchKw2));
        }

        // 策略：问产品「立即出产品」——全网商品优先，站内文章作为「相关攻略」补充，
        // 避免「先给文章、多轮才出商品」的割裂感（用户要的是货，不是文章）。
        // 1. 优先大淘客全网搜索（按平台优先级填充：淘宝>京东>拼多多>唯品会）
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $this->tjk = $tjk; // 复用同一实例给后续转链，避免重新初始化丢失推广位 PID 等配置
            $tjkResult = $tjk->searchAllPlatforms($searchKw2, 1, 5, null, true, $filters, 4);
            if (isset($tjkResult['debug'])) {
                error_log('[AI search] platforms=' . json_encode($tjkResult['debug']));
            }
            // 二次相关性过滤：剔除标题 SEO 堆砌关键词但并非用户所求的商品
            // （如搜"高跟鞋"却返回"真皮鞋垫…高跟鞋"），避免给用户推错品
            if (!empty($tjkResult['items'])) {
                $tjkResult['items'] = \ZhiCms\ext\Tjk::filterRelevantItems($tjkResult['items'], $keyword);
            }
            // 结果过少时自动放宽预算重试（避免"严格预算 + 小众品类"导致空结果死胡同）
            $relaxedNote = '';
            if (count($tjkResult['items'] ?? array()) < 3
                && (!empty($filters['price_min']) || !empty($filters['price_max']))) {
                $relaxed = $filters;
                $relaxed['price_min'] = 0;
                $relaxed['price_max'] = !empty($filters['price_max']) ? (int)round($filters['price_max'] * 1.6) : 0;
                try {
                    $r2 = $tjk->searchAllPlatforms($searchKw2, 1, 5, null, true, $relaxed, 4);
                    if ($r2['code'] == 1 && !empty($r2['items'])) {
                        $r2['items'] = \ZhiCms\ext\Tjk::filterRelevantItems($r2['items'], $keyword);
                        if (count($r2['items']) > count($tjkResult['items'])) {
                            $tjkResult = $r2;
                            $relaxedNote = '<div class="ai-guide ai-note">💡 严格预算下商品较少，已为您放宽预算范围，以下为相近推荐：</div>';
                        }
                    }
                } catch (\Throwable $e) { /* 放宽失败不影响原结果 */ }
            }
            if ($tjkResult['code'] == 1 && !empty($tjkResult['items'])) {
                // 用途 / 场景 / 特殊需求：对结果做软过滤（匹配项前置，不匹配也不丢结果，避免空结果）
                $this->curFilters = $filters;
                $this->savePrefs($filters);
                // —— 结果符合度自评（ROI）：逐件标注符合分 + 整批 ROI，并落知识库 ——
                $items = $tjkResult['items'];
                $roi = $this->attachRoi($items, $filters);
                $tjkResult['items'] = $this->rankItemsByFilters($items, $filters, $keyword);
                $roiBanner = $this->hasRealFilters($filters) ? $this->renderRoiBanner($roi, $searchKw2) : '';
                $this->logKnowledge(array(
                    'keyword' => $searchKw2,
                    'filters' => $filters,
                    'found'   => count($tjkResult['items']),
                    'roi'     => $roi,
                    'items'   => $this->kbItemsMeta($tjkResult['items']),
                ));
                $html = $roiBanner . $relaxedNote . $this->formatTjkResults($tjkResult['items'], $searchKw2, $explicit);
                if ($needAdvice) {
                    $html = $this->buildAdvice($originalMessage, $searchKw2, $history, 'product', $tjkResult['items'], $filters) . $html;
                }
                // 站内相关攻略作为补充（若有），放在商品卡之后，不喧宾夺主
                $articles = obj("api/ApiData")->dataSelect(
                    "yun_article",
                    array('title' => array('like', '%' . $searchKw2 . '%')),
                    "`id` DESC LIMIT 0, 3"
                );
                if (!empty($articles)) {
                    $html .= $this->formatArticleResults($articles, $searchKw2, true);
                }
                return $html;
            }
        } catch (\Exception $e) {
            // 大淘客不可用时静默 fallback
        }

        // 2. 全网无结果，再退站内文章（仍是购物相关，给出攻略/测评）
        $articles = obj("api/ApiData")->dataSelect(
            "yun_article",
            array('title' => array('like', '%' . $searchKw2 . '%')),
            "`id` DESC LIMIT 0, 5"
        );
        if (!empty($articles)) {
            $html = $this->formatArticleResults($articles, $searchKw2);
            if ($needAdvice) {
                $html = $this->buildAdvice($originalMessage, $searchKw2, $history, 'article') . $html;
            }
            return $html;
        }

        // 3. 都没有结果，交给 AI 处理（结合上下文给出智能引导，而非干巴巴兜底）
        return $this->handleAiChat($originalMessage, $history);
    }

    /**
     * 按筛选维度对搜索结果做"软过滤"排序：匹配的置前，不匹配的不丢弃（避免空结果）。
     * 用于让"用途/场景/特殊需求/人群"在结果排序上真正生效（预算已在 searchGoods 层用 pmin/pmax 硬过滤）。
     *
     * @param array $items  商品列表
     * @param array $filters extractPurchaseFilters 产出的筛选维度
     * @return array 重新排序后的列表（稳定排序，未匹配项保持相对位置）
     */
    private function rankItemsByFilters(array $items, array $filters, $keyword = '')
    {
        if (empty($filters)) {
            return $items;
        }
        // 排除项：剔除用户明确"不要"的品牌/特征（硬移除，避免推错）
        $exBrand = isset($filters['exclude_brand']) ? trim($filters['exclude_brand']) : '';
        $exFeat  = isset($filters['exclude_feature']) ? trim($filters['exclude_feature']) : '';
        if ($exBrand !== '' || $exFeat !== '') {
            $items = array_values(array_filter($items, function ($it) use ($exBrand, $exFeat) {
                $t = mb_strtolower(strip_tags(($it['title'] ?? '') . ' ' . ($it['brandName'] ?? '') . ' ' . ($it['shopName'] ?? '')));
                if ($exBrand !== '' && mb_stripos($t, $exBrand) !== false) return false;
                if ($exFeat !== '' && mb_stripos($t, $exFeat) !== false) return false;
                return true;
            }));
            if (empty($items)) return $items;
        }

        // 维度 → 标题关键词（命中即加分）。覆盖常见"用途/场景/人群/特征"表达。
        $sceneKw = array(
            '送礼'     => ['礼盒', '礼品', '送礼', '礼物', '礼包', '伴手礼'],
            '旅行携带' => ['旅行', '便携', '出差', '户外', '行李', '旅游'],
            '商务办公' => ['商务', '办公', '职场', '通勤', '会议'],
            '学生宿舍' => ['学生', '宿舍', '校园', '考研', '上学'],
            '母婴照顾' => ['婴儿', '宝宝', '母婴', '儿童', '小孩', '孕'],
            '运动健身' => ['运动', '健身', '跑步', '瑜伽', '训练', '球'],
            '厨房烹饪' => ['厨房', '烹饪', '烘焙', '做饭'],
            '卧室居家' => ['卧室', '居家', '家用', '房间'],
            '客厅娱乐' => ['客厅', '电视', '游戏', '影音', '影院'],
            '户外露营' => ['户外', '露营', '野餐', '登山', '徒步'],
            '通勤上班' => ['通勤', '上班', '代步'],
            '聚会约会' => ['约会', '聚会', '派对', '聚餐'],
            '新房装修' => ['装修', '新房', '搬家', '乔迁'],
            '车载出行' => ['车载', '车用', '汽车'],
        );
        $featureKw = array(
            '防水'   => ['防水', '防泼溅', 'ipx', '生活防水'],
            '便携'   => ['便携', '轻便', '小巧', '迷你', '口袋'],
            '大容量' => ['大容量', '大杯', '大号', '大屏', '大内存', '大空间'],
            '智能'   => ['智能', 'ai', '语音', '联网', 'app'],
            '静音'   => ['静音', '无声', '低噪', '安静'],
            '轻薄'   => ['轻薄', '超薄', '轻巧', '纤薄'],
            '高颜值' => ['高颜值', '颜值', '美观', 'ins', '简约'],
            '续航长' => ['续航', '待机', '长续航'],
            '快充'   => ['快充', '闪充'],
            '护眼'   => ['护眼', '防蓝光', '低蓝光'],
            '儿童适用' => ['儿童', '宝宝', '学生'],
            '可水洗' => ['可水洗', '能洗'],
            '折叠'   => ['折叠', '翻盖'],
        );
        $audienceKw = array(
            '女性'   => ['女', '女士', '女神', '闺蜜'],
            '男性'   => ['男', '男士', '汉子'],
            '学生'   => ['学生', '校园'],
            '上班族' => ['办公', '职场', '通勤'],
            '母婴'   => ['婴儿', '宝宝', '儿童'],
            '长辈'   => ['老人', '长辈', '父母', '中老年'],
            '儿童'   => ['儿童', '宝宝', '小孩'],
            '宠物'   => ['猫', '狗', '宠物'],
        );

        $score = array();
        foreach ($items as $i => $it) {
            $score[$i] = 0; // 初始化，避免 uksort 时未定义键告警
        }

        // 季节性软排序：默认当前季时，按「应季画像」把当季该买的款式排前、反季的排后（不删除，避免空结果）；
        // 用户明确要反季（如"冬装"）时只加权、不压低。覆盖服饰/鞋包/水果/食品/家居/户外等吃喝住行。
        $season = $filters['season'] ?? '';
        if ($season !== '') {
            // 优先用已判定的类目（规则或 AI 增强给出的），否则回退到关键词推断
            $cat = !empty($filters['category']) ? $filters['category'] : $this->detectSeasonCategory($keyword ?? '');
            $bias = $filters['region'] ?? '';
            $sk = $this->getSeasonKeywords($cat, $season, $bias);
            // 用户明确要反季（如"冬装"）时只加权、不压低，避免把用户要的冬装排后面
            $allowDown = empty($filters['season_explicit']);
            foreach ($items as $i => $it) {
                $title = mb_strtolower(strip_tags($it['title'] ?? '') . ' ' . ($it['desc'] ?? '') . ' ' . ($it['subTitle'] ?? ''));
                foreach ($sk['boost'] as $kw) {
                    if (mb_stripos($title, $kw) !== false) { $score[$i] += 2; break; }
                }
                if ($allowDown) {
                    foreach ($sk['down'] as $kw) {
                        if (mb_stripos($title, $kw) !== false) { $score[$i] -= 3; break; }
                    }
                }
            }
        }

        foreach ($items as $i => $it) {
            $title = mb_strtolower(strip_tags($it['title'] ?? '') . ' ' . ($it['desc'] ?? '') . ' ' . ($it['subTitle'] ?? ''));
            $s = 0;
            if (!empty($filters['scene']) && isset($sceneKw[$filters['scene']])) {
                foreach ($sceneKw[$filters['scene']] as $kw) {
                    if (mb_stripos($title, $kw) !== false) { $s += 2; break; }
                }
            }
            if (!empty($filters['feature']) && isset($featureKw[$filters['feature']])) {
                foreach ($featureKw[$filters['feature']] as $kw) {
                    if (mb_stripos($title, $kw) !== false) { $s += 2; break; }
                }
            }
            if (!empty($filters['audience']) && isset($audienceKw[$filters['audience']])) {
                foreach ($audienceKw[$filters['audience']] as $kw) {
                    if (mb_stripos($title, $kw) !== false) { $s += 1; break; }
                }
            }
            // 价值排序：在预算内、优惠力度大、销量高者优先（让"最值得买"排前面）
            $price  = (float)($it['actualPrice'] ?? $it['zkFinalPrice'] ?? $it['finalPrice'] ?? $it['price'] ?? 0);
            $coupon = (float)($it['couponAmount'] ?? $it['couponPrice'] ?? 0);
            $sales  = (int)($it['monthSales'] ?? $it['sales'] ?? 0);
            if ($price > 0) {
                $pmin = (int)($filters['price_min'] ?? 0);
                $pmax = (int)($filters['price_max'] ?? 0);
                if ($pmax > 0 && $price <= $pmax && $price >= $pmin) {
                    $s += 1; // 命中预算区间内，给基础分
                }
                if ($coupon > 0) {
                    $s += min(3, (int)round($coupon / max($price, 1) * 10)); // 折扣力度（券额/总价）
                }
            }
            if ($sales > 0) {
                $s += min(3, (int)log10($sales + 1)); // 销量热度（对数，避免头部垄断）
            }
            // 颜色 / 规格属性匹配：命中用户指定的颜色或规格则加分
            $ct = mb_strtolower(strip_tags($it['title'] ?? ''));
            if (!empty($filters['color']) && mb_stripos($ct, $filters['color']) !== false) {
                $s += 2;
            }
            if (!empty($filters['spec']) && mb_stripos($ct, $filters['spec']) !== false) {
                $s += 2;
            }
            $score[$i] += $s;
        }

        // 仅当评分出现差异时才重排，否则保持原序（不丢结果）
        if (max($score) !== min($score)) {
            uksort($items, function ($a, $b) use ($score) {
                return $score[$b] <=> $score[$a];
            });
            $items = array_values($items);
        }
        return $items;
    }

    /**
     * 生成 AI 导购点评（让商品卡更像"真人导购"而非冷冰冰列表）
     *
     * 结合用户原始诉求 + 搜索到的商品，给出 1-2 句选购建议 / 推荐理由。
     * AI 不可用时静默返回空（商品卡本身仍正常展示）。
     *
     * @param string $userMsg   用户原始消息
     * @param string $keyword   商品关键词
     * @param array  $history   上下文
     * @param string $type      'product' | 'article'
     * @param array  $items     商品数据（用于生成更贴合的点评）
     */
    private function buildAdvice($userMsg, $keyword, $history, $type = 'product', $items = array(), $filters = array())
    {
        // 组装简化的商品线索（避免把大段 HTML 喂给模型）
        $clues = array();
        foreach (array_slice($items, 0, 5) as $it) {
            $clues[] = ($it['title'] ?? '') . ' ' . ($it['shopName'] ?? '') . ' ¥' . ($it['actualPrice'] ?? '?')
                . ' 月销' . ($it['monthSales'] ?? '?');
        }
        $itemLine = $clues ? ("\n候选商品（标题 店铺 价格 月销）：" . implode('；', $clues)) : '';

        // 优先用结构化筛选维度（语义 SDK 抽取）生成需求描述，原正则作为兜底，
        // 保证预算/用途/人群等"随意图变化且准确"地进入导购建议。
        $demand = array();
        if (!empty($filters)) {
            if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
                $b = '';
                if (!empty($filters['price_min'])) {
                    $b .= $filters['price_min'] . '元';
                }
                if (!empty($filters['price_max'])) {
                    $b .= (empty($filters['price_min']) ? '以内' : '~') . $filters['price_max'] . '元';
                }
                $demand[] = '预算' . $b;
            }
            if (!empty($filters['scene']))    $demand[] = '用途/场景：' . $filters['scene'];
            if (!empty($filters['audience'])) $demand[] = '人群：' . $filters['audience'];
            if (!empty($filters['brand']))    $demand[] = '品牌：' . $filters['brand'];
            if (!empty($filters['feature']))  $demand[] = '特殊需求：' . $filters['feature'];
        }
        if (empty($demand)) {
            // 兜底：原正则抽取，保证未走语义抽取时仍有基础需求描述
            if (preg_match('/(\d{2,5})\s*(元|块|块钱|\$|¥)/u', $userMsg, $m)
                || preg_match('/(?:预算|价位)\s*(\d{2,5})/u', $userMsg, $m)) {
                $demand[] = '预算约' . $m[1] . '元';
            }
            if (preg_match('/(送|礼物|生日|节日|过年|新年)/u', $userMsg)) {
                $demand[] = '送礼场景';
            }
            if (preg_match('/(女生|女生用|女生送|她|闺蜜)/u', $userMsg)) {
                $demand[] = '女性用户';
            }
            if (preg_match('/(男生|男生用|他|男友|兄弟)/u', $userMsg)) {
                $demand[] = '男性用户';
            }
            if (preg_match('/(学生|上学|宿舍|考研|考研党)/u', $userMsg)) {
                $demand[] = '学生党';
            }
            if (preg_match('/(宝妈|宝宝|婴儿|儿童|小孩|孩子)/u', $userMsg)) {
                $demand[] = '母婴人群';
            }
            if (preg_match('/(老人|长辈|父母|爸妈|中老年)/u', $userMsg)) {
                $demand[] = '长辈';
            }
            if (preg_match('/(夏天|夏季|冬天|冬季|春秋|换季)/u', $userMsg)) {
                $demand[] = '季节相关';
            }
            if (preg_match('/(新手|入门|第一次|刚开始)/u', $userMsg)) {
                $demand[] = '新手入门';
            }
        }
        $demandLine = $demand ? ("\n用户附加需求：" . implode('、', $demand)) : '';

        $system = "你是本站的智能导购顾问，风格像懂车帝/小红书里的专业买手。"
            . "用户想买「{$keyword}」。请基于候选商品，给出**结构化导购建议**，"
            . "用如下格式（每段一行，不要加序号前缀，控制在 120 字内）：\n"
            . "【结论】用一句大白话告诉用户现在值不值得买、先买哪类\n"
            . "【看什么】挑 2-3 个最该关注的核心指标/参数，说明怎么看（别说废话）\n"
            . "【避坑】一句最容易被坑的点或选购误区\n"
            . "若用户提到预算/人群/场景，务必结合给出针对性建议。语言亲切、像朋友，不堆参数。";

        $prompt = "用户说：「{$userMsg}」"
            . $itemLine
            . $demandLine
            . "\n请按格式给出导购建议。";

        try {
            $advice = \app\common\AiHub::chat($prompt, $system, false, array('fallback' => true));
            if (!\app\common\AiHub::isErrorResult($advice) && trim($advice) !== '') {
                return $this->renderAdviceBlock(trim($advice), $keyword);
            }
        } catch (\Throwable $e) {
            // 点评失败不影响商品卡展示
        }
        return '';
    }

    /**
     * 把 AI 返回的结构化导购建议渲染成可展示的 HTML 区块（【结论】/【看什么】/【避坑】分段）
     */
    private function renderAdviceBlock($text, $keyword)
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        // 将 【xxx】 标记转换为带样式的分段
        $text = preg_replace_callback('/【(结论|看什么|避坑|推荐|适合谁)】/', function ($m) {
            $label = array(
                '结论' => '🎯 结论',
                '看什么' => '🔍 看什么',
                '避坑' => '⚠️ 避坑',
                '推荐' => '⭐ 推荐',
                '适合谁' => '👤 适合谁',
            );
            $cls = array(
                '结论' => 'conclusion',
                '看什么' => 'point',
                '避坑' => 'warn',
                '推荐' => 'point',
                '适合谁' => 'point',
            );
            $k = $m[1];
            return '</p><p class="ai-advice-line ai-advice-' . $cls[$k] . '"><b>' . $label[$k] . '：</b>';
        }, $text);
        return '<div class="ai-advice"><div class="ai-advice-title">💡 智能导购建议 · ' . htmlspecialchars($keyword) . '</div>'
            . '<p class="ai-advice-line">' . $text . '</p></div>';
    }

    /**
     * 格式化站内文章结果为 HTML
     * @param bool $asSupplement 是否为「相关攻略」补充块（商品卡之后展示，弱化标题）
     */
    private function formatArticleResults($items, $keyword, $asSupplement = false)
    {
        $header = $asSupplement
            ? '<div class="ai-product-header ai-guide-header">📚 相关攻略 & 评测</div>'
            : '<div class="ai-product-header">🔍 为您找到关于 "<b>' . htmlspecialchars($keyword) . '</b>" 的优惠信息：</div>';
        $html = $header;
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
    private function renderTjkItem($item, $noPrefix = false)
    {
        $title   = htmlspecialchars($item['title'] ?? $item['dtitle'] ?? '', ENT_QUOTES, 'UTF-8');
        $goodsId = $item['goodsId'] ?? '';
        $from    = isset($item['item_from']) ? strtolower($item['item_from']) : '';
        if ($from === 'taobao' || $from === 'dtk') $from = 'tb';
        if (!in_array($from, ['tb', 'jd', 'pdd', 'vip'])) $from = 'tb';

        // 统一走全站标准转链伪静态入口 buy-<platform>.html?id=<goodsId>
        // （rule.php 中 'buy-<platform>.html' => 'index/redirect/jump/platform=<platform>'，
        //  id 通过 query string 传入），由 RedirectController::jump 实时调好单库/大淘客转链，
        //  避免直接使用搜索接口返回的 couponLink（多为无佣落地页），保证站长拿到返利。
        // 只需 id 即可，jump() 会按平台处理转链（淘宝入库商品自动取 goodsSign）。
        $link = ROOT_URL . 'buy-' . rawurlencode($from) . '.html?id=' . rawurlencode($goodsId);
        $pic     = isset($item['mainPic']) ? htmlspecialchars($item['mainPic']) : '';
        $price   = isset($item['actualPrice']) ? floatval($item['actualPrice']) : 0;
        $coupon  = isset($item['couponPrice']) ? floatval($item['couponPrice']) : 0;
        $sales   = isset($item['monthSales']) ? intval($item['monthSales']) : 0;
        $originalPrice = isset($item['originalPrice']) ? floatval($item['originalPrice']) : (isset($item['origPrice']) ? floatval($item['origPrice']) : 0);

        $fromLabel = [
            'tb' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会',
        ];
        $fromText = (!$noPrefix && isset($fromLabel[$from])) ? '【' . $fromLabel[$from] . '】' : '';

        // 轻量导购卖点标签：基于真实字段生成，不额外调用 AI，保证性能
        $tags = array();
        if ($sales >= 10000)     $tags[] = '🔥 爆款热销';
        elseif ($sales >= 2000)  $tags[] = '📈 高销量';
        if ($coupon > 0)         $tags[] = '🧧 有券可领';
        if ($price > 0 && $price < 50)  $tags[] = '💰 平价好物';
        if ($price >= 3000)      $tags[] = '🏆 品质之选';
        $shop = $item['shopName'] ?? '';
        if (mb_strpos($shop, '自营') !== false || mb_strpos($shop, '官方') !== false) {
            $tags[] = '🛡️ 官方/自营';
        }
        // 符合度自评徽标：单品命中用户核心诉求（预算/颜色/规格/品牌）且分高时标记"精准命中"
        if (($item['_matchScore'] ?? 0) >= 80) {
            array_unshift($tags, '✅ 精准命中');
        }
        $tagHtml = '';
        if ($tags) {
            $tagHtml = '<div class="ai-product-tags">' . implode('', array_map(function ($t) {
                return '<span class="ai-tag">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>';
            }, array_slice($tags, 0, 3))) . '</div>';
        }

        // 逐条推荐理由：基于用户的筛选诉求 + 该商品的真实字段，给出一句"为什么推荐它"
        $reasonHtml = '';
        $f = $this->curFilters ?? array();
        if (!empty($f) || $coupon > 0 || $sales > 0) {
            $bits = array();
            $pmax = (int)($f['price_max'] ?? 0);
            $pmin = (int)($f['price_min'] ?? 0);
            if ($pmax > 0 && $price > 0 && $price <= $pmax && $price >= $pmin) {
                $bits[] = '在预算内';
            }
            if ($coupon >= 20)      $bits[] = '券后省' . $coupon . '元';
            elseif ($coupon >= 5)   $bits[] = '可用券';
            if ($sales >= 10000)    $bits[] = '万级销量';
            elseif ($sales >= 2000) $bits[] = '高销量';
            if (!empty($f['feature'])) {
                $fm = array(
                    '轻薄' => ['轻薄', '超薄', '轻巧', '纤薄'], '防水' => ['防水', '防泼溅'],
                    '便携' => ['便携', '轻便', '小巧', '迷你'], '大容量' => ['大容量', '大杯', '大号'],
                    '静音' => ['静音', '无声'], '快充' => ['快充', '闪充'], '护眼' => ['护眼', '防蓝光'],
                    '高颜值' => ['高颜值', '颜值', '美观'], '续航长' => ['续航', '长续航'],
                );
                $kw = $fm[$f['feature']] ?? array($f['feature']);
                foreach ($kw as $k) { if (mb_stripos($title, $k) !== false) { $bits[] = '符合' . $f['feature']; break; } }
            }
            if (!empty($f['color']) && mb_stripos($title, $f['color']) !== false) {
                $bits[] = $f['color'] . '色款';
            }
            if (!empty($bits)) {
                $reasonHtml = '<div class="ai-product-reason">💡 ' . implode(' · ', array_map(function ($b) {
                    return htmlspecialchars($b, ENT_QUOTES, 'UTF-8');
                }, array_slice($bits, 0, 3))) . '</div>';
            }
        }

        // data-* 属性供移动端导购接口（api/ai/guide）解析出真实商品 ID / 平台，
        // 以便 App 直接转链唤起电商 App（桌面 web 端会忽略这些属性，无副作用）。
        $goodsSign = isset($item['goodsSign']) ? htmlspecialchars($item['goodsSign'], ENT_QUOTES, 'UTF-8') : '';
        $html = '<a href="' . $link . '" target="_blank" rel="nofollow" class="ai-product-item"'
            . ' data-goods-id="' . htmlspecialchars($goodsId, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-goods-sign="' . $goodsSign . '"'
            . ' data-from="' . htmlspecialchars($from, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-shop="' . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . '"'
            . ($originalPrice > 0 ? ' data-original-price="' . $originalPrice . '"' : '') . '>';
        if ($pic) {
            $html .= '<img src="' . $pic . '" class="ai-product-pic" alt="' . $title . '">';
        }
        $html .= '<div class="ai-product-info">';
        $html .= '<div class="ai-product-title">' . $fromText . $title . '</div>';
        $html .= '<div class="ai-product-price">';
        if ($price > 0) $html .= '💰 券后 <b>¥' . $price . '</b>';
        if ($coupon > 0) $html .= ' <span class="ai-coupon">券' . $coupon . '元</span>';
        if ($sales > 0) $html .= ' <span class="ai-sales">已售' . $this->formatSales($sales) . '</span>';
        $html .= '</div>';
        $html .= $tagHtml . $reasonHtml;
        $html .= '</div>';
        $html .= '<span class="ai-product-badge">领券购买 →</span>';
        $html .= '</a>';
        return $html;
    }

    /**
     * 销量格式化（12345 → 1.2万），用于商品卡展示
     */
    private function formatSales($sales)
    {
        $sales = intval($sales);
        if ($sales >= 10000) {
            return round($sales / 10000, 1) . '万';
        }
        return (string) $sales;
    }

    /**
     * 渲染时预生成商品推广短链（用于 AI 搜索结果卡片）。
     * 优先调 getPrivilegeLink 拿到真实带佣金短链；失败返回空（调用方回退到 jump 中转）。
     * 包裹超时保护，避免转链接口慢拖垮 AI 响应。
     */
    private function resolveItemLink($from, $goodsId, $goodsSign = '', $fallbackUrl = '')
    {
        if (empty($goodsId)) {
            // 无 goodsId 时直接尝试使用商品原始链接（兜底）
            return (!empty($fallbackUrl) && preg_match('#^https?://#i', $fallbackUrl)) ? $fallbackUrl : '';
        }
        try {
            // 优先复用搜索时已初始化的 Tjk 实例（配置完整，含推广位 PID），
            // 否则重新初始化（Web 环境可能因配置缓存丢失 vip_pid，故回退读 ConfigStore 注入）
            if (!empty($this->tjk)) {
                $tjk = $this->tjk;
            } else {
                $tjk = new \ZhiCms\ext\Tjk();
                if ($from === 'vip' && class_exists('\\app\\common\\ConfigStore')) {
                    $vipPid = \app\common\ConfigStore::load('api', 'hdk_vip_pid');
                    if (!empty($vipPid)) {
                        $hdk = $tjk->getHdk();
                        if ($hdk) {
                            $ref = new \ReflectionProperty($hdk, 'vipPid');
                            $ref->setAccessible(true);
                            $ref->setValue($hdk, $vipPid);
                        }
                    }
                }
            }
            $ret = $tjk->getPrivilegeLink($goodsId, '', $from, $goodsSign);
            if (isset($ret['code']) && $ret['code'] == 1) {
                $data = $ret['data'] ?? array();
                // 优先「商品详情页直链」(itemUrl/taokeLink)：普通用户点击直接看到真实淘宝商品页，
                // 体验正确（不再是联盟优惠券中间页）；大淘客返回的 itemUrl 已带淘客 pid，站长仍有佣金。
                // 其次才用优惠券二合一/短链（有券时仍可领券）。
                $url = $data['itemUrl'] ?? $data['taokeLink'] ?? $data['shortUrl']
                     ?? $data['couponClickUrl'] ?? $data['url'] ?? $data['couponLink']
                     ?? $data['couponurl'] ?? $data['shortLink'] ?? $data['clickUrl'] ?? '';
                if (!empty($url) && preg_match('#^https?://#i', $url)) {
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            // 转链失败不影响卡片展示，回退商品原始链接 / jump 入口
        }
        // 转链失败（如 vip 推广位 PID 未配置）时，回退到商品原始链接，避免死链
        if (!empty($fallbackUrl) && preg_match('#^https?://#i', $fallbackUrl)) {
            return $fallbackUrl;
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
            '枕头', '床单', '文具', '玩具', '水杯', '保温杯', '杯子', '杯', '锅', '表', '键盘',
            '鼠标', '充电宝', '充电器', '数据线', '雨伞', '毛巾', '洗发水', '牙膏', '零食',
        ];
        $k = mb_strtolower(trim($kw));
        foreach ($generic as $g) {
            $gl = mb_strtolower($g);
            // 精确匹配，或「关键词包含该泛品类词」匹配（长度>=2 才允许包含，避免单字误伤如「杯」）
            if ($k === $gl) return true;
            if (mb_strlen($g) >= 2 && mb_strpos($k, $gl) !== false) return true;
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
        // 注意：不再用「长度>=4字就当具体型号」这种过宽规则，否则"今天天气"这类闲聊词
        // 会被误判为型号，导致真实性校验放行、把闲聊当成购物意图。
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
     * 模糊需求澄清引导（懂车帝式"先问清楚再推荐"）
     * 用户只说泛品类（如"手机"）且无预算/场景时，给出结构化快捷选项补全需求。
     * 每个按钮的 data-msg 带约束（如"手机 预算2000"），点击即重新触发精准搜索。
     */
    private function renderClarifyGuide($keyword)
    {
        $kw = htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
        // 按品类给不同的预算档位 / 用途标签
        $budgets = ['1000以内', '1000-3000', '3000-5000', '5000以上'];
        $scenes = $this->getClarifyScenes($keyword);

        $html  = '<div class="ai-clarify">';
        $html .= '<div class="ai-clarify-tip">🛒 想给您更精准的推荐，先告诉我几点偏好吧~</div>';

        $html .= '<div class="ai-clarify-row"><span class="ai-clarify-label">💰 预算：</span>';
        foreach ($budgets as $b) {
            $msg = htmlspecialchars($keyword . ' 预算' . $b, ENT_QUOTES, 'UTF-8');
            $html .= '<button class="ai-clarify-btn" data-msg="' . $msg . '">' . htmlspecialchars($b) . '</button>';
        }
        $html .= '</div>';

        if ($scenes) {
            $html .= '<div class="ai-clarify-row"><span class="ai-clarify-label">🎯 用途：</span>';
            foreach ($scenes as $s) {
                $msg = htmlspecialchars($keyword . ' ' . $s, ENT_QUOTES, 'UTF-8');
                $html .= '<button class="ai-clarify-btn" data-msg="' . $msg . '">' . htmlspecialchars($s) . '</button>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="ai-clarify-row"><span class="ai-clarify-label">🔤 或直接说：</span>';
        $html .= '<button class="ai-clarify-btn" data-msg="' . htmlspecialchars('帮我推荐' . $kw, ENT_QUOTES, 'UTF-8') . '">随便推荐几款' . $kw . '</button>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    /**
     * 按品类返回相关的"用途/场景"快捷标签，让澄清引导更对口
     */
    private function getClarifyScenes($keyword)
    {
        $k = mb_strtolower($keyword);
        if (mb_strpos($k, '手机') !== false || mb_strpos($k, '电脑') !== false || mb_strpos($k, '笔记本') !== false) {
            return ['打游戏', '拍照', '续航长', '轻薄办公', '学生用'];
        }
        if (mb_strpos($k, '耳机') !== false || mb_strpos($k, '音箱') !== false) {
            return ['降噪', '运动', '通勤', '听歌'];
        }
        if (mb_strpos($k, '衣服') !== false || mb_strpos($k, '裙') !== false || mb_strpos($k, '裤') !== false || mb_strpos($k, '鞋') !== false) {
            return ['日常通勤', '运动', '约会', '显瘦'];
        }
        if (mb_strpos($k, '洗衣机') !== false || mb_strpos($k, '空调') !== false || mb_strpos($k, '冰箱') !== false || mb_strpos($k, '电视') !== false) {
            return ['节能', '小户型', '智能', '性价比'];
        }
        if (mb_strpos($k, '护肤品') !== false || mb_strpos($k, '面膜') !== false || mb_strpos($k, '化妆品') !== false) {
            return ['补水', '抗老', '油皮', '敏感肌'];
        }
        return ['自用', '送人', '性价比', '高品质'];
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
     * 渲染"类目歧义"澄清引导：用户只说泛类（裙子/裤子…）时，列出候选子类让用户确认，
     * 像其他 AI 一样"先问清楚再推荐"。点击选项即把具体子类作为新消息重新触发搜索。
     *
     * @param string $term       泛类词（如"裙子"）
     * @param array  $candidates 候选具体子类（已按季节过滤/排序）
     */
    private function renderCategoryClarify($term, $candidates)
    {
        $termHtml = htmlspecialchars($term, ENT_QUOTES, 'UTF-8');
        $html  = '<div class="ai-clarify">';
        $html .= '<div class="ai-clarify-tip">🤔 你说的「<b>' . $termHtml . '</b>」具体是哪一种呢？选一下，我好更准地帮你找~</div>';
        $html .= '<div class="ai-clarify-row"><span class="ai-clarify-label">👗 具体款式：</span>';
        if (empty($candidates)) {
            // 极端情况无候选时，给一个兜底，避免卡死
            $html .= '<button class="ai-clarify-btn" data-msg="' . $termHtml . '">就看看' . $termHtml . '</button>';
        } else {
            foreach ($candidates as $c) {
                $msg = htmlspecialchars($c, ENT_QUOTES, 'UTF-8');
                $html .= '<button class="ai-clarify-btn" data-msg="' . $msg . '">' . $msg . '</button>';
            }
        }
        $html .= '</div>';
        // 兜底：用户就想随便看看泛类结果
        $html .= '<div class="ai-clarify-row"><span class="ai-clarify-label">🔤 或直接：</span>';
        $html .= '<button class="ai-clarify-btn" data-msg="' . $termHtml . '">就看看' . $termHtml . '相关</button>';
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
        // 固定格式：按平台分组，每平台最多 4 个；空平台（无数据/权限问题）直接不显示
        $platOrder = array('tb' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会');
        $grouped = array();
        foreach ($items as $it) {
            $p = $it['item_from'] ?? 'tb';
            if (!isset($platOrder[$p])) $p = 'tb';
            $grouped[$p][] = $it;
        }
        foreach ($platOrder as $p => $label) {
            if (empty($grouped[$p])) continue;
            $html .= '<div class="ai-platform-group">';
            $html .= '<div class="ai-platform-title">' . $label . ' · ' . count($grouped[$p]) . ' 件</div>';
            foreach ($grouped[$p] as $item) {
                $html .= $this->renderTjkItem($item, true);   // 分组后标题不再重复平台前缀
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($needGuide) {
            $html .= '<div class="ai-product-footer">👆 点击上方按钮可精准切换「产品本身」或「配件周边」。也可直接告诉我更具体的需求~</div>';
        } else {
            $html .= '<div class="ai-product-footer">💡 以上为淘宝、京东、拼多多、唯品会多平台比价结果，点击卡片即可领券购买。没找到满意的？告诉我更多需求，我帮您再找~</div>';
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

        $this->curFilters = array(
            'brand'     => $brand,
            'price_min' => $range ? $range[0] : 0,
            'price_max' => $range ? ($range[1] ?? 0) : 0,
            'feature'   => is_array($feature) ? implode('', $feature) : $feature,
            'scene'     => $scene,
        );
        $roi = $this->attachRoi($items, $this->curFilters);
        $roiBanner = $this->hasRealFilters($this->curFilters) ? $this->renderRoiBanner($roi, $keyword) : '';
        $this->logKnowledge(array(
            'keyword' => $keyword, 'filters' => $this->curFilters, 'found' => count($items),
            'roi' => $roi, 'items' => $this->kbItemsMeta($items), 'src' => 'pick',
        ));
        $html = $roiBanner . $this->formatPickResults($items, $keyword, $category, $price, $brand, $scene, $feature);
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
     * 改进：优先精确包含核心词；否则按「二元/全词片段命中率」判定，避免单字符误杀。
     */
    private function filterByKeywordRelevance($items, $keyword)
    {
        $kw = trim($keyword);
        if (mb_strlen($kw) < 2 || empty($items)) {
            return $items;
        }
        // 去掉泛词，得到用于判断的核心词
        $stopKw = ['商品', '东西', '产品', '推荐', '一下', '帮我', '想买', '我想', '有没有', '可以', '一个'];
        $core = $kw;
        foreach ($stopKw as $s) {
            $core = str_replace($s, '', $core);
        }
        if (mb_strlen($core) < 2) {
            $core = $kw;
        }

        // 把核心词切成 2 字片段（"保温杯" -> [保温,温杯]），用于更稳的包含判定
        $segs = [];
        $clen = mb_strlen($core);
        if ($clen >= 2) {
            for ($i = 0; $i < $clen - 1; $i++) {
                $segs[] = mb_substr($core, $i, 2);
            }
        } else {
            $segs[] = $core;
        }

        $kept = [];
        foreach ($items as $it) {
            $title = $it['title'] ?? $it['dtitle'] ?? '';
            $title = strip_tags((string)$title);
            if ($title === '') {
                continue;
            }
            // 1) 精确包含核心词或原关键词，直接保留
            if (mb_strpos($title, $core) !== false || mb_strpos($title, $kw) !== false) {
                $kept[] = $it;
                continue;
            }
            // 2) 片段命中率：至少命中一半以上的 2 字片段才算相关
            if (count($segs) > 0) {
                $hit = 0;
                foreach ($segs as $s) {
                    if (mb_strpos($title, $s) !== false) {
                        $hit++;
                    }
                }
                if ($hit / count($segs) >= 0.5) {
                    $kept[] = $it;
                }
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

    // ==================== 双品对比引擎 ====================

    /**
     * 识别"对比意图"：A 和 B 哪个好 / A vs B / 对比 A 与 B
     * 仅在出现明确对比连接词（vs/对比/比一比/比较）或"和/与/跟/还是"+结论词（哪个好/怎么选/区别）时命中，
     * 避免把"手机和耳机"(并列购买) 误判为对比。
     * @return array|false ['a'=>..,'b'=>..] 或 false
     */
    private function detectCompare($message)
    {
        $m = trim($message);
        // 显式对比连接词
        if (preg_match('/^(.+?)\s*(?:vs|VS|对比一下|对比|比一比|比较一下|比较)\s*(.+)$/u', $m, $mm)) {
            $a = trim($mm[1]);
            $b = trim($mm[2]);
            if (mb_strlen($a) >= 2 && mb_strlen($b) >= 2) {
                return $this->normalizeComparePair($a, $b);
            }
        }
        // "A和B哪个好/怎么选/区别/更适合谁" 等带结论词的对比
        if (preg_match('/^(.+?)(?:和|与|跟|还是)\s*(.+?)(?:哪个好|怎么选|区别|哪个更|更好|哪个划算|性价比高|对比|比较|值得买|适合谁|应该怎么选)\s*$/u', $m, $mm)) {
            $a = trim($mm[1]);
            $b = trim($mm[2]);
            if (mb_strlen($a) >= 2 && mb_strlen($b) >= 2) {
                return $this->normalizeComparePair($a, $b);
            }
        }
        return false;
    }

    /**
     * 清洗对比词对：去掉尾部残留的疑问/对比词，保证拿到干净的 A、B 商品词
     */
    private function normalizeComparePair($a, $b)
    {
        $strip = '/(哪个好|怎么选|区别|哪个更|更好|哪个划算|性价比高|对比|比较|值得买|适合谁|应该怎么选|呢|啊|吧|呀|？|\?|。|\.)\s*$/u';
        $a = trim(preg_replace($strip, '', $a));
        $b = trim(preg_replace($strip, '', $b));
        if ($a === '' || $b === '') {
            return false;
        }
        return array('a' => $a, 'b' => $b);
    }

    /**
     * 双品对比主流程：各取头部商品 → 并排卡片 → AI 优劣点评
     */
    private function handleCompare($a, $b, $history)
    {
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $this->tjk = $tjk;
            $ra = $tjk->searchAllPlatforms($a, 1, 3, null, true, array(), 2);
            $rb = $tjk->searchAllPlatforms($b, 1, 3, null, true, array(), 2);
        } catch (\Throwable $e) {
            return $this->handleAiChat('对比 ' . $a . ' 和 ' . $b, $history);
        }
        $ia = ($ra['code'] == 1 && !empty($ra['items'])) ? array_slice($ra['items'], 0, 1) : array();
        $ib = ($rb['code'] == 1 && !empty($rb['items'])) ? array_slice($rb['items'], 0, 1) : array();
        if (empty($ia) && empty($ib)) {
            return $this->handleAiChat('对比 ' . $a . ' 和 ' . $b, $history);
        }

        $html = '<div class="ai-compare">';
        $html .= '<div class="ai-compare-head">⚖️ 「' . htmlspecialchars($a) . '」 <span>VS</span> 「' . htmlspecialchars($b) . '」 横向对比</div>';
        $html .= '<div class="ai-compare-cols">';
        $html .= '<div class="ai-compare-col">' . (!empty($ia) ? $this->renderTjkItem($ia[0]) : '<div class="ai-compare-empty">未找到「' . htmlspecialchars($a) . '」相关商品</div>') . '</div>';
        $html .= '<div class="ai-compare-col">' . (!empty($ib) ? $this->renderTjkItem($ib[0]) : '<div class="ai-compare-empty">未找到「' . htmlspecialchars($b) . '」相关商品</div>') . '</div>';
        $html .= '</div>';
        $advice = $this->buildCompareAdvice($a, $ia, $b, $ib);
        if ($advice !== '') {
            $html .= $advice;
        }
        $html .= '<div class="ai-product-footer">💡 还想更细对比？告诉我你最在意的点（如"续航""价格""品牌""售后"），我帮你深挖~</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 生成双品对比的 AI 优劣点评（一句话结论 + 各自适合谁）
     */
    private function buildCompareAdvice($a, $ia, $b, $ib)
    {
        $fmt = function ($it) {
            return ($it['title'] ?? '') . ' 券后¥' . ($it['actualPrice'] ?? '?')
                . ' 月销' . ($it['monthSales'] ?? '?') . ' 券' . ($it['couponPrice'] ?? 0) . '元';
        };
        $lines = array();
        if ($ia) $lines[] = 'A(' . $a . '): ' . $fmt($ia[0]);
        if ($ib) $lines[] = 'B(' . $b . '): ' . $fmt($ib[0]);
        $clues = implode('；', $lines);

        $system = '你是购物对比助手。用户想对比「' . $a . '」与「' . $b . '」。'
            . '请基于候选商品给出：一句话结论（谁更值得买）+ 各自适合哪类人。控制在 90 字内，亲切口语化。';
        $prompt = '候选商品：' . $clues . "\n请直接给出对比建议。";
        try {
            $r = \app\common\AiHub::chat($prompt, $system, false, array('fallback' => true));
            if (!\app\common\AiHub::isErrorResult($r) && trim($r) !== '') {
                return $this->renderAdviceBlock(trim($r), $a . ' vs ' . $b);
            }
        } catch (\Throwable $e) {
            // 点评失败不影响对比卡片
        }
        return '';
    }

    // ==================== 用户偏好画像（登录/匿名持久化） ====================

    /**
     * 偏好存储文件：按用户身份（Cookie 持久化的 ai_uid）隔离，文件型、无需数据库。
     */
    private function prefsFile()
    {
        $dir = \ROOT_PATH . 'data/ai_prefs/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . md5($this->userId) . '.json';
    }

    /**
     * 读取用户长期偏好（人群、颜色、排除品牌等软提示）
     */
    private function loadPrefs()
    {
        $file = $this->prefsFile();
        if (!file_exists($file)) {
            return array();
        }
        $d = json_decode(file_get_contents($file), true);
        return is_array($d) ? $d : array();
    }

    /**
     * 持久化稳定偏好：仅存 audience / color / exclude_brand（场景等易误套，不持久化）。
     * 每次成功出商品卡时调用，让"我常买学生党/喜欢黑色/不要某品牌"跨会话生效。
     */
    private function savePrefs(array $f)
    {
        if (empty($f)) {
            return;
        }
        $p = $this->loadPrefs();
        foreach (array('audience', 'color', 'exclude_brand') as $k) {
            if (!empty($f[$k])) {
                $p[$k] = $f[$k];
            }
        }
        @file_put_contents($this->prefsFile(), json_encode($p, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    // ==================== 反馈闭环（前端埋点回传） ====================

    /**
     * 接收前端对推荐结果的反馈（点击/接受/下单），落日志供后续排序与词库优化。
     * 前端可在商品卡点击/购买时调用：api/ai/feedback (POST json {action,keyword,goodsId,from})
     */
    public function feedback()
    {
        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_REQUEST;
        }
        $action   = trim($input['action']   ?? '');
        $keyword  = trim($input['keyword']  ?? '');
        $goodsId  = trim($input['goodsId']  ?? '');
        $from     = trim($input['from']     ?? '');
        if ($action === '') {
            echo json_encode(['status' => 'n', 'info' => 'missing action']);
            return;
        }
        $dir = \ROOT_PATH . 'data/ai_feedback/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $line = json_encode(array(
            't'        => date('Y-m-d H:i:s'),
            'uid'      => $this->userId,
            'action'   => $action,
            'keyword'  => $keyword,
            'goodsId'  => $goodsId,
            'from'     => $from,
        ), JSON_UNESCAPED_UNICODE);
        @file_put_contents($dir . date('Y-m-d') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
        echo json_encode(['status' => 'y']);
    }

    // ==================== 结果符合度自评（ROI / 我们的自有标准） ====================
    //
    // 这是「AI 智能电商导购聚合搜索」独有的质量度量：每次出结果，都按【用户真正说出的需求】
    // 给每件商品打 0-100 的"符合度分"，再汇总成整批 ROI。它不是平台通用的销量/价格排序，
    // 而是"小淘替你筛得准不准"的可解释评分——别人抄走代码也抄不走我们持续累积的语义/行为数据。

    /**
     * 判断本次是否真的带"明确诉求"（预算/颜色/规格/品牌/人群/场景/特性/排除）。
     * 无明确诉求时不展示 ROI（避免对纯热度排序给出无意义的"符合度"）。
     */
    private function hasRealFilters($filters)
    {
        foreach (array('price_min', 'price_max', 'color', 'spec', 'brand', 'audience', 'scene', 'feature', 'exclude_brand', 'exclude_feature') as $k) {
            if (!empty($filters[$k])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 单品符合度评分（0-100）：基于用户真实筛选诉求 + 该商品真实字段。
     * 权重即我们自有标准：预算命中(+20) > 颜色对味(+10) ≥ 规格/品牌/特性(+10) > 排除命中(-40) > 价值(+5)。
     * 返回 ['score'=>int,'reasons'=>[],'match'=>bool(>=70)]
     */
    private function scoreMatch($item, $filters)
    {
        $title = mb_strtolower(strip_tags(($item['title'] ?? '') . ' ' . ($item['dtitle'] ?? '')));
        $brand = mb_strtolower(strip_tags(($item['brandName'] ?? '') . ' ' . ($item['shopName'] ?? '')));
        $price  = (float)($item['actualPrice'] ?? $item['zkFinalPrice'] ?? $item['finalPrice'] ?? $item['price'] ?? 0);
        $coupon = (float)($item['couponPrice'] ?? $item['couponAmount'] ?? 0);
        $sales  = (int)($item['monthSales'] ?? $item['sales'] ?? 0);

        $score = 60; // 基线：已通过相关性过滤，默认"相关"
        $reasons = array();

        // 1) 预算：命中区间大幅加分；略超/超预算扣分
        $pmin = (int)($filters['price_min'] ?? 0);
        $pmax = (int)($filters['price_max'] ?? 0);
        if ($pmax > 0 || $pmin > 0) {
            if ($price > 0 && $price <= ($pmax ?: PHP_INT_MAX) && $price >= $pmin) {
                $score += 20; $reasons[] = '预算命中';
            } elseif ($price > 0 && $pmax > 0 && $price <= $pmax * 1.1) {
                $score += 8;  $reasons[] = '略超预算';
            } elseif ($price > 0 && $pmax > 0 && $price > $pmax * 1.1) {
                $score -= 12; $reasons[] = '超预算';
            } elseif ($price > 0 && $pmin > 0 && $price < $pmin) {
                $score -= 6;  $reasons[] = '低于预期价位';
            }
        }
        // 2) 颜色对味
        if (!empty($filters['color'])) {
            if (mb_stripos($title, $filters['color']) !== false) { $score += 10; $reasons[] = $filters['color'] . '色对味'; }
            else { $score -= 5; }
        }
        // 3) 规格（容量/尺寸/型号等）匹配
        if (!empty($filters['spec']) && mb_stripos($title, $filters['spec']) !== false) {
            $score += 10; $reasons[] = '规格匹配';
        }
        // 4) 指定品牌
        if (!empty($filters['brand']) && $brand !== '' && mb_stripos($brand, mb_strtolower($filters['brand'])) !== false) {
            $score += 10; $reasons[] = '指定品牌';
        }
        // 5) 排除项命中（硬扣，理论上已被 rankItemsByFilters 剔除，这里兜底）
        if (!empty($filters['exclude_brand']) &&
            (mb_stripos($title, $filters['exclude_brand']) !== false || mb_stripos($brand, $filters['exclude_brand']) !== false)) {
            $score -= 40; $reasons[] = '命中排除品牌';
        }
        if (!empty($filters['exclude_feature']) && mb_stripos($title, $filters['exclude_feature']) !== false) {
            $score -= 20;
        }
        // 6) 特性（轻薄/防水/便携/快充/护眼…）
        if (!empty($filters['feature'])) {
            $fm = array(
                '轻薄' => ['轻薄', '超薄', '轻巧', '纤薄'], '防水' => ['防水', '防泼溅'],
                '便携' => ['便携', '轻便', '小巧', '迷你'], '大容量' => ['大容量', '大杯', '大号'],
                '静音' => ['静音', '无声'], '快充' => ['快充', '闪充'], '护眼' => ['护眼', '防蓝光'],
                '高颜值' => ['高颜值', '颜值', '美观'], '续航长' => ['续航', '长续航'], '智能' => ['智能', 'ai', '语音'],
            );
            $kw = $fm[$filters['feature']] ?? array($filters['feature']);
            foreach ($kw as $k) {
                if (mb_stripos($title, $k) !== false) { $score += 10; $reasons[] = '符合' . $filters['feature']; break; }
            }
        }
        // 7) 价值加权（券后省得多 / 销量高 → 更值得买）
        if ($coupon >= 20) $score += 5;
        if ($sales  >= 10000) $score += 5;

        $score = max(0, min(100, $score));
        return array('score' => $score, 'reasons' => $reasons, 'match' => $score >= 70);
    }

    /**
     * 整批 ROI 汇总：取前 8 件的平均分 + 精准命中率 + 评级。
     */
    private function scoreQueryRoi($items, $filters)
    {
        if (empty($items)) {
            return array('avg' => 0, 'rate' => 0, 'matchCount' => 0, 'total' => 0, 'label' => '无结果');
        }
        $n = 0; $sum = 0; $matchCount = 0;
        foreach (array_slice($items, 0, 8) as $it) {
            $s = $it['_matchScore'] ?? 60;
            $sum += $s; $n++;
            if ($s >= 70) $matchCount++;
        }
        $avg  = $n ? round($sum / $n) : 0;
        $rate = $n ? $matchCount / $n : 0;
        $label = $avg >= 85 ? '优秀' : ($avg >= 70 ? '良好' : ($avg >= 55 ? '一般' : '偏差'));
        return array('avg' => $avg, 'rate' => $rate, 'matchCount' => $matchCount, 'total' => count($items), 'label' => $label);
    }

    /**
     * 给商品列表逐件标注 _matchScore / _matchReasons，并返回整批 ROI。
     */
    private function attachRoi(array &$items, $filters)
    {
        foreach ($items as &$it) {
            $ms = $this->scoreMatch($it, $filters);
            $it['_matchScore']    = $ms['score'];
            $it['_matchReasons']  = $ms['reasons'];
        }
        unset($it);
        return $this->scoreQueryRoi($items, $filters);
    }

    /**
     * 渲染"小淘自评符合度"横幅（仅在有明确诉求时展示）。
     */
    private function renderRoiBanner($roi, $keyword)
    {
        if (empty($roi) || $roi['total'] == 0) {
            return '';
        }
        $avg = intval($roi['avg']);
        $icon = $avg >= 85 ? '🏆' : ($avg >= 70 ? '✅' : '⚠️');
        $lt   = array('优秀' => '超准', '良好' => '很准', '一般' => '基本对味', '偏差' => '偏了');
        $verdict = $lt[$roi['label']] ?? '';
        return '<div class="ai-roi">' . $icon . ' 小淘自评：本批「<b>' . htmlspecialchars($keyword) . '</b>」结果'
            . '<b>符合度 ' . $avg . '%</b>（' . $roi['total'] . ' 件中 ' . $roi['matchCount'] . ' 件精准命中你的预算/颜色/需求）· '
            . $verdict . '，已按"最值 + 最对味"排序</div>';
    }

    // ==================== 知识库 / 程序印记（别人抄不走的累积数据） ====================

    /**
     * 把每次高质量导购请求落盘到 data/ai_kb/（按天分片 jsonl）。
     * 记录：关键词、解析出的真实诉求、命中平台、结果 ROI、头部商品画像。
     * 这是「值得淘」独有的语义/行为资产：算法可抄，但日积月累的"用户到底要什么"抄不走。
     */
    private function logKnowledge(array $entry)
    {
        $dir = \ROOT_PATH . 'data/ai_kb/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $entry['t'] = date('Y-m-d H:i:s');
        $entry['uid'] = $this->userId;
        @file_put_contents($dir . date('Y-m-d') . '.jsonl', json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 抽取头部商品画像（标题/价格/平台/符合度），用于知识库，避免入库大段 HTML。
     */
    private function kbItemsMeta($items)
    {
        $meta = array();
        foreach (array_slice($items, 0, 6) as $it) {
            $meta[] = array(
                't' => mb_substr(strip_tags($it['title'] ?? ''), 0, 40, 'utf-8'),
                'p' => (float)($it['actualPrice'] ?? 0),
                'f' => $it['item_from'] ?? '',
                's' => (int)($it['_matchScore'] ?? 0),
            );
        }
        return $meta;
    }
}
