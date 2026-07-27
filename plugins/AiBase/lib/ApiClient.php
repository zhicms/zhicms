<?php
/**
 * 优杰AI连接器 - 大模型 API 客户端
 * @package AiBase
 */

class AiApiClient
{
    private $apiKey;
    private $baseUrl;
    private $model;
    private $timeout;

    /**
     * 构造函数
     * @param string $apiKey
     * @param string $baseUrl
     * @param string $model
     * @param int $timeout
     */
    public function __construct($apiKey, $baseUrl, $model = 'deepseek-chat', $timeout = 60)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->timeout = (int)$timeout;
    }

    /**
     * 发送非流式聊天对话请求
     * @param array $messages
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function chat($messages, $options = array())
    {
        $url = $this->baseUrl . '/chat/completions';
        
        $payload = array_merge(array(
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false
        ), $options);

        $ajax = Network::Create();
        if (!$ajax) {
            throw new Exception("服务器不支持 Z-Blog Network 网络连接组件！");
        }
        $ajax->open('POST', $url);
        $ajax->setTimeOuts($this->timeout, $this->timeout, $this->timeout, $this->timeout);
        $ajax->enableGzip();
        
        $ajax->setRequestHeader('Content-Type', 'application/json');
        if (!empty($this->apiKey)) {
            $ajax->setRequestHeader('Authorization', 'Bearer ' . $this->apiKey);
        }
        
        $ajax->send(json_encode($payload));
        $response = $ajax->responseText;
        $httpCode = $ajax->status;
        $errNo = $ajax->errno;
        $errMsg = $ajax->errstr;

        if ($errNo) {
            $friendly = self::getFriendlyError($errMsg, 0, $errNo);
            throw new Exception($friendly);
        }

        $result = json_decode($response, true);
        
        if ((int)$httpCode !== 200) {
            $apiError = isset($result['error']['message']) ? $result['error']['message'] : $response;
            $friendly = self::getFriendlyError(trim($apiError), $httpCode);
            throw new Exception($friendly);
        }

        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("大模型响应格式异常，未找到生成文本");
        }

        return $result;
    }

    /**
     * 发送流式聊天对话请求
     * @param array $messages
     * @param callable $callback 回调函数，每收到一个 Token 执行一次：function($token)
     * @param array $options
     * @return bool
     * @throws Exception
     */
    public function chatStream($messages, $callback, $options = array())
    {
        $url = $this->baseUrl . '/chat/completions';
        
        $payload = array_merge(array(
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true
        ), $options);

        $ajax = Network::Create('curl');
        if (!$ajax) {
            throw new Exception("服务器不支持 Z-Blog Network CURL 网络组件！");
        }
        $ajax->open('POST', $url);
        $ajax->setTimeOuts($this->timeout, $this->timeout, $this->timeout, $this->timeout);
        $ajax->enableGzip();

        $ajax->setRequestHeader('Content-Type', 'application/json');
        if (!empty($this->apiKey)) {
            $ajax->setRequestHeader('Authorization', 'Bearer ' . $this->apiKey);
        }

        $ch = $ajax->ch;

        $headers = array();
        foreach ($ajax->httpheader as $h) {
            $headers[] = $h;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

        // 收集未解析的流，应对非200错误响应
        $rawResponse = '';
        $buffer = '';

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer, &$rawResponse, $callback) {
            $rawResponse .= $data;
            $buffer .= $data;
            
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                
                if (empty($line)) {
                    continue;
                }
                
                if (strpos($line, 'data:') === 0) {
                    $rawJson = trim(substr($line, 5));
                    
                    if ($rawJson === '[DONE]') {
                        // 流式传输正常结束
                        call_user_func($callback, '[DONE]');
                        break;
                    }
                    
                    $decoded = json_decode($rawJson, true);
                    if ($decoded && isset($decoded['choices'][0]['delta']['content'])) {
                        $token = $decoded['choices'][0]['delta']['content'];
                        call_user_func($callback, $token);
                    }
                }
            }
            return strlen($data);
        });

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($ch);
        $errMsg = curl_error($ch);
        
        // PHP 8.0+ curl_close has no effect but we call it for compatibility
        if (function_exists('curl_close') && is_resource($ch)) {
            @curl_close($ch);
        }

        if ($errNo) {
            $friendly = self::getFriendlyError($errMsg, 0, $errNo);
            throw new Exception($friendly);
        }

        if ((int)$httpCode !== 200) {
            $body = $rawResponse;
            $headerEnd = strpos($body, "\r\n\r\n");
            if ($headerEnd !== false) {
                $body = substr($body, $headerEnd + 4);
            }
            $decoded = json_decode(trim($body), true);
            $apiError = isset($decoded['error']['message']) ? $decoded['error']['message'] : $body;
            $friendly = self::getFriendlyError(trim($apiError), $httpCode);
            throw new Exception($friendly);
        }

        return true;
    }

    /**
     * 将原始错误消息转换为用户易懂的中文友好提示
     * @param string $errorMsg 原始错误消息
     * @param int $httpCode HTTP 状态码
     * @param int $curlErrNo cURL 错误号
     * @return string
     */
    public static function getFriendlyError($errorMsg, $httpCode = 0, $curlErrNo = 0)
    {
        // 1. 处理 cURL 网络连接错误
        if ($curlErrNo > 0) {
            switch ($curlErrNo) {
                case 6: // CURLE_COULDNT_RESOLVE_HOST
                    return "网络解析域名失败：无法解析大模型服务商的域名。请检查您的服务器网络 DNS 配置，或者确认“接口地址 (Base URL)”拼写是否正确。";
                case 7: // CURLE_COULDNT_CONNECT
                    return "网络连接服务器失败：无法连接到大模型服务器。可能服务商节点异常、您的服务器防火墙阻止了外网连接，或者是您的服务器需要配置代理才能访问海外服务。";
                case 28: // CURLE_OPERATION_TIMEDOUT
                    return "网络连接超时：大模型请求响应超时。由于国内服务器直连海外接口常有网络波动，建议您在【高级设置】中调大“连接超时时间”，或更换为国内节点/中转加速地址。";
                case 35: // CURLE_SSL_CONNECT_ERROR
                    return "SSL 连接安全通道失败：与服务商建立安全连接 (SSL) 失败。可能您服务器的 OpenSSL 组件版本较旧，无法适配最新的安全协议，请升级服务器环境或改用 HTTP 接口。";
                default:
                    return "网络连接失败 (cURL 错误码 {$curlErrNo})：{$errorMsg}。请检查服务器网络状态与代理设置。";
            }
        }

        // 2. 如果包含常见的 cURL 报错文本特征，进行归纳
        $lowerMsg = strtolower($errorMsg);
        if (strpos($lowerMsg, 'resolve host') !== false || strpos($lowerMsg, 'could not resolve') !== false) {
            return "网络解析域名失败：无法解析大模型接口域名，请确认“接口地址 (Base URL)”是否填写正确。";
        }
        if (strpos($lowerMsg, 'timed out') !== false || strpos($lowerMsg, 'timeout') !== false) {
            return "网络连接超时：请求超时，请在【高级设置】中调大“连接超时时间”，或改用响应速度更快的国内加速通道。";
        }
        if (strpos($lowerMsg, 'ssl') !== false || strpos($lowerMsg, 'handshake') !== false) {
            return "SSL 连接安全通道失败：SSL 安全通道握手失败，建议检查服务器 OpenSSL 版本或尝试改用 HTTP 接口。";
        }

        // 2.5 处理常见的中转、网关或代理服务器的纯文本/HTML 报错（即便 HTTP Code 是 200 也会尝试拦截）
        if (strpos($lowerMsg, 'service unavailable') !== false) {
            return "服务商接口暂时不可用 (Service Unavailable)。这通常是由于接口地址 (Base URL) 填写错误、中转代理服务器宕机、或官方服务临时维护导致的。请核对接口地址或联系您的服务商。";
        }
        if (strpos($lowerMsg, 'gateway timeout') !== false || strpos($lowerMsg, '504 gateway') !== false) {
            return "网关超时 (Gateway Timeout)。可能由于中转节点网络超时或服务商接口无响应，请稍后再试。";
        }
        if (strpos($lowerMsg, 'bad gateway') !== false || strpos($lowerMsg, '502 bad') !== false) {
            return "糟糕的网关 (Bad Gateway)。通常是中转服务商宕机或无法连接到官方上游服务器，请联系您的中转服务商。";
        }

        // 3. 处理 HTTP 状态码错误
        if ($httpCode > 0 && $httpCode !== 200) {
            // 先解析 API 返回的 JSON 报错关键字
            if (strpos($lowerMsg, 'insufficient balance') !== false || strpos($lowerMsg, 'insufficient_balance') !== false || strpos($lowerMsg, 'credit') !== false || strpos($lowerMsg, 'exceeded quota') !== false || strpos($lowerMsg, 'quota') !== false) {
                return "余额不足或超限：大模型账户额度已耗尽或已欠费。请登录服务商后台检查账户余额，并及时充值。";
            }
            if (strpos($lowerMsg, 'invalid api') !== false || strpos($lowerMsg, 'api key') !== false || strpos($lowerMsg, 'unauthorized') !== false || strpos($lowerMsg, 'incorrect api key') !== false || $httpCode === 401) {
                return "API Key 错误：您的 API Key 无效或配置不正确。请重新确认填写的 API Key 是否有误（检查前后是否有空格或特殊字符）。";
            }
            if (strpos($lowerMsg, 'model_not_found') !== false || strpos($lowerMsg, 'model not found') !== false || strpos($lowerMsg, 'does not exist') !== false || strpos($lowerMsg, 'unknown model') !== false) {
                return "模型未找到：指定的模型名称不支持或不存在。请点击“一键自动获取最新的模型列表”以选择正确的模型。";
            }
            if (strpos($lowerMsg, 'rate limit') !== false || strpos($lowerMsg, 'ratelimit') !== false || $httpCode === 429) {
                return "请求受限：已超出大模型接口的调用频次限制（RPM/TPM）或并发上限。请稍等片刻后再试，或前往后台升级 API 账号配额。";
            }
            if (strpos($lowerMsg, 'region') !== false || strpos($lowerMsg, 'not available') !== false || strpos($lowerMsg, 'geo') !== false || strpos($lowerMsg, 'location') !== false) {
                return "地区受限：服务商限制了您的服务器 IP 所在地区（如 OpenAI 限制国内 IP 直接访问）。请尝试使用国内中转接口或换用国内大模型平台（如阿里、百度、腾讯、智谱等）。";
            }
            if (strpos($lowerMsg, 'context length') !== false || strpos($lowerMsg, 'max tokens') !== false || strpos($lowerMsg, 'too long') !== false) {
                return "内容超限：提示词或生成的文本内容超出了该模型所能支持的最大上下文窗口限制，请精简输入内容。";
            }

            // 根据 HTTP Code 兜底翻译
            switch ($httpCode) {
                case 301:
                case 302:
                case 307:
                case 308:
                    return "接口被重定向 (HTTP {$httpCode})：请检查您的“接口地址 (Base URL)”是否有多填了 /chat/completions 路径，或使用了不支持的 http 协议（有些服务商强制要求使用 https）。";
                case 401:
                    return "API Key 错误：API Key 无效或未授权。请确认您的 API Key 是否正确（注意不要包含多余空格）。";
                case 402:
                    return "余额不足或超限：账户余额不足或超出限制。请登录大模型服务商后台查看账单与额度是否充沛。";
                case 403:
                    return "访问被拒绝 (HTTP 403)：可能由于服务商限制了您的 IP 地区，或该 API Key 无权访问此模型。";
                case 404:
                    return "未找到服务路径或模型 (HTTP 404)：请确认您的“接口地址 (Base URL)”是否拼写正确（是否多了或漏了子路径）。";
                case 429:
                    return "请求受限 (HTTP 429)：触发频率或并发限制，请稍后重试或调高服务商限额。";
                case 500:
                case 502:
                case 503:
                case 504:
                    return "服务商服务器内部错误 (HTTP {$httpCode})：这通常是服务商接口宕机或临时维护引起的，请稍后再试或查看服务商官网状态页。";
                default:
                    return "服务商返回错误 (HTTP {$httpCode})：{$errorMsg}";
            }
        }

        // 4. 其他普通错误兜底
        return $errorMsg;
    }

    /**
     * 判断模型标识符是否是对话/文本生成模型 (过滤掉向量、绘图、语音、OCR 等模型)
     * @param string $modelId
     * @return bool
     */
    public static function isChatModel($modelId)
    {
        $id = strtolower($modelId);
        
        // 排除词列表
        $excludeKeywords = array(
            'embedding',
            'bge-',
            '-ocr',
            'paddleocr',
            'structure',
            'toytalk',
            '-tts',
            'whisper',
            'speech',
            'audio',
            'vocal',
            'rerank',
            'tao-8k',
            'image',
            'drawing',
            'paint',
            'diffusion',
            'flux.',
            'dall-e',
            'midjourney',
            'check-vl', // 百度安全检测
        );
        
        foreach ($excludeKeywords as $kw) {
            if (strpos($id, $kw) !== false) {
                return false;
            }
        }
        
        return true;
    }
}
