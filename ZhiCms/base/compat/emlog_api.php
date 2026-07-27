<?php
/**
 * Emlog 兼容 API（全局函数 / 全局类）
 * 在加载 emlog 格式插件前由 EmlogBridge 引入一次。
 * 这些全局符号会把 emlog 插件对宿主的调用"翻译"为 ZhiCms 的 Hook 系统。
 *
 * ⚠️ 本文件必须最先被 require，以确保 EMLOG_ROOT 等常量在插件加载前已定义。
 */

// ======================== 核心防护常量（必须在最顶部） ========================

if (!defined('EMLOG_ROOT')) {
    define('EMLOG_ROOT', BASE_PATH);
}

if (!defined('DB_PREFIX')) {
    $__dbConf = \ZhiCms\base\Config::get('DB.default');
    define('DB_PREFIX', (!empty($__dbConf['DB_PREFIX']) ? $__dbConf['DB_PREFIX'] : 'yun_'));
}

// ======================== 钩子系统 ========================

if (!function_exists('addAction')) {
    /**
     * 注册钩子（emlog 风格）
     * @param string $hook     emlog 挂载点名（如 index_head）
     * @param string $func     回调函数名
     * @param int    $priority 优先级
     */
    function addAction($hook, $func, $priority = 10)
    {
        $mapped = \ZhiCms\base\compat\EmlogBridge::mapHook($hook);
        \ZhiCms\base\Hook::add($mapped, $func, (int)$priority);
    }
}

if (!function_exists('doAction')) {
    /** 触发钩子，返回最后一个监听器结果 */
    function doAction($hook, ...$args)
    {
        $mapped = \ZhiCms\base\compat\EmlogBridge::mapHook($hook);
        $r = null;
        \ZhiCms\base\Hook::listen($mapped, $args, $r);
        return $r;
    }
}

if (!function_exists('doOnceAction')) {
    /**
     * 单次接管式挂载：仅执行首个监听器，并以引用方式改写变量。
     * 对应 emlog 的 doOnceAction('upload_media', $attach, $ret)
     */
    function doOnceAction($hook, &$a = null, &$b = null)
    {
        $mapped = \ZhiCms\base\compat\EmlogBridge::mapHook($hook);
        $cb = \ZhiCms\base\Hook::firstListener($mapped);
        if ($cb === null) return;
        $args = array();
        if ($a !== null) $args[] =& $a;
        if ($b !== null) $args[] =& $b;
        call_user_func_array(\ZhiCms\base\Hook::normalize($cb), $args);
    }
}

if (!function_exists('doMultiAction')) {
    /**
     * 轮流接管式挂载：前一个输出作为下一个输入（过滤器）。
     * 对应 emlog 的 doMultiAction('article_content_echo', $content, $content)
     */
    function doMultiAction($hook, &$value, $value2 = null)
    {
        $mapped = \ZhiCms\base\compat\EmlogBridge::mapHook($hook);
        $params = ($value2 === null) ? array() : array($value2);
        $value = \ZhiCms\base\Hook::filter($mapped, $value, $params);
    }
}

// ======================== Storage 键值存储 ========================

if (!class_exists('Storage')) {
    /**
     * 键值对存储（兼容 emlog Storage 类），底层复用插件配置 JSON。
     */
    class Storage
    {
        protected $plugin;

        public static function getInstance($plugin)
        {
            return new self($plugin);
        }

        public function __construct($plugin)
        {
            $this->plugin = $plugin;
        }

        protected function storeKey()
        {
            return '_storage_' . $this->plugin;
        }

        public function setValue($key, $value, $type = 'string')
        {
            $cfg = \ZhiCms\base\PluginManager::getConfig($this->plugin);
            $cfg[$this->storeKey()][$key] = array('type' => $type, 'value' => $value);
            \ZhiCms\base\PluginManager::setConfig($this->plugin, $cfg);
        }

        public function getValue($key)
        {
            $cfg = \ZhiCms\base\PluginManager::getConfig($this->plugin);
            $st = $cfg[$this->storeKey()][$key] ?? null;
            return $st['value'] ?? '';
        }

        public function deleteName($key)
        {
            $cfg = \ZhiCms\base\PluginManager::getConfig($this->plugin);
            unset($cfg[$this->storeKey()][$key]);
            \ZhiCms\base\PluginManager::setConfig($this->plugin, $cfg);
        }

        public function deleteAllName($yes = '')
        {
            if ($yes !== 'YES') return;
            $cfg = \ZhiCms\base\PluginManager::getConfig($this->plugin);
            foreach ($cfg as $k => $v) {
                if (strpos($k, '_storage_') === 0) unset($cfg[$k]);
            }
            \ZhiCms\base\PluginManager::setConfig($this->plugin, $cfg);
        }
    }
}

// ======================== 数据库访问 ========================

if (!class_exists('MySql')) {
    /**
     * 数据库访问（兼容 emlog MySql / Database 单例）
     */
    class MySql
    {
        public static function getInstance()
        {
            return new self();
        }

        public function query($sql, $params = array())
        {
            return obj('api/ApiData')->query($sql, $params);
        }

        public function execute($sql, $params = array())
        {
            return obj('api/ApiData')->execute($sql, $params);
        }
    }
    if (!class_exists('Database')) {
        class Database extends MySql
        {
        }
    }
}

// ======================== 常用工具函数 ========================

if (!function_exists('em_rand')) {
    function em_rand($min, $max) { return rand($min, $max); }
}

