<?php
namespace app\index\controller;

use ZhiCms\base\Controller;
use app\common\AiService;

/**
 * 前台 AI 购物助手「小淘」控制器（游客可用，无需后台登录）
 *
 * 路由：index.php?r=index/aiAssistant/index
 * 前端（app/index/view/index/ai_widget.html）调用：
 *   POST index/aiAssistant/chat            -> {"reply": "...", "unconfigured": false}
 *   GET  index/aiAssistant/getHistory      -> {"history": [...], "is_login": bool, "user_name": ""}
 *   POST index/aiAssistant/clearHistory
 *   POST index/aiAssistant/pickProductSearch
 *   GET  index/aiAssistant/getHotKeywords
 *   GET  index/aiAssistant/getFilterSuggestions
 *
 * 所有 AI 能力统一委托 app\common\AiService（支持 openai / 文心 / 讯飞 / 智谱 / Gemini / Claude 等协议）。
 * 会话历史存于 session 独立键，游客与登录用户均可用，且与管理员后台历史隔离。
 */
class AiAssistant extends Controller
{
    /** 购物助手系统提示词 */
    const SHOP_ASSISTANT_PROMPT = '你是一个热情、专业的电商导购助手，名字叫「小淘」。'
        . '请用简洁友好的语气帮助用户挑选商品、对比优劣、给出购买建议，'
        . '可以适当推荐商品链接思路，但不要编造不存在的具体优惠。回答尽量口语化、条理清晰。';

    /**
     * 页面入口（渲染购物助手 widget 所在的页面或片段）
     */
    public function index()
    {
        $this->display('index/ai_widget');
    }

