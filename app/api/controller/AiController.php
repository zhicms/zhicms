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

        // 令牌校验
        $token = $ai['token'] ?? '';
        if ($token !== '') {
            if ($this->requestToken() !== $token) {
                $this->json(array('error' => array('message' => '无效访问令牌')), 401);
            }
        }

        if (empty($ai['enabled']) || empty($ai['api_key'])) {
            // 小程序未单独配置时，回落到「AI 开放平台」当前对话模型，实现统一管理
            $chatInfo = \app\common\AiService::getChatModelInfo();
            if (empty($chatInfo) || empty($chatInfo['api_key'])) {
                $this->json(array('error' => array('message' => 'AI 服务未配置或未开启，请在「AI 开放平台 → 模型管理」中添加并启用对话模型')), 503);
            }

            $protocol = isset($chatInfo['protocol']) ? strtolower($chatInfo['protocol']) : 'openai';
            // OpenAI / Azure 兼容：直接使用原 Bearer 转发，返回原生 OpenAI 格式
            if ($protocol === 'openai' || $protocol === 'azure') {
                $ai['api_url'] = $chatInfo['api_url'];
                $ai['api_key'] = $chatInfo['api_key'];
                $ai['model']   = $chatInfo['model'];
                $ai['temperature'] = 0.7;
                $ai['max_tokens']  = 1024;
            } else {
                // 其他协议（Gemini/Claude/文心/讯飞）：用 AiService 统一对话，再包装为 OpenAI 格式返回
                $reply = \app\common\AiService::chat($payload['messages'] ?? array(), '你是一个有用的助手', false);
                if (empty($reply) || strpos($reply, '大模型') === 0 || strpos($reply, 'AI 模型') === 0 || strpos($reply, 'CURL错误') === 0 || strpos($reply, 'HTTP错误') === 0) {
                    $this->json(array('error' => array('message' => 'AI 对话失败：' . $reply)), 502);
                }
                $this->json(array(
                    'choices' => array(array('message' => array('role' => 'assistant', 'content' => $reply), 'index' => 0, 'finish_reason' => 'stop')),
                    'usage'   => new \stdClass(),
                ), 200);
            }
        }

        // 解析请求体
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->raw();
        }

        $messages = $payload['messages'] ?? array();
        if (empty($messages) || !is_array($messages)) {
            $this->json(array('error' => array('message' => 'messages 不能为空')), 400);
        }

        $model       = !empty($payload['model']) ? $payload['model'] : ($ai['model'] ?? '');
        $temperature = isset($payload['temperature']) ? floatval($payload['temperature']) : ($ai['temperature'] ?? 0.7);
        $maxTokens   = isset($payload['max_tokens']) ? intval($payload['max_tokens']) : ($ai['max_tokens'] ?? 1024);

        // 统一以非流式转发，兼容小程序 uni.request
        $forward = array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'stream'      => false,
        );

        $ch = curl_init($ai['api_url']);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $ai['api_key'],
            ),
            CURLOPT_POSTFIELDS     => json_encode($forward, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));

        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            $this->json(array('error' => array('message' => 'AI 网关请求失败：' . $curlErr)), 502);
        }

        // 原样返回提供商响应（OpenAI 格式），由小程序插件解析
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');
        if ($httpCode > 0) {
            http_response_code($httpCode);
        }
        echo $resp;
        exit;
    }
}
