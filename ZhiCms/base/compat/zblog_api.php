<?php
/**
 * Z-BlogPHP 兼容 API（全局函数 / 全局对象 / 常量）
 * 在加载 zblog 格式插件前由 ZblogBridge 引入一次。
 * ⚠️ 本文件必须最先被 require，以确保 ZBP_PATH 等常量在插件加载前已定义。
 */

// ======================== 核心防护常量（必须在最顶部） ========================

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', BASE_PATH);
}

if (!defined('ZBP_HOOKERROR')) {
    define('ZBP_HOOKERROR', true);
}

if (!defined('ZBP_SAFEMODE')) {
    define('ZBP_SAFEMODE', false);
}

// ======================== 全局 $zbp 对象 ========================

global $zbp;
if (!isset($zbp)) {
    $zbp = new \ZhiCms\base\compat\ZbpShim();
}

// ======================== 插件注册 ========================

if (!function_exists('RegisterPlugin')) {
    /**
     * 注册插件（zblog 风格）
     * @param string $id      插件 id（目录名）
     * @param string $active  激活函数名（如 ActivePlugin_Totoro）
     */
    function RegisterPlugin($id, $active)
    {
        $GLOBALS['_zbp_active'][$id] = $active;
        if (function_exists($active)) {
            $active();
        }
    }
}

if (!function_exists('InstallPlugin')) {
    function InstallPlugin($id, $func)
    {
        $GLOBALS['_zbp_install'][$id] = $func;
        if (function_exists($func)) {
            $func();
        }
    }
}

if (!function_exists('UninstallPlugin')) {
    function UninstallPlugin($id, $func)
    {
        $GLOBALS['_zbp_uninstall'][$id] = $func;
    }
}

// ======================== 钩子系统 ========================

if (!function_exists('Add_Filter_Plugin')) {
    /**
     * 挂载接口（zblog 风格）
     * @param string $interface 接口名（如 Filter_Plugin_Admin_Header）
     * @param string $func      回调函数名
     * @param int    $priority  优先级
     */
    function Add_Filter_Plugin($interface, $func, $priority = 50)
    {
        $mapped = \ZhiCms\base\compat\ZblogBridge::mapInterface($interface);
        \ZhiCms\base\Hook::add($mapped, $func, (int)$priority);
    }
}

if (!function_exists('Remove_Filter_Plugin')) {
    function Remove_Filter_Plugin($interface, $func)
    {
        // no-op for compatibility
    }
}

// ======================== 请求工具 ========================

if (!function_exists('GetVars')) {
    /** 获取请求变量（兼容 zblog GetVars） */
    function GetVars($name, $type = 'REQUEST', $default = null)
    {
        $type = strtoupper($type);
        switch ($type) {
            case 'GET':    $src =& $_GET;    break;
            case 'POST':   $src =& $_POST;   break;
            case 'COOKIE': $src =& $_COOKIE; break;
            case 'SERVER': $src =& $_SERVER; break;
            default:       $src =& $_REQUEST;
        }
        if ($name === null) return $src;
        return $src[$name] ?? $default;
    }
}

if (!function_exists('GetVarsByDefault')) {
    function GetVarsByDefault($name, $type = 'REQUEST', $default = null)
    {
        $v = GetVars($name, $type);
        return $v !== null ? $v : $default;
    }
}

// ======================== 输出工具 ========================

