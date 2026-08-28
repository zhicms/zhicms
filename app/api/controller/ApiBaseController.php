<?php
namespace app\api\controller;

use ZhiCms\base\Controller;

/**
 * API 基类
 * 提供 JSON 输出、CORS、原始参数读取、JSON 请求体解析等通用能力。
 * 注意：直接继承 \ZhiCms\base\Controller，不触发前台站点关闭拦截与 Session。
 */
class ApiBaseController extends Controller {

    /**
     * 输出 JSON 并结束请求
     */
    protected function json($data, $httpCode = 200) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: application/json; charset=utf-8');
        if ($httpCode != 200) {
            http_response_code($httpCode);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 处理 CORS 预检请求
     */
    protected function options() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('HTTP/1.1 204 No Content');
            exit;
        }
    }

    /**
     * 将 OpenAI 格式 messages 数组折叠为单条 prompt（取最后一条 user 消息），
     * 供 AiService::chat 使用（AiService 接受字符串 prompt）。
     * @param array $messages
     * @return string
     */
    protected function messagesToPrompt($messages) {
        if (!is_array($messages) || empty($messages)) {
            return '';
        }
        // 倒序找第一条 user 消息内容作为主 prompt
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                return is_array($messages[$i]['content'])
                    ? json_encode($messages[$i]['content'], JSON_UNESCAPED_UNICODE)
                    : (string)$messages[$i]['content'];
            }
        }
        $last = end($messages);
        return is_array($last['content'] ?? null)
            ? json_encode($last['content'], JSON_UNESCAPED_UNICODE)
            : (string)($last['content'] ?? '');
    }

    /**
     * 将 AiService::chat 的字符串回复包装为 OpenAI 兼容格式输出（非流式）。
     * @param string|array $reply
     */
    protected function outputOpenAiCompat($reply) {
        if (is_array($reply) && isset($reply['error'])) {
            $this->json(array('error' => array('message' => $reply['error'])), 502);
        }
        if (empty($reply)
            || strpos((string)$reply, '大模型') === 0
            || strpos((string)$reply, 'AI 模型') === 0
            || strpos((string)$reply, '大模型API错误') === 0
            || strpos((string)$reply, 'CURL错误') === 0
            || strpos((string)$reply, 'HTTP错误') === 0) {
            $this->json(array('error' => array('message' => 'AI 对话失败：' . $reply)), 502);
        }
        $this->json(array(
            'choices' => array(array(
                'message'      => array('role' => 'assistant', 'content' => (string)$reply),
                'index'        => 0,
                'finish_reason' => 'stop',
            )),
            'usage'   => new \stdClass(),
        ), 200);
    }

    /**
     * 读取原始 GET+POST 参数（不做 htmlspecialchars，避免破坏消息体）
     */
    protected function raw($name = null, $default = null) {
        $args = array_merge((array) $_GET, (array) $_POST);
        if ($name === null) {
            return $args;
        }
        return $args[$name] ?? $default;
    }

    /**
     * 读取 JSON 请求体
     */
    protected function body() {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $dec = json_decode($raw, true);
        return is_array($dec) ? $dec : [];
    }

    /**
     * 当前站点根 URL（含结尾 /）
     */
    protected function siteUrl() {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $scriptDir = rtrim(dirname($scriptName), '/\\');
        // 兜底：CLI 等环境 SCRIPT_NAME 无前导斜杠时 dirname 返回 '.'，需归一为空
        if ($scriptDir === '' || $scriptDir === '.') {
            $scriptDir = '';
        }
        return $scheme . $host . $scriptDir . '/';
    }

    /**
     * 加载 AI 配置（不下发 api_key 之外的内容由调用方控制）
     */
    protected function loadAiConfig() {
        // 文件默认值（含 api_url / provider / model 等静态字段）
        $file = \CONFIG_PATH . 'aichat.php';
        $fileCfg = array();
        if (file_exists($file)) {
            $cfg = include $file;
            if (is_array($cfg)) {
                $fileCfg = $cfg;
            }
        }
        // 数据库优先：后台「开启 AI 对话导购」开关保存在 config 表，
        // 必须以 DB 覆盖文件默认值，否则后台开关不生效。
        $dbCfg = \app\common\ConfigStore::load('aichat');
        if (!is_array($dbCfg)) {
            $dbCfg = array();
        }
        $merged = array_merge($fileCfg, $dbCfg);
        // 安全：绝不向下发服务商 api_key
        unset($merged['api_key']);
        return $merged;
    }

    /**
     * 从 Authorization: Bearer <token> 或 ?token= 获取访问令牌
     */
    protected function requestToken() {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($auth !== '' && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            return trim($m[1]);
        }
        return $this->raw('token', '');
    }
}
