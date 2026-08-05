<?php
/**
 * AI 开放平台 - 核心服务类
 * 
 * 支持所有兼容 OpenAI API 协议的大模型（文本对话 + 图像生成）。
 * 常用平台：DeepSeek / 智谱AI / 硅基流动 / 豆包 / OpenAI / 通义千问 等。
 *
 * @package common
 */

namespace app\common;

class AiService
{
    /**
     * 已加载的 AI 配置（单例缓存）
     */
    private static $config = null;

    /**
     * 加载 AI 配置文件
     */
    public static function loadConfig()
    {
        if (self::$config !== null) {
            return self::$config;
        }
        $configFile = \CONFIG_PATH . 'ai.php';
        if (!file_exists($configFile)) {
            self::$config = array('ai_chat' => '', 'ai_image' => '', 'ai_system_prompt' => '', 'ai_models' => array());
            return self::$config;
        }
        include $configFile;
        self::$config = isset($AI) ? $AI : array('ai_chat' => '', 'ai_image' => '', 'ai_system_prompt' => '', 'ai_models' => array());
        self::$config = self::migrateEndpoints(self::$config);
        return self::$config;
    }

    /**
     * 老配置的接口地址迁移。
     *
     * 部分厂商已把接口迁移到 OpenAI 兼容协议，旧地址要么无法访问（讯飞的 wss://
     * 是 WebSocket，PHP curl 根本连不上），要么域名已变更。这里在读取配置时
     * 自动纠正，避免用户升级后还得手动逐个重填模型。
     *
     * @param array $config
     * @return array
     */
    private static function migrateEndpoints($config)
    {
        if (empty($config['ai_models']) || !is_array($config['ai_models'])) {
            return $config;
        }

        $changed = false;
        foreach ($config['ai_models'] as $key => $model) {
            if (!is_array($model) || empty($model['api_url'])) continue;
            $url = trim($model['api_url']);

            // 1) 讯飞星火：WebSocket / 老 MaaS 地址 -> OpenAI 兼容 HTTP 接口
            if (stripos($url, 'maas-api') !== false
                || stripos($url, 'spark-api.xf-yun.com') !== false
                || stripos($url, 'wss://') === 0) {
                $config['ai_models'][$key]['api_url']  = 'https://spark-api-open.xf-yun.com/v1/chat/completions';
                $config['ai_models'][$key]['protocol'] = 'openai';
                $changed = true;
                continue;
            }

            // 2) 百度文心：aip.baidubce.com 老接口 -> 千帆 V2 OpenAI 兼容接口
            if (stripos($url, 'aip.baidubce.com') !== false) {
                $config['ai_models'][$key]['api_url']  = 'https://qianfan.baidubce.com/v2/chat/completions';
                $config['ai_models'][$key]['protocol'] = 'openai';
                $changed = true;
                continue;
            }

            // 3) MiniMax 域名变更 api.minimax.chat -> api.minimaxi.com
            if (stripos($url, 'api.minimax.chat') !== false) {
                $config['ai_models'][$key]['api_url'] = str_ireplace('api.minimax.chat', 'api.minimaxi.com', $url);
                $changed = true;
            }
        }

        // 迁移结果落盘一次，避免每次请求重复计算
        if ($changed) {
            $content = "<?php\n/**\n * AI 开放平台配置\n */\n\n\$AI = " . var_export($config, true) . ";\n";
            @file_put_contents(\CONFIG_PATH . 'ai.php', $content, LOCK_EX);
        }
        return $config;
    }

    /**
     * 保存 AI 配置
     */
    public static function saveConfig($config)
    {
        // 保持配置结构完整
        $defaults = array('ai_chat' => '', 'ai_image' => '', 'ai_system_prompt' => '', 'ai_models' => array());
        $config = array_merge($defaults, $config);

        $content = "<?php\n/**\n * AI 开放平台配置\n */\n\n\$AI = " . var_export($config, true) . ";\n";
        $of = fopen(\CONFIG_PATH . 'ai.php', 'w');
        if ($of) {
            fwrite($of, $content);
            fclose($of);
            self::$config = null; // 清除缓存
            return true;
        }
        return false;
    }

    /**
     * 获取所有模型列表
     */
    public static function models()
    {
        $config = self::loadConfig();
        return isset($config['ai_models']) ? $config['ai_models'] : array();
    }

    /**
     * 获取指定类型的模型列表
     * @param string $type 模型类型 chat|image
     */
    public static function getModelsByType($type = 'chat')
    {
        $allModels = self::models();
        $filtered = array();
        foreach ($allModels as $key => $model) {
            $modelType = isset($model['type']) ? $model['type'] : 'chat';
            if ($modelType === $type) {
                $filtered[$key] = $model;
            }
        }
        return $filtered;
    }