if (!function_exists('JsonReturn')) {
    function JsonReturn($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('CloseTags')) {
    function CloseTags($html) { return preg_replace('/>\s+</', '><', trim($html)); }
}

if (!function_exists('ReturnJson')) {
    function ReturnJson($data) { JsonReturn($data); }
}

if (!function_exists('ShowMessage')) {
    function ShowMessage($x) { echo '<div class="zbp-message">' . htmlspecialchars($x) . '</div>'; }
}

// ======================== 字符串/安全工具 ========================

if (!function_exists('TransferHTML')) {
    function TransferHTML($s, $type = 'htmlencode') { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('SubStrUtf8_Start')) {
    function SubStrUtf8_Start($str, $len) { return mb_substr($str, 0, $len, 'UTF-8'); }
}

if (!function_exists('SubStrUtf8')) {
    function SubStrUtf8($str, $len) { return mb_substr($str, 0, $len, 'UTF-8'); }
}

if (!function_exists('FormatString')) {
    function FormatString($s, $type = '') { return $s; }
}

if (!function_exists('CheckIsRefererValid')) {
    function CheckIsRefererValid() { return true; }
}

// ======================== 文件系统工具 ========================

if (!function_exists('GetFileExt')) {
    function GetFileExt($filename) { return strtolower(pathinfo($filename, PATHINFO_EXTENSION)); }
}

if (!function_exists('Zbp_CreateDir')) {
    function Zbp_CreateDir($dir) {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return is_dir($dir);
    }
}

if (!function_exists('GetFileInDir')) {
    function GetFileInDir($dir, $type = null) {
        $files = array();
        if (!is_dir($dir)) return $files;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $files[] = $f;
        }
        return $files;
    }
}

// ======================== URL 工具 ========================

if (!function_exists('BuildSafeURL')) {
    function BuildSafeURL($str) {
        $url = parse_url($str);
        return ($url['scheme'] ?? '') . '://' . ($url['host'] ?? '') . ($url['path'] ?? '/') . (isset($url['query']) ? '?' . $url['query'] : '');
    }
}

if (!function_exists('BuildSafeCmdURL')) {
    function BuildSafeCmdURL($str) { return htmlspecialchars($str); }
}

if (!function_exists('Redirect301')) {
    function Redirect301($url) {
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: $url");
        exit;
    }
}

if (!function_exists('Redirect302')) {
    function Redirect302($url) {
        header("Location: $url");
        exit;
    }
}

if (!function_exists('Redirect')) {
    /**
     * Z-Blog 标准重定向（set hint + redirect）
     * 在 ZhiCms 环境下，Hint 通过 session 传递
     */
    function Redirect($url) {
        if (!headers_sent()) {
            header("Location: $url");
        }
        exit;
    }
}

// ======================== 其他常用函数 ========================

if (!function_exists('GetScheme')) {
    function GetScheme($url) { return parse_url($url, PHP_URL_SCHEME) ?: 'http'; }
}

if (!function_exists('GetHost')) {
    function GetHost($url) { return parse_url($url, PHP_URL_HOST); }
}

if (!function_exists('CountArray')) {
    function CountArray($array) { return is_array($array) ? count($array) : 0; }
}

if (!function_exists('GetValueInArray')) {
    function GetValueInArray($array, $key, $default = null) { return $array[$key] ?? $default; }
}

if (!function_exists('GetValueInArrayByCurrent')) {
    function GetValueInArrayByCurrent($array, $key, $type = 'string') { return $array[$key] ?? ''; }
}

if (!function_exists('RunTime')) {
    function RunTime() {}
}

if (!function_exists('VerifyLogin')) {
    function VerifyLogin() { return true; }
}

// ======================== Network 网络组件垫片 ========================

if (!class_exists('Network')) {
    /**
     * Z-BlogPHP Network 类垫片
     * 提供 Create() 工厂方法和基本的 HTTP 请求能力
     */
    class Network
    {
        private $url;
        private $method;
        private $timeout = 30;
        private $connectTimeout = 10;
        private $headers = array();
        private $body;
        private $rawResponse;
        private $responseHeaders = array();
        private $statusCode = 0;
        private $gzip = false;

        // Z-Blog 兼容：AiBase 等插件通过 ->responseText / ->status / ->errno / ->errstr 访问
        public $responseText = '';
        public $status = 0;
        public $errno = 0;
        public $errstr = '';

        public static function Create()
        {
            if (function_exists('curl_init') || ini_get('allow_url_fopen')) {
                return new self();
            }
            return null;
        }

        public function open($method, $url)
        {
            $this->method = strtoupper($method);
            $this->url = $url;
        }

        public function setTimeOuts($connectTimeout, $readTimeout, $sendTimeout = 30, $totalTimeout = 30)
        {
            $this->connectTimeout = (int)$connectTimeout;
            $this->timeout = (int)$totalTimeout;
        }

        public function enableGzip()
        {
            $this->gzip = true;
        }

        public function setRequestHeader($name, $value)
        {
            $this->headers[$name] = $value;
        }

        public function send($body = '')
        {
            $this->body = $body;
            if (function_exists('curl_init')) {
                return $this->sendViaCurl();
            }
            return $this->sendViaStream();
        }

        private function sendViaCurl()
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            curl_setopt($ch, CURLOPT_HEADER, true);

            $curlHeaders = array();
            foreach ($this->headers as $k => $v) {
                $curlHeaders[] = "$k: $v";
            }

            if ($this->method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
            }

            if ($this->gzip) {
                curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
            }

            if (!empty($curlHeaders)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            }

            $response = curl_exec($ch);
            if ($response === false) {
                throw new \Exception('Network Error: ' . curl_error($ch));
            }

            $this->statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $this->rawResponse = substr($response, $headerSize);
            $this->responseText = $this->rawResponse;
            $this->status = $this->statusCode;
            $this->errno = curl_errno($ch);
            $this->errstr = curl_error($ch);
            curl_close($ch);

            return $this->rawResponse;
        }

        private function sendViaStream()
        {
            $opts = array(
                'http' => array(
                    'method'  => $this->method,
                    'timeout' => $this->timeout,
                    'header'  => '',
                ),
            );

            $hdr = '';
            foreach ($this->headers as $k => $v) {
                $hdr .= "$k: $v\r\n";
            }
            $opts['http']['header'] = $hdr;

            if ($this->method === 'POST' && !empty($this->body)) {
                $opts['http']['content'] = $this->body;
            }

            $context = stream_context_create($opts);
            $this->rawResponse = @file_get_contents($this->url, false, $context);
            if ($this->rawResponse === false) {
                $this->errno = 1;
                $this->errstr = 'Network Error: file_get_contents failed';
                throw new \Exception('Network Error: file_get_contents failed');
            }
            $this->responseText = $this->rawResponse;
            $this->status = 200;
            $this->errno = 0;
            $this->errstr = '';
            return $this->rawResponse;
        }

        public function getResponse()
        {
            return $this->rawResponse;
        }
    }
}

// ======================== 菜单工具 ========================

if (!function_exists('MakeTopMenu')) {
    /**
     * Z-BlogPHP 顶部菜单项构造器桩
     * @param string $parent    父级 ID（'root' 表示顶层）
     * @param string $title     菜单标题
     * @param string $url       链接地址
     * @param string $icon      图标类名
     * @param string $id        菜单项 ID
     * @param string $target    目标属性
     * @return string           菜单项 HTML（兼容 ZhiCms 时返回空串）
     */
    function MakeTopMenu($parent = '', $title = '', $url = '', $icon = '', $id = '', $target = '')
    {
        return '';
    }
}
