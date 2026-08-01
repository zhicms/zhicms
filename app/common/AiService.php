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
        $configFile = CONFIG_PATH . 'ai.php';
        if (!file_exists($configFile)) {
            self::$config = array('ai_chat' => '', 'ai_image' => '', 'ai_system_prompt' => '', 'ai_models' => array());
            return self::$config;
        }
        include $configFile;
        self::$config = isset($AI) ? $AI : array('ai_chat' => '', 'ai_image' => '', 'ai_system_prompt' => '', 'ai_models' => array());
        return self::$config;
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
        $of = fopen(CONFIG_PATH . 'ai.php', 'w');
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
     * 发送非流式 API 请求
     */
    private static function sendRequest($messages)
    {
        $modelInfo = self::getChatModelInfo();
        if (!$modelInfo) {
            return json_encode(array('error' => 'AI 模型未配置'));
        }

        $postData = json_encode(array(
            'messages'    => $messages,
            'model'       => $modelInfo['model'],
            'max_tokens'  => 4096,
            'stream'      => false,
            'temperature' => 1,
        ), JSON_UNESCAPED_UNICODE);

        return self::curlRequest($modelInfo['api_url'], $modelInfo['api_key'], $postData, 60);
    }

    /**
     * 发送流式 API 请求并直接输出 SSE
     */
    private static function sendStreamRequest($messages)
    {
        $modelInfo = self::getChatModelInfo();
        if (!$modelInfo) {
            echo "data: " . json_encode(array("error" => "AI 模型未配置"), JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "data: [DONE]\n\n";
            return '';
        }

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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION  => function ($curl, $data) use (&$fullResponse) {
                echo $data;
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                // 解析数据流中 content 部分
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
        ));

        curl_exec($ch);
        if (curl_errno($ch)) {
            echo "data: [ERROR] " . curl_error($ch) . "\n\n";
        }
        curl_close($ch);

        return $fullResponse;
    }

    /**
     * 通用 cURL 请求
     */
    private static function curlRequest($url, $apiKey, $postData, $timeout = 60)
    {
        // curl 扩展缺失：返回结构化错误，交由上层降级/提示，避免 PHP 8 下抛 \Error 导致白屏
        if (!extension_loaded('curl')) {
            return json_encode(array('error' => 'curl 扩展未安装，AI 功能不可用。请在 php.ini 中启用 curl 扩展。'));
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => $timeout,
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return 'CURL错误: ' . $error;
        }
        if ($httpCode !== 200) {
            return 'HTTP错误: ' . $httpCode;
        }
        return $response;
    }

    /**
     * 解析 API 响应
     */
    private static function parseResponse($response)
    {
        // 内部错误：AI 模型未配置（sendRequest 返回的）
        if (strpos($response, 'AI 模型未配置') === 0) {
            return $response;
        }

        $data = json_decode($response, true);

        // API 返回错误（模型名错误、限流、网络错误等）
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

        // 正常响应
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        if (isset($data['choices'][0]['text'])) {
            return $data['choices'][0]['text'];
        }

        // 纯文本响应
        if (is_string($data) && !empty($data)) {
            return $data;
        }

        return '大模型处理异常，请稍后再试，错误信息：' . $response;
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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));

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