    /**
     * 获取当前启用的对话模型 key
     */
    public static function getCurrentChatKey()
    {
        $config = self::loadConfig();
        return isset($config['ai_chat']) ? $config['ai_chat'] : '';
    }

    /**
     * 获取当前启用的图像模型 key
     */
    public static function getCurrentImageKey()
    {
        $config = self::loadConfig();
        return isset($config['ai_image']) ? $config['ai_image'] : '';
    }

    /**
     * 获取当前对话模型信息
     */
    public static function getChatModelInfo()
    {
        $key = self::getCurrentChatKey();
        if (empty($key)) return null;
        $models = self::models();
        return isset($models[$key]) ? $models[$key] : null;
    }

    /**
     * 获取当前图像模型信息
     */
    public static function getImageModelInfo()
    {
        $key = self::getCurrentImageKey();
        if (empty($key)) return null;
        $models = self::models();
        return isset($models[$key]) ? $models[$key] : null;
    }

    /**
     * 获取当前对话模型名称
     */
    public static function getChatModelName()
    {
        $info = self::getChatModelInfo();
        return $info ? $info['model'] : '';
    }

    /**
     * 非流式 AI 对话
     * @param string $prompt 用户输入
     * @param string $systemPrompt 系统提示词
     * @param bool $useHistory 是否使用会话历史
     * @return string AI回复内容
     */
    public static function chat($prompt, $systemPrompt = '你是一个有用的助手', $useHistory = false)
    {
        $messages = self::buildMessages($prompt, $systemPrompt, $useHistory);
        $response = self::sendRequest($messages);
        $result = self::parseResponse($response);

        if ($useHistory && !self::isErrorResult($result)) {
            self::saveHistory($prompt, $result);
        }

        return $result;
    }

    /**
     * 流式 AI 对话 (SSE)
     * 直接输出 Server-Sent Events 流到客户端
     */
    public static function chatStream($prompt, $systemPrompt = '你是一个有用的助手', $useHistory = false)
    {
        $messages = self::buildMessages($prompt, $systemPrompt, $useHistory);

        $fullResponse = self::sendStreamRequest($messages);

        if ($useHistory && !empty($fullResponse)) {
            self::saveHistory($prompt, $fullResponse);
        }

        return $fullResponse;
    }

    /**
     * 图像生成
     */
    public static function generateImage($prompt, $options = array())
    {
        $modelInfo = self::getImageModelInfo();
        if (!$modelInfo) {
            return array('error' => '未配置图像生成模型，请先在 AI 设置中添加模型并启用');
        }

        $data = array(
            'model'   => $modelInfo['model'],
            'prompt'  => $prompt,
            'n'       => isset($options['n']) ? $options['n'] : 1,
            'size'    => isset($options['size']) ? $options['size'] : '1024x1024',
        );

        if (isset($options['quality'])) {
            $data['quality'] = $options['quality'];
        }

        // 豆包模型去水印
        if (strpos($modelInfo['model'], 'doubao-seedream') !== false) {
            $data['watermark'] = false;
        }

        return self::sendImageApi($modelInfo['api_url'], $modelInfo['api_key'], $data);
    }

    // ==================== 私有方法 ====================

    /**
     * 构建消息数组
     */
    private static function buildMessages($prompt, $systemPrompt, $useHistory)
    {
        $messages = array();

        if (!empty($systemPrompt)) {
            $messages[] = array(
                'content' => $systemPrompt,
                'role'    => 'system'
            );
        }

        if ($useHistory) {
            $history = self::getHistory();
            foreach ($history as $msg) {
                $messages[] = array(
                    'role'    => $msg['role'],
                    'content' => $msg['content']
                );
            }
        }

        $messages[] = array(
            'content' => $prompt,
            'role'    => 'user'
        );

        return $messages;
    }

    /**
     * 获取模型协议（默认 openai 兼容）
     */
    private static function getProtocol($modelInfo)
    {
        return isset($modelInfo['protocol']) ? strtolower(trim($modelInfo['protocol'])) : 'openai';
    }

    /**
     * 发送非流式 API 请求（按协议分流）
     */
    private static function sendRequest($messages)
    {
        $modelInfo = self::getChatModelInfo();
        if (!$modelInfo) {
            return json_encode(array('error' => 'AI 模型未配置'));
        }
        $protocol = self::getProtocol($modelInfo);
        return self::requestByProtocol($protocol, $modelInfo, $messages, false);
    }