    /**
     * 对话接口
     */
    public function chat()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_POST['message'])) {
            echo json_encode(array('reply' => '说点什么吧~', 'unconfigured' => false));
            return;
        }

        $message = trim($_POST['message']);

        if (!AiService::isChatAvailable()) {
            echo json_encode(array(
                'reply'        => '😣 AI 助手尚未配置，请联系站长在后台「AI 设置」中添加并启用对话模型~',
                'unconfigured' => true,
            ));
            return;
        }

        // 前台导购场景不写入后台管理员历史（AiService::chat 固定存 ai_chat_history），
        // 这里 useHistory=false 自行保存到前台独立键，避免游客对话污染后台历史。
        $reply = AiService::chat($message, self::SHOP_ASSISTANT_PROMPT, false);

        if (empty($reply) || $this->isAiError($reply)) {
            echo json_encode(array(
                'reply'        => '哎呀，AI 这会儿有点忙，请稍后再试一次吧~',
                'unconfigured' => false,
            ));
            return;
        }

        $this->saveFrontHistory($message, $reply);

        echo json_encode(array('reply' => $reply, 'unconfigured' => false), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取会话历史 + 登录态
     */
    public function getHistory()
    {
        header('Content-Type: application/json; charset=utf-8');

        $history = $this->loadFrontHistory();
        $loginUser = $this->loginUser ?? array();

        echo json_encode(array(
            'history'   => $history,
            'is_login'  => !empty($loginUser),
            'user_name' => !empty($loginUser['mobile']) ? $loginUser['mobile'] : (!empty($loginUser['nickname']) ? $loginUser['nickname'] : ''),
        ), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 清除会话历史
     */
    public function clearHistory()
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->saveFrontHistory('', '', true);
        echo json_encode(array('code' => 0, 'msg' => '已清除'));
    }

    // ==================== 购物助手附属接口（前端有静默降级，缺失也不影响主聊天） ====================

    /**
     * 选品搜索：按关键词 + 预算/品牌等筛选查商品库，返回卡片 HTML
     * POST: keyword / category / price / brand / scene / feature
     */
    public function pickProductSearch()
    {
        header('Content-Type: application/json; charset=utf-8');

        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = $_POST;
        }
        $keyword = isset($body['keyword']) ? trim($body['keyword']) : '';
        $price = isset($body['price']) ? (array)$body['price'] : array();

        if ($keyword === '') {
            echo json_encode(array('html' => '请告诉我你想找什么商品吧~'));
            return;
        }

        $model = obj('api/ApiData');
        // 先转义引号（防 SQL 注入闭合），再转义 LIKE 通配符 % _ \（纵深防御）
        $kw = addslashes($keyword);
        $kw = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $kw);
        $where = array("`title` LIKE '%{$kw}%'");
        $priceRange = $this->parsePriceRange($price);
        if ($priceRange) {
            $where[] = "`actualPrice` >= {$priceRange[0]} AND `actualPrice` <= {$priceRange[1]}";
        }
        $list = $model->dataSelect(
            $model->realTable('yun_items'),
            $where,
            "`id` DESC",
            10
        );
        if (empty($list)) {
            echo json_encode(array('html' => '抱歉，没找到「' . htmlspecialchars($keyword, ENT_QUOTES) . '」相关的商品，换个关键词试试~'));
            return;
        }

        $cards = array();
        foreach ($list as $it) {
            $title  = htmlspecialchars($it['title'] ?: ($it['dtitle'] ?? ''), ENT_QUOTES);
            $pic    = $it['mainPic'] ?? '';
            $price  = isset($it['actualPrice']) ? floatval($it['actualPrice']) : 0;
            $orig   = isset($it['originalPrice']) ? floatval($it['originalPrice']) : 0;
            $coupon = isset($it['couponPrice']) ? floatval($it['couponPrice']) : 0;
            $detailUrl = url(array('r' => 'index/view/smzdm_detail', 'id' => $it['id']));
            $couponHtml = $coupon ? '<span class="apc-coupon">券¥' . $coupon . '</span>' : '';
            $cards[] = '<div class="ai-product-card">'
                . '<a href="' . $detailUrl . '" target="_blank" rel="noopener">'
                . '<img src="' . $pic . '" alt="' . $title . '" loading="lazy">'
                . '<div class="apc-info">'
                . '<div class="apc-title">' . $title . '</div>'
                . '<div class="apc-price">¥' . $price . '<span class="apc-orig">¥' . $orig . '</span>' . $couponHtml . '</div>'
                . '</div></a></div>';
        }
        echo json_encode(array('html' => implode('', $cards)), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 热门问题词（前端有内置池兜底，这里返回 is_fallback 触发内置池）
     */
    public function getHotKeywords()
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'pools'       => array(),
            'is_fallback' => true,
            'updated'     => 'built-in',
        ), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 筛选器联动建议（返回空 groups 触发 JS 内置兜底）
     */
    public function getFilterSuggestions()
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('groups' => new \stdClass()), JSON_UNESCAPED_UNICODE);
    }

    // ==================== 私有辅助 ====================

    /**
     * 判断 AiService 返回的字符串是否为错误提示
     */
    private function isAiError($reply)
    {
        $s = (string)$reply;
        return strpos($s, 'AI 模型未配置') === 0
            || strpos($s, '大模型处理异常') === 0
            || strpos($s, '大模型API错误') === 0
            || strpos($s, 'CURL错误') === 0
            || strpos($s, 'HTTP错误') === 0;
    }

    /**
     * 读取前台独立历史（与管理员历史键隔离，避免互相污染）
     */
    private function loadFrontHistory()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return isset($_SESSION['ai_front_history']) && is_array($_SESSION['ai_front_history'])
            ? $_SESSION['ai_front_history'] : array();
    }

    /**
     * 保存前台独立历史（最多 10 条）
     */
    private function saveFrontHistory($prompt, $response, $clear = false)
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if ($clear) {
            $_SESSION['ai_front_history'] = array();
            return;
        }
        $history = $this->loadFrontHistory();
        $history[] = array('role' => 'user', 'content' => $prompt);
        $history[] = array('role' => 'assistant', 'content' => $response);
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        $_SESSION['ai_front_history'] = $history;
    }

    /**
     * 解析价格区间筛选为 [min, max]
     */
    private function parsePriceRange($priceFilters)
    {
        $map = array(
            '100元以下'   => array(0, 100),
            '100-300元'   => array(100, 300),
            '300-800元'   => array(300, 800),
            '800-2000元'  => array(800, 2000),
            '2000元以上'  => array(2000, 999999),
        );
        foreach ((array)$priceFilters as $p) {
            if (isset($map[$p])) {
                return $map[$p];
            }
        }
        return null;
    }
}