if (!function_exists('getRandStr')) {
    function getRandStr($length = 32) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) $str .= $chars[rand(0, strlen($chars) - 1)];
        return $str;
    }
}

if (!function_exists('getFileSuffix')) {
    function getFileSuffix($fileName) { return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)); }
}

if (!function_exists('grabImage')) {
    function grabImage($url, $filename = '') {
        return '';
    }
}

if (!function_exists('uploadFile')) {
    function uploadFile($fileName, $errorNum, $tmpFile, $fileSize, $ext, $attach = array()) {
        return array();
    }
}

if (!function_exists('_langPlu')) {
    function _langPlu($key, $plugin) { return array(); }
}

if (!function_exists('_g')) {
    function _g($key) {
        return GetVars($key, 'GET');
    }
}

if (!function_exists('GetVars')) {
    function GetVars($name, $type = 'REQUEST') {
        $type = strtoupper($type);
        switch ($type) {
            case 'GET':    $src =& $_GET;    break;
            case 'POST':   $src =& $_POST;   break;
            case 'COOKIE': $src =& $_COOKIE; break;
            case 'SERVER': $src =& $_SERVER; break;
            default:       $src =& $_REQUEST;
        }
        return $src[$name] ?? null;
    }
}

if (!function_exists('URL')) {
    function URL() { return defined('ROOT_URL') ? ROOT_URL : '/'; }
}

if (!function_exists('BLOG_URL')) {
    function BLOG_URL() { return defined('ROOT_URL') ? ROOT_URL : '/'; }
}

if (!function_exists('TEMPLATE_URL')) {
    function TEMPLATE_URL() { return defined('ROOT_URL') ? ROOT_URL . 'public/' : '/public/'; }
}

if (!function_exists('DYNAMIC_BLOGURL')) {
    function DYNAMIC_BLOGURL() { return defined('ROOT_URL') ? ROOT_URL : '/'; }
}

if (!function_exists('emMsg')) {
    function emMsg($msg, $url = '') {
        if ($url) {
            header("Location: $url");
            exit;
        }
        echo $msg;
        exit;
    }
}

if (!function_exists('showJson')) {
    function showJson($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('emDirect')) {
    function emDirect($directUrl) {
        header("Location: $directUrl");
        exit;
    }
}

// ======================== Input 输入过滤 ========================

if (!class_exists('Input')) {
    class Input
    {
        public static function getStrVar($k) { return isset($_REQUEST[$k]) ? addslashes((string)$_REQUEST[$k]) : ''; }
        public static function getIntVar($k) { return isset($_REQUEST[$k]) ? (int)$_REQUEST[$k] : 0; }
        public static function getVar($k) { return $_REQUEST[$k] ?? ''; }
        public static function postStrVar($k) { return isset($_POST[$k]) ? addslashes((string)$_POST[$k]) : ''; }
        public static function postIntVar($k) { return isset($_POST[$k]) ? (int)$_POST[$k] : 0; }
    }
}

// ======================== Option 工具 ========================

if (!function_exists('getOption')) {
    function getOption($name) { return \ZhiCms\base\Config::get($name); }
}

if (!function_exists('updateOption')) {
    function updateOption($name, $value) { return true; }
}

// ======================== Option 类 ========================

if (!class_exists('Option')) {
    class Option
    {
        public static function get($key) { return \ZhiCms\base\Config::get($key); }
        public static function getAll() { return array(); }
        public static function update($key, $value) { return true; }
        public static function getRoles() { return array(ROLE_ADMIN, ROLE_WRITER, ROLE_VISITOR); }
        public static function getLibOptions() { return array(); }
    }
}

// ======================== User 模型 ========================

if (!class_exists('User')) {
    class User
    {
        public static function isAdmin() { return !empty($_SESSION['manage_system']); }
        public static function isLogin() { return !empty($_SESSION['user']) || !empty($_SESSION['manage_system']); }
        public static function getRoleByUid($uid) { return empty($_SESSION['manage_system']) ? ROLE_WRITER : ROLE_ADMIN; }
    }
}

// ======================== 角色常量 ========================

if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 'admin');
}
if (!defined('ROLE_WRITER')) {
    define('ROLE_WRITER', 'writer');
}
if (!defined('ROLE_VISITOR')) {
    define('ROLE_VISITOR', 'visitor');
}
if (!defined('ROLE_EDITOR')) {
    define('ROLE_EDITOR', 'editor');
}

// ======================== Log 模型 ========================

if (!class_exists('LogModel')) {
    class LogModel
    {
        public static function getLogNum() { return 0; }
        public static function getLogsForPage($page = 1, $perPage = 10) { return array(); }
    }
}

// ======================== Navi 模型 ========================

if (!class_exists('Navi')) {
    class Navi
    {
        public static function getNavi() { return array(); }
    }
}

// ======================== 页面工具 ========================

if (!class_exists('Page')) {
    class Page
    {
        public static function getPages() { return array(); }
    }
}

if (!class_exists('Widget')) {
    class Widget
    {
        public static function getWidgets() { return array(); }
    }
}

// ======================== 杂项 ========================

if (!function_exists('user_can')) {
    function user_can($uid, $action) {
        return !empty($_SESSION['manage_system']);
    }
}

if (!function_exists('checkAdmin')) {
    function checkAdmin() { return !empty($_SESSION['manage_system']); }
}

if (!function_exists('register_nav_menu')) {
    function register_nav_menu($location, $description) {}
}

if (!function_exists('register_sidebar')) {
    function register_sidebar($args = array()) {}
}

if (!function_exists('has_nav_menu')) {
    function has_nav_menu($location) { return false; }
}