    /**
     * 发送流式 API 请求并直接输出 SSE（按协议分流）
     */
    private static function sendStreamRequest($messages)
    {
        $modelInfo = self::getChatModelInfo();
        if (!$modelInfo) {
            echo "data: " . json_encode(array("error" => "AI 模型未配置"), JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "data: [DONE]\n\n";
            return '';
        }
        $protocol = self::getProtocol($modelInfo);
        return self::streamByProtocol($protocol, $modelInfo, $messages);
    }

    /**
     * 按协议构建请求并发送（非流式），返回原始响应字符串
     */
    private static function requestByProtocol($protocol, $modelInfo, $messages, $stream)
    {
        switch ($protocol) {
            case 'gemini':
                return self::requestGemini($modelInfo, $messages, $stream);
            case 'anthropic':
                return self::requestAnthropic($modelInfo, $messages, $stream);
            case 'ernie':
                return self::requestErnie($modelInfo, $messages, $stream);
            case 'azure':
                return self::requestAzure($modelInfo, $messages, $stream);
            case 'xinghuo':
                return self::requestXinghuo($modelInfo, $messages, $stream);
            case 'openai':
            default:
                return self::requestOpenAi($modelInfo, $messages, $stream);
        }
    }

    /**
     * OpenAI 兼容协议（DeepSeek/智谱/硅基/豆包/通义/OpenAI/Kimi/MiniMax 等）
     */
    private static function requestOpenAi($modelInfo, $messages, $stream)
    {
        $postData = json_encode(array(
            'messages'    => $messages,
            'model'       => $modelInfo['model'],
            'max_tokens'  => 4096,
            'stream'      => $stream,
            'temperature' => 1,
        ), JSON_UNESCAPED_UNICODE);

        return self::curlRequest($modelInfo['api_url'], $modelInfo['api_key'], $postData, 60, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $modelInfo['api_key'],
        ));
    }

    /**
     * Azure OpenAI（与 OpenAI 协议一致，但用 api-key 头并附加 api-version）
     */
    private static function requestAzure($modelInfo, $messages, $stream)
    {
        $url = $modelInfo['api_url'];
        if (strpos($url, 'api-version=') === false) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'api-version=2024-02-15-preview';
        }
        $postData = json_encode(array(
            'messages'    => $messages,
            'max_tokens'  => 4096,
            'stream'      => $stream,
            'temperature' => 1,
        ), JSON_UNESCAPED_UNICODE);

