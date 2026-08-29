<?php
namespace app\api\controller;

/**
 * AI 对话代理（OpenAI 兼容）
 * 小程序将消息体 POST 到本接口，由服务端持有真实 api_key 转发给 AI 提供商，
 * 返回标准 OpenAI 格式 JSON，供小程序插件原样消费。
 *
 * 请求：POST index.php?r=api/ai/chat
 *       支持 JSON 或 form 表单：{ messages:[{role,content}], model, temperature, max_tokens, stream }
 * 鉴权：若 aichat.token 非空，须携带 Authorization: Bearer <token> 或 ?token=<token>
 */
class AiController extends ApiBaseController {

    public function chat() {
        $this->options();

        $ai = $this->loadAiConfig();

        // 先在解析请求体前取出 messages，后续回落分支也会用到
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->raw();
        }
        $reqMessages = $payload['messages'] ?? array();

        // 令牌校验
        $token = $ai['token'] ?? '';
        if ($token !== '') {
            if ($this->requestToken() !== $token) {
                $this->json(array('error' => array('message' => '无效访问令牌')), 401);
            }
        }

        // 统一经 AiHub（AI 中台增强层）处理：支持多协议、失败自动降级到备用模型、用量埋点。
        // AiHub 内部复用 AiService 的协议适配 / SSL / 历史守卫能力。
        if (!empty($ai['enabled']) && !empty($ai['api_key'])) {
            // 小程序单独配置：用其配置的模型 key 作为首选，其余模型作降级候选
            $reply = \app\common\AiHub::chat(
                $this->messagesToPrompt($reqMessages),
                $ai['system_prompt'] ?? '你是一个有用的助手',
                false,
                array('model' => $ai['model'] ?? '')
            );
            $this->outputOpenAiCompat($reply);
        }

        // 小程序未单独配置时，回落到「AI 开放平台」当前对话模型，实现统一管理
        $chatInfo = \app\common\AiService::getChatModelInfo();
        if (empty($chatInfo) || empty($chatInfo['api_key'])) {
            $this->json(array('error' => array('message' => 'AI 服务未配置或未开启，请在「AI 开放平台 → 模型管理」中添加并启用对话模型')), 503);
        }

