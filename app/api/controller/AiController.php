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
}