        return self::curlRequest($url, $modelInfo['api_key'], $postData, 60, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'api-key: ' . $modelInfo['api_key'],
        ));
    }

    /**
     * Google Gemini 协议
     * URL: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key=KEY
     */
    private static function requestGemini($modelInfo, $messages, $stream)
    {
        $apiKey = $modelInfo['api_key'];
        // 支持两种方式：api_url 已含完整 endpoint（含 ?key=），或仅填项目/模型由 key 拼接
        if (strpos($modelInfo['api_url'], 'generativelanguage.googleapis.com') !== false) {
            $url = $modelInfo['api_url'];
            if (strpos($url, 'key=') === false) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'key=' . $apiKey;
            }
        } else {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelInfo['model']
                . ':generateContent?key=' . $apiKey;
        }

        // 拆分 system 与对话
        $systemText = '';
        $contents = array();
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemText = $m['content'];
                continue;
            }
            $role = ($m['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = array(
                'role'  => $role,
                'parts' => array(array('text' => $m['content'])),
            );
        }
        if (empty($contents)) {
            $contents[] = array('role' => 'user', 'parts' => array(array('text' => '')));
        }

        $body = array(
            'contents'         => $contents,
            'generationConfig' => array('temperature' => 1, 'maxOutputTokens' => 4096),
        );
        if ($systemText !== '') {
            $body['systemInstruction'] = array('parts' => array(array('text' => $systemText)));
        }

        $postData = json_encode($body, JSON_UNESCAPED_UNICODE);
        return self::curlRequest($url, $apiKey, $postData, 60, array(
            'Content-Type: application/json',
            'Accept: application/json',
        ));
    }

    /**
     * Anthropic Claude 协议
     */
    private static function requestAnthropic($modelInfo, $messages, $stream)
    {
        $systemText = '';
        $chatMessages = array();
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemText = $m['content'];
                continue;
            }
            $chatMessages[] = array('role' => $m['role'], 'content' => $m['content']);
        }
        $body = array(
            'model'       => $modelInfo['model'],
            'max_tokens'  => 4096,
            'temperature' => 1,
            'messages'    => $chatMessages,
        );
        if ($systemText !== '') {
            $body['system'] = $systemText;
        }
        if ($stream) {
            $body['stream'] = true;
        }
        $postData = json_encode($body, JSON_UNESCAPED_UNICODE);

        return self::curlRequest($modelInfo['api_url'], $modelInfo['api_key'], $postData, 60, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'x-api-key: ' . $modelInfo['api_key'],
            'anthropic-version: 2023-06-01',
        ));
    }

    /**
     * 百度文心 ERNIE 协议（需先用 APIKey+Secret 换取 access_token）
     */
    private static function requestErnie($modelInfo, $messages, $stream)
    {
        $apiKey    = $modelInfo['api_key'];               // 百度 API Key
        $apiSecret = isset($modelInfo['api_secret']) ? $modelInfo['api_secret'] : ''; // 百度 Secret Key

        // 获取 access_token（带简单缓存到 runtime）
        $token = self::getErnieToken($apiKey, $apiSecret);
        if ($token === false) {
            return json_encode(array('error' => '文心 access_token 获取失败，请检查 API Key / Secret Key'));
        }

        // 文心对话地址：https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/{model}
        if (strpos($modelInfo['api_url'], 'aip.baidubce.com') !== false) {
            $url = $modelInfo['api_url'];
        } else {
            $url = 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/' . $modelInfo['model'];
        }
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . $token;

        $body = array(
            'messages'    => $messages,
            'temperature' => 1,
            'stream'      => false,
        );
        $postData = json_encode($body, JSON_UNESCAPED_UNICODE);
        return self::curlRequest($url, $apiKey, $postData, 60, array(
            'Content-Type: application/json',
            'Accept: application/json',
        ));
    }

    /**
     * 科大讯飞 MaaS / 星火 HTTP 推理协议（HMAC-SHA256 签名鉴权）
     * api_key 存 APIKey，api_secret 存 APISecret，api_url 为推理服务地址（需含 app_id 由 model 旁字段给出）
     */
    private static function requestXinghuo($modelInfo, $messages, $stream)
    {
        $apiKey    = $modelInfo['api_key'];
        $apiSecret = isset($modelInfo['api_secret']) ? $modelInfo['api_secret'] : '';
        $appId     = isset($modelInfo['app_id']) ? $modelInfo['app_id'] : '';
        $url       = $modelInfo['api_url'];

        // 构造签名鉴权头
        $authHeaders = self::buildXinghuoAuth($url, $apiKey, $apiSecret);
        if ($authHeaders === false) {
            return json_encode(array('error' => '讯飞鉴权生成失败，请检查 APIKey / APISecret'));
        }

        // 讯飞自定义请求体
        $text = array();
        foreach ($messages as $m) {
            $text[] = array('role' => $m['role'], 'content' => $m['content']);
        }
        $body = array(
            'header'    => array('app_id' => $appId, 'uid' => 'zhi_cms'),
            'parameter' => array('chat' => array('temperature' => 1)),
            'payload'   => array('message' => array('text' => $text)),
        );
        $postData = json_encode($body, JSON_UNESCAPED_UNICODE);

        return self::curlRequest($url, $apiKey, $postData, 60, array_merge(
            array('Content-Type: application/json', 'Accept: application/json'),
            $authHeaders
        ));
    }

    /**
     * 构造讯飞通用 URL 鉴权头（HMAC-SHA256）
     */
    private static function buildXinghuoAuth($url, $apiKey, $apiSecret)
    {
        $parsed = parse_url($url);
        if ($parsed === false) return false;
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        if (isset($parsed['query']) && $parsed['query'] !== '') {
            $path .= '?' . $parsed['query'];
        }
        if ($host === '' || $apiKey === '' || $apiSecret === '') return false;

        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $signatureOrigin = "host: {$host}\n";
        $signatureOrigin .= "date: {$date}\n";
        $signatureOrigin .= "POST {$path} HTTP/1.1";
        $signatureSha = hash_hmac('sha256', $signatureOrigin, $apiSecret, true);
        $signature = base64_encode($signatureSha);
        $authorization = base64_encode("api_key=\"$apiKey\", algorithm=\"hmac-sha256\", headers=\"host date request-line\", signature=\"$signature\"");

        return array(
            'Authorization: ' . $authorization,
            'Host: ' . $host,
            'Date: ' . $date,
        );
    }

    /**
     * 文心 access_token 获取（runtime 缓存 30 天）
     */
    private static function getErnieToken($apiKey, $apiSecret)
    {
        if ($apiKey === '' || $apiSecret === '') return false;
        // 凭证缓存放在独立目录并写入 .htaccess 禁止 Web 访问，避免 access_token 被直接下载
        $cacheDir = \BASE_PATH . 'runtime/credential/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0700, true);
        }
        if (!is_file($cacheDir . '.htaccess')) {
            @file_put_contents($cacheDir . '.htaccess', "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
        }
        if (!is_file($cacheDir . 'index.html')) {
            @file_put_contents($cacheDir . 'index.html', '');
        }
        $cacheFile = $cacheDir . 'ernie_token_' . md5($apiKey) . '.json';
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['expire']) && $cached['expire'] > time() && !empty($cached['token'])) {
                return $cached['token'];
            }
        }
        $tokenUrl = 'https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials'
            . '&client_id=' . urlencode($apiKey) . '&client_secret=' . urlencode($apiSecret);
        $ch = curl_init();
        curl_setopt_array($ch, self::sslOptions() + array(
            CURLOPT_URL            => $tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ));
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data['access_token'])) {
            return false;
        }
        $token = $data['access_token'];
        $expire = time() + (isset($data['expires_in']) ? intval($data['expires_in']) : 2592000) - 60;
        @file_put_contents($cacheFile, json_encode(array('token' => $token, 'expire' => $expire)));
        return $token;
    }

    /**
     * 按协议发送流式请求并输出 SSE，返回累积文本
     */
    private static function streamByProtocol($protocol, $modelInfo, $messages)
    {
        switch ($protocol) {
            case 'gemini':
            case 'anthropic':
            case 'ernie':
            case 'azure':
            case 'xinghuo':
                // 这些协议暂以非流式结果回灌 SSE（统一降级为一次性输出），保证前端可用
                $raw = self::requestByProtocol($protocol, $modelInfo, $messages, false);
                $text = self::parseResponseByProtocol($protocol, $raw);
                if ($text !== '' && strpos($text, '大模型') !== 0 && strpos($text, 'AI 模型') !== 0) {
                    echo "data: " . json_encode(array('choices' => array(array('delta' => array('content' => $text)))), JSON_UNESCAPED_UNICODE) . "\n\n";
                } else {
                    echo "data: " . json_encode(array('error' => $text), JSON_UNESCAPED_UNICODE) . "\n\n";
                }
                echo "data: [DONE]\n\n";
                return $text;
            case 'openai':
            default:
                return self::streamOpenAi($modelInfo, $messages);
        }
    }

    /**
     * OpenAI 流式请求（SSE，逐块输出并累积）
     */
    private static function streamOpenAi($modelInfo, $messages)
    {
        $postData = json_encode(array(
            'messages'    => $messages,
            'model'       => $modelInfo['model'],
            'stream'      => true,
            'temperature' => 1,
            'max_tokens'  => 4096,
        ), JSON_UNESCAPED_UNICODE);

        $fullResponse = '';
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $modelInfo['api_url'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $modelInfo['api_key'],
            ),
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION  => function ($curl, $data) use (&$fullResponse) {
                echo $data;
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'data:') === 0) {
                        $jsonStr = trim(substr($line, 5));
                        if ($jsonStr === '[DONE]') continue;
                        $json = json_decode($jsonStr, true);
                        if (is_array($json)) {
                            $chunk = '';
                            if (isset($json['choices'][0]['delta']['content'])) {
                                $chunk = $json['choices'][0]['delta']['content'];
                            } elseif (isset($json['choices'][0]['message']['content'])) {
                                $chunk = $json['choices'][0]['message']['content'];
                            }
                            $fullResponse .= $chunk;
                        }
                    }
                }
                return strlen($data);
            },
        ) + self::sslOptions());

        curl_exec($ch);
        if (curl_errno($ch)) {
            echo "data: [ERROR] " . curl_error($ch) . "\n\n";
        }
        curl_close($ch);

        return $fullResponse;
    }

    /**
     * 通用 cURL 请求（支持自定义 headers）
     */
    private static function curlRequest($url, $apiKey, $postData, $timeout = 60, $headers = null)
    {
        // curl 扩展缺失：返回结构化错误，交由上层降级/提示，避免 PHP 8 下抛 \Error 导致白屏
        if (!extension_loaded('curl')) {
            return json_encode(array('error' => 'curl 扩展未安装，AI 功能不可用。请在 php.ini 中启用 curl 扩展。'));
        }

        if ($headers === null) {
            $headers = array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
            );
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
        ) + self::sslOptions());

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return 'CURL错误: ' . $error;
        }
        if ($httpCode !== 200) {
            return 'HTTP错误: ' . $httpCode . ' ' . substr((string)$response, 0, 300);
        }
        return $response;
    }

    /**
     * 统一的 SSL 校验选项。
     *
     * 安全考量：本类的请求头里携带用户的 AI API Key，关闭证书校验会带来中间人窃取风险，
     * 因此默认开启校验；仅在服务器确实找不到 CA 证书包时才降级，避免升级后接口全挂。
     *
     * @return array curl 选项数组
     */
    public static function sslOptions()
    {
        static $opts = null;
        if ($opts !== null) return $opts;

        $caBundle = '';
        // 1) php.ini 已配置 curl.cainfo / openssl.cafile 时，curl 自行使用，无需干预
        $ini = ini_get('curl.cainfo');
        if (!$ini) $ini = ini_get('openssl.cafile');
        if ($ini && is_file($ini)) {
            $caBundle = $ini;
        } else {
            // 2) 尝试常见位置（含随程序分发的证书包）
            $candidates = array(
                \BASE_PATH . 'data/cacert.pem',
                '/etc/ssl/certs/ca-certificates.crt',
                '/etc/pki/tls/certs/ca-bundle.crt',
                '/usr/local/etc/openssl/cert.pem',
            );
            foreach ($candidates as $c) {
                if (is_file($c)) { $caBundle = $c; break; }
            }
        }

        if ($caBundle) {
            $opts = array(
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CAINFO         => $caBundle,
            );
        } elseif ($ini) {
            // php.ini 配好了但文件路径读不到，仍交给 curl 默认行为并开启校验
            $opts = array(
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            );
        } else {
            // 环境确实没有 CA 包，降级但保持可用（并记录一次告警）
            $opts = array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            );
        }
        return $opts;
    }

    /**
     * 解析 API 响应（按协议分发）
     */
    private static function parseResponse($response)
    {
        $modelInfo = self::getChatModelInfo();
        $protocol = $modelInfo ? self::getProtocol($modelInfo) : 'openai';
        return self::parseResponseByProtocol($protocol, $response);
    }

    /**
     * 按协议解析响应为纯文本
     */
    private static function parseResponseByProtocol($protocol, $response)
    {
        // 内部错误：AI 模型未配置（sendRequest 返回的）
        if (strpos($response, 'AI 模型未配置') === 0) {
            return $response;
        }
        // CURL / HTTP 错误前缀
        if (strpos($response, 'CURL错误') === 0 || strpos($response, 'HTTP错误') === 0) {
            return $response;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return '大模型处理异常，请稍后再试，错误信息：' . substr($response, 0, 300);
        }

        // 通用 API 错误
        if (isset($data['error'])) {
            $error = $data['error'];
            if (is_array($error) && isset($error['message'])) {
                return '大模型API错误：' . $error['message'];
            }
            if (is_string($error)) {
                return '大模型API错误：' . $error;
            }
            return '大模型API错误：未知错误';
        }

        switch ($protocol) {
            case 'gemini':
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    return $data['candidates'][0]['content']['parts'][0]['text'];
                }
                if (isset($data['error']['message'])) {
                    return '大模型API错误：' . $data['error']['message'];
                }
                return '大模型处理异常，请稍后再试，错误信息：' . substr($response, 0, 300);

            case 'anthropic':
                if (isset($data['content'][0]['text'])) {
                    return $data['content'][0]['text'];
                }
                if (isset($data['error']['message'])) {
                    return '大模型API错误：' . $data['error']['message'];
                }
                return '大模型处理异常，请稍后再试，错误信息：' . substr($response, 0, 300);

            case 'ernie':
                if (isset($data['result'])) {
                    return $data['result'];
                }
                if (isset($data['error_msg'])) {
                    return '大模型API错误：' . $data['error_msg'];
                }
                return '大模型处理异常，请稍后再试，错误信息：' . substr($response, 0, 300);

            case 'xinghuo':
                if (isset($data['payload']['choices']['text'][0]['content'])) {
                    return $data['payload']['choices']['text'][0]['content'];
                }
                if (isset($data['header']['code']) && $data['header']['code'] != 0) {
                    return '大模型API错误：讯飞返回错误码 ' . $data['header']['code'];
                }
                return '大模型处理异常，请稍后再试，错误信息：' . substr($response, 0, 300);

            case 'openai':
            case 'azure':
            default:
                if (isset($data['choices'][0]['message']['content'])) {
                    return $data['choices'][0]['message']['content'];
                }
                if (isset($data['choices'][0]['text'])) {
                    return $data['choices'][0]['text'];
                }
                return '大模型处理异常，请稍后再试，错误信息：' . substr($response, 0, 300);
        }
    }

    /**
     * 发送图像生成 API 请求
     */
    private static function sendImageApi($apiUrl, $apiKey, $data)
    {
        // curl 扩展缺失：返回错误数组，由调用方提示
        if (!extension_loaded('curl')) {
            return array('error' => 'curl 扩展未安装，图像生成功能不可用。请在 php.ini 中启用 curl 扩展。');
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ) + self::sslOptions());

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array('error' => 'CURL错误: ' . $error);
        }
        if ($httpCode !== 200) {
            return array('error' => 'HTTP错误: ' . $httpCode . ' ' . substr($response, 0, 200));
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('error' => 'JSON解析错误');
        }

        return $result;
    }

    // ==================== 会话历史管理 ====================

    /**
     * 获取会话历史（最近 10 条）
     */
    private static function getHistory()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['ai_chat_history']) && is_array($_SESSION['ai_chat_history'])
            ? $_SESSION['ai_chat_history'] : array();
    }

    /**
     * 保存对话到会话历史
     */
    private static function saveHistory($prompt, $response)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $history = isset($_SESSION['ai_chat_history']) && is_array($_SESSION['ai_chat_history'])
            ? $_SESSION['ai_chat_history'] : array();

        $history[] = array('role' => 'user', 'content' => $prompt);
        $history[] = array('role' => 'assistant', 'content' => $response);

        // 只保留最近 10 条（5 轮对话）
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }

        $_SESSION['ai_chat_history'] = $history;
    }

    /**
     * 清除会话历史
     */
    public static function clearHistory()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['ai_chat_history'] = array();
    }

    /**
     * 获取会话历史（公开方法）
     */
    public static function getHistoryPublic()
    {
        return self::getHistory();
    }

    /**
     * 检查 AI 对话模型是否可用
     * @return bool
     */
    public static function isChatAvailable()
    {
        $info = self::getChatModelInfo();
        return !empty($info) && !empty($info['api_url']) && !empty($info['api_key']);
    }

    /**
     * 检查 AI 返回结果是否为错误
     * @param string $result
     * @return bool
     */
    private static function isErrorResult($result)
    {
        if (empty($result)) return true;
        if (strpos($result, 'AI 模型未配置') === 0) return true;
        if (strpos($result, '大模型处理异常') === 0) return true;
        if (strpos($result, '大模型API错误') === 0) return true;
        return false;
    }

    // ==================== AI 辅助写作 ====================

    /**
     * AI 提取文章关键词
     * @param string $title 文章标题
     * @param string $content 文章内容
     * @return string 逗号分隔的关键词
     */
    public static function extractKeywords($title, $content = '')
    {
        if (!self::isChatAvailable()) {
            return self::extractKeywordsFallback($title, $content);
        }

        $prompt = "请从以下文章标题和内容中提取3-5个最相关的关键词，用英文逗号分隔，只输出关键词，不要其他解释：\n\n标题：{$title}";
        if (!empty($content)) {
            $contentText = strip_tags($content);
            if (mb_strlen($contentText) > 500) {
                $contentText = mb_substr($contentText, 0, 500);
            }
            $prompt .= "\n\n内容摘要：{$contentText}";
        }

        $systemPrompt = '你是一个SEO专家，擅长提取文章关键词。';
        $result = self::chat($prompt, $systemPrompt, false);

        if (self::isErrorResult($result)) {
            return self::extractKeywordsFallback($title, $content);
        }

        $result = trim($result);
        $result = preg_replace('/^[^\p{Han}a-zA-Z0-9,，、\s]+|[^\p{Han}a-zA-Z0-9,，、\s]+$/u', '', $result);
        $result = str_replace(array('，', '、'), ',', $result);
        $parts = array_filter(array_map('trim', explode(',', $result)));
        $parts = array_slice(array_unique($parts), 0, 5);

        if (empty($parts)) {
            return self::extractKeywordsFallback($title, $content);
        }

        return implode(',', $parts);
    }

    /**
     * 本地规则提取关键词（AI 不可用时的降级方案）
     */
    private static function extractKeywordsFallback($title, $content = '')
    {
        $keywords = array();

        $title = trim($title);
        if (!empty($title)) {
            $len = mb_strlen($title, 'UTF-8');
            if ($len > 15) {
                $keywords[] = mb_substr($title, 0, 15, 'UTF-8');
            } else {
                $keywords[] = $title;
            }
        }

        if (!empty($content)) {
            $text = strip_tags($content);
            $text = preg_replace('/[\s\p{P}]+/u', ' ', $text);
            $text = trim($text);
            if (mb_strlen($text) > 10) {
                $segments = preg_split('/\s+/u', $text);
                if (is_array($segments)) {
                    $keywords = array_merge($keywords, array_slice($segments, 0, 3));
                }
            }
        }

        $keywords = array_slice(array_unique(array_filter($keywords)), 0, 5);
        return implode(',', $keywords);
    }

    /**
     * AI 生成文章描述
     * @param string $title 文章标题
     * @param string $content 文章内容
     * @return string 120字以内的描述
     */
    public static function generateDec($title, $content = '')
    {
        if (!self::isChatAvailable()) {
            return self::generateDecFallback($title, $content);
        }

        $contentText = strip_tags($content);
        if (mb_strlen($contentText) > 800) {
            $contentText = mb_substr($contentText, 0, 800);
        }

        $prompt = "请为以下文章生成一段简短的SEO描述（不超过120字），要求准确概括文章主题，吸引读者点击，只输出描述文本：\n\n标题：{$title}";
        if (!empty($contentText)) {
            $prompt .= "\n\n内容：{$contentText}";
        }

        $systemPrompt = '你是一个SEO专家，擅长撰写吸引人的网页描述。';
        $result = self::chat($prompt, $systemPrompt, false);

        if (self::isErrorResult($result)) {
            return self::generateDecFallback($title, $content);
        }

        $result = trim($result);
        $result = preg_replace('/^[^\p{Han}a-zA-Z0-9]+|[^\p{Han}a-zA-Z0-9]+$/u', '', $result);

        if (mb_strlen($result) > 120) {
            $result = rtrim(mb_substr($result, 0, 118, 'UTF-8'), '，,。.!！？?…') . '…';
        }

        return $result;
    }

    /**
     * 本地规则生成描述（AI 不可用时的降级方案）
     */
    private static function generateDecFallback($title, $content = '')
    {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if (empty($text)) {
            return $title;
        }

        if (mb_strlen($text) > 120) {
            return rtrim(mb_substr($text, 0, 118, 'UTF-8'), '，,。.!！？?…') . '…';
        }

        return $text;
    }

    /**
     * AI 匹配商品
     * 使用 AI 分析文章内容提取商品关键词，然后通过 TJK 搜索匹配商品
     * @param string $title 文章标题
     * @param string $content 文章内容
     * @param string $platform 平台 taobao|pdd|jd|vip
     * @return array
     */
    public static function matchGoodsByAi($title, $content = '', $platform = 'taobao')
    {
        $keyword = '';

        if (self::isChatAvailable()) {
            $contentText = strip_tags($content);
            if (mb_strlen($contentText) > 500) {
                $contentText = mb_substr($contentText, 0, 500);
            }

            $prompt = "请根据以下文章标题和内容，提取一个最适合搜索商品的关键词（如商品名称、品牌、品类等），只输出关键词，不要其他解释：\n\n标题：{$title}";
            if (!empty($contentText)) {
                $prompt .= "\n\n内容：{$contentText}";
            }

            $systemPrompt = '你是一个电商选品专家，擅长根据文章内容提取精准的商品搜索关键词。';
            $aiResult = self::chat($prompt, $systemPrompt, false);

            if (!self::isErrorResult($aiResult)) {
                $keyword = trim($aiResult);
                $keyword = preg_replace('/^[^\p{Han}a-zA-Z0-9]+|[^\p{Han}a-zA-Z0-9]+$/u', '', $keyword);
                $keyword = preg_replace('/\s+/u', ' ', $keyword);
            }
        }

        if (empty($keyword)) {
            $keyword = self::extractSearchKeywordFallback($title, $content);
        }

        if (empty($keyword)) {
            return array('code' => 0, 'message' => '无法提取有效关键词', 'keyword' => '', 'items' => array());
        }

        $tjk = new \ZhiCms\ext\Tjk();
        $result = $tjk->searchGoods($keyword, $platform, 1, 10);

        $result['keyword'] = $keyword;
        return $result;
    }

    /**
     * 本地规则提取搜索关键词
     */
    private static function extractSearchKeywordFallback($title, $content = '')
    {
        $text = trim($title);
        if (empty($text) && !empty($content)) {
            $text = strip_tags($content);
        }

        $text = preg_replace('/[^\p{Han}a-zA-Z0-9\s]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) > 20) {
            $text = mb_substr($text, 0, 20, 'UTF-8');
        }

        return $text;
    }
}