        $reply = \app\common\AiHub::chat(
            $this->messagesToPrompt($reqMessages),
            '你是一个有用的助手',
            false
        );
        $this->outputOpenAiCompat($reply);
    }

    /**
     * AI 导购接口（与桌面端统一逻辑，输出结构化 JSON 供小程序消费）
     *
     * 复用前台 AiAssistantController 的完整导购流程（意图识别 / 歧义消除 /
     * 全网商品搜索 / 结构化导购点评 / 模糊需求澄清），保证双端体验一致。
     * 桌面端以 HTML 片段返回（web 直渲），此处将其解析为结构化数据给小程序。
     *
     * 请求：POST index.php?r=api/ai/guide
     *       支持 JSON 或 form：{ message:"用户输入", forceMode:""|"search"|"compare"|"chat" }
     * 返回：{ code:1, type:"product|chat|clarify", text:"导购点评/回复",
     *         products:[{id,name,pic,price,coupon,sales,link,from,tag}], clarify:[{label,msg}] }
     */
    public function guide()
    {
        $this->options();
        $input = $this->body();
        if (empty($input)) {
            $input = $this->raw();
        }
        $message = isset($input['message']) ? trim($input['message']) : '';
        if (empty($message)) {
            $message = isset($_REQUEST['message']) ? trim($_REQUEST['message']) : '';
        }
        $forceMode = isset($input['forceMode']) ? trim($input['forceMode']) : '';

        if (empty($message)) {
            $this->json(array('code' => 0, 'msg' => '请输入您想咨询的问题~'));
            return;
        }

        // 与桌面端 chat() 一致：解析快捷指令前缀（前端未指定 forceMode 时启用）
        //   #关键词 → 直接搜商品   @关键词 → 全网比价   ?问题 → 纯聊天（跳过导购）
        if ($forceMode === '' && preg_match('/^([#@?])\s*(.+)$/u', $message, $m)) {
            $prefix = $m[1];
            $message = trim($m[2]); // 剥离前缀，避免干扰关键词提取/意图分析
            if ($prefix === '#')      $forceMode = 'search';
            elseif ($prefix === '@')  $forceMode = 'compare';
            elseif ($prefix === '?')  $forceMode = 'chat';
        }

        // 移动端无浏览器 session cookie，前端回传自定义 sessionId 以维持多轮对话历史
        // （桌面端 loadHistory/saveHistory 以 ai_uid Cookie 作为历史文件标识）
        $sid = isset($input['sessionId']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $input['sessionId']) : '';
        if ($sid !== '') {
            session_id($sid);
            @session_start();
            // 小程序/App 不会像浏览器那样自动回传 ai_uid Cookie，
            // 直接以移动端稳定下发的 sessionId 作为历史文件标识，
            // 否则每轮都生成新的随机 userId、历史对不上 → 多轮上下文丢失（鸡同鸭讲）。
            $_COOKIE['ai_uid'] = $sid;
        }

        // 复用前台导购主流程（同一份逻辑）
        $ctrl = new \app\index\controller\AiAssistantController();
        $res = $ctrl->chatLogic($message, $forceMode);

        $type = isset($res['type']) ? $res['type'] : 'chat';
        $replyHtml = isset($res['reply']) ? $res['reply'] : '';

        // 解析 HTML 为结构化数据
        $parsed = $this->parseGuideHtml($replyHtml);

        $data = array(
            'code' => 1,
            'type' => $type,
            'text' => $parsed['text'],
            'products' => $parsed['products'],
            'clarify' => $parsed['clarify'],
        );
        if (!empty($res['unconfigured'])) {
            $data['unconfigured'] = true;
        }
        $this->json($data);
    }

    /**
     * 将桌面端返回的导购 HTML 片段解析为结构化数组（小程序友好）
     * 依赖桌面端固定 class 结构，改动桌面端渲染样式时需同步本方法。
     */
    private function parseGuideHtml($html)
    {
        $result = array('text' => '', 'products' => array(), 'clarify' => array());
        if (empty($html)) {
            return $result;
        }
        $dom = new \DOMDocument();
        // 用 UTF-8 声明避免中文乱码；@ 抑制 HTML5 标签的警告
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        // 1) 导购点评 / 文本回复：取 .ai-advice 内文本；无则取整个片段纯文本
        $adviceNodes = $xpath->query('//div[contains(@class,"ai-advice")]');
        if ($adviceNodes && $adviceNodes->length > 0) {
            $result['text'] = $this->domInnerText($adviceNodes->item(0));
        } else {
            // 澄清引导 / 纯文本：取去标签文本
            $result['text'] = $this->domInnerText($dom->documentElement);
        }

        // 2) 商品卡：.ai-product-item 是 <a> 标签
        $itemNodes = $xpath->query('//a[contains(@class,"ai-product-item")]');
        if ($itemNodes) {
            foreach ($itemNodes as $node) {
                $link = $node->getAttribute('href');
                $img = $xpath->query('.//img', $node);
                $pic = ($img && $img->length > 0) ? $img->item(0)->getAttribute('src') : '';
                $titleNode = $xpath->query('.//*[contains(@class,"ai-product-title")]', $node);
                $name = ($titleNode && $titleNode->length > 0) ? $this->domInnerText($titleNode->item(0)) : '';
                // 价格：优先 .ai-product-price b（券后价）
                $priceNode = $xpath->query('.//*[contains(@class,"ai-product-price")]//b', $node);
                $price = ($priceNode && $priceNode->length > 0) ? $this->domInnerText($priceNode->item(0)) : '';
                $price = preg_replace('/[^0-9.]/', '', $price);
                // 优惠券
                $couponNode = $xpath->query('.//*[contains(@class,"ai-coupon")]', $node);
                $coupon = ($couponNode && $couponNode->length > 0) ? $this->domInnerText($couponNode->item(0)) : '';
                // 销量
                $salesNode = $xpath->query('.//*[contains(@class,"ai-sales")]', $node);
                $sales = ($salesNode && $salesNode->length > 0) ? $this->domInnerText($salesNode->item(0)) : '';
                // 卖点标签
                $tagNodes = $xpath->query('.//*[contains(@class,"ai-tag")]', $node);
                $tags = array();
                if ($tagNodes) {
                    foreach ($tagNodes as $tn) {
                        $tags[] = $this->domInnerText($tn);
                    }
                }
                // 真实商品标识（供移动端转链唤起电商 App，桌面端从 a 标签 data-* 注入）
                $goodsId = $node->getAttribute('data-goods-id');
                $goodsSign = $node->getAttribute('data-goods-sign');
                $fromCode = $node->getAttribute('data-from'); // tb / jd / pdd / vip
                $originalPrice = $node->getAttribute('data-original-price');
                $shop = $node->getAttribute('data-shop');
                if (!in_array($fromCode, array('tb', 'jd', 'pdd', 'vip'), true)) {
                    $fromCode = '';
                }
                // 展示用平台中文名（从标题前缀或 data-from 推断）
                $fromText = '';
                if (preg_match('/【(淘宝|京东|拼多多|唯品会)】/u', $name, $fm)) {
                    $fromText = $fm[1];
                } elseif ($fromCode) {
                    $fromMap = array('tb' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会');
                    $fromText = $fromMap[$fromCode];
                }
                $result['products'][] = array(
                    'id' => $goodsId ? $goodsId : md5($link . $name),
                    'goodsId' => $goodsId,
                    'goodsSign' => $goodsSign,
                    'platform' => $fromCode,
                    'name' => $name,
                    'pic' => $pic,
                    'price' => $price,
                    'coupon' => $coupon,
                    'sales' => $sales,
                    'link' => $link,
                    'from' => $fromText,
                    'tag' => implode(' ', $tags),
                    'originalPrice' => $originalPrice !== '' ? $originalPrice : '',
                    'shop' => $shop,
                );
            }
        }

        // 3) 模糊需求澄清按钮：.ai-clarify-btn
        $clarifyNodes = $xpath->query('//*[contains(@class,"ai-clarify-btn")]');
        if ($clarifyNodes) {
            foreach ($clarifyNodes as $btn) {
                $label = $this->domInnerText($btn);
                $msg = $btn->getAttribute('data-msg');
                if ($label && $msg) {
                    $result['clarify'][] = array('label' => $label, 'msg' => $msg);
                }
            }
        }

        return $result;
    }

    /**
     * 提取 DOM 节点纯文本（递归合并子节点文本，去多余空白）
     */
    private function domInnerText($node)
    {
        $text = $node->textContent;
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
}
