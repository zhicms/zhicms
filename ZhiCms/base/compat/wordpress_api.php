<?php
/**
 * WordPress 兼容 API（全局函数 / 常量）
 * 在加载 WordPress 格式插件前由 WordPressBridge 引入。
 * 这些全局符号会把 WordPress 插件对宿主的调用"翻译"为 ZhiCms 的 Hook 系统。
 *
 * ⚠️ 本文件必须最先被 require，以确保 ABSPATH 等常量在插件加载前已定义。
 */

// ======================== 核心防护常量（必须在最顶部） ========================

if (!defined('ABSPATH')) {
    define('ABSPATH', BASE_PATH);
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', BASE_PATH);
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', BASE_PATH . 'plugins/');
}

if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', defined('ROOT_URL') ? ROOT_URL : '/');
}

if (!defined('WP_PLUGIN_URL')) {
    define('WP_PLUGIN_URL', defined('ROOT_URL') ? ROOT_URL . 'plugins/' : '/plugins/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

if (!defined('WP_DEFAULT_THEME')) {
    define('WP_DEFAULT_THEME', 'default');
}

if (!defined('WP_MAX_MEMORY_LIMIT')) {
    define('WP_MAX_MEMORY_LIMIT', '256M');
}

if (!defined('WP_MEMORY_LIMIT')) {
    define('WP_MEMORY_LIMIT', '40M');
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}
if (!defined('MONTH_IN_SECONDS')) {
    define('MONTH_IN_SECONDS', 2592000);
}
if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('OBJECT_K')) {
    define('OBJECT_K', 'OBJECT_K');
}

if (!defined('PHP_INT_MAX')) {
    define('PHP_INT_MAX', 2147483647);
}

// ======================== 钩子系统 ========================

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        $mapped = \ZhiCms\base\compat\WordPressBridge::mapHook($tag);
        \ZhiCms\base\Hook::add($mapped, $function_to_add, (int)$priority);
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        $mapped = \ZhiCms\base\compat\WordPressBridge::mapHook($tag);
        \ZhiCms\base\Hook::add($mapped, $function_to_add, (int)$priority);
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$arg)
    {
        $mapped = \ZhiCms\base\compat\WordPressBridge::mapHook($tag);
        \ZhiCms\base\Hook::listen($mapped, $arg);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args)
    {
        $mapped = \ZhiCms\base\compat\WordPressBridge::mapHook($tag);
        return \ZhiCms\base\Hook::filter($mapped, $value, array_merge([$value], $args));
    }
}

if (!function_exists('apply_filters_ref_array')) {
    function apply_filters_ref_array($tag, $args)
    {
        $mapped = \ZhiCms\base\compat\WordPressBridge::mapHook($tag);
        $value = $args[0] ?? null;
        return \ZhiCms\base\Hook::filter($mapped, $value, $args);
    }
}

if (!function_exists('do_action_ref_array')) {
    function do_action_ref_array($tag, $args)
    {
        $mapped = \ZhiCms\base\compat\WordPressBridge::mapHook($tag);
        \ZhiCms\base\Hook::listen($mapped, $args);
    }
}

if (!function_exists('has_action')) {
    function has_action($tag, $function_to_check = false)
    {
        return false;
    }
}

if (!function_exists('has_filter')) {
    function has_filter($tag, $function_to_check = false)
    {
        return false;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($tag, $function_to_remove, $priority = 10) { /* no-op */ }
}

if (!function_exists('remove_filter')) {
    function remove_filter($tag, $function_to_remove, $priority = 10) { /* no-op */ }
}

if (!function_exists('remove_all_actions')) {
    function remove_all_actions($tag, $priority = false) { /* no-op */ }
}

if (!function_exists('remove_all_filters')) {
    function remove_all_filters($tag, $priority = false) { /* no-op */ }
}

if (!function_exists('doing_action')) {
    function doing_action($tag) { return false; }
}

if (!function_exists('doing_filter')) {
    function doing_filter($tag) { return false; }
}

if (!function_exists('did_action')) {
    function did_action($tag) { return 0; }
}

// ======================== 激活/停用注册 ========================

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback)
    {
        if (empty($GLOBALS['_wp_activation_hooks'])) {
            $GLOBALS['_wp_activation_hooks'] = array();
        }
        $key = $file;
        if (is_string($file) && !empty($GLOBALS['_wp_current_plugin'])) {
            $key = $GLOBALS['_wp_current_plugin'];
        }
        $GLOBALS['_wp_activation_hooks'][$key][] = $callback;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback)
    {
        if (empty($GLOBALS['_wp_deactivation_hooks'])) {
            $GLOBALS['_wp_deactivation_hooks'] = array();
        }
        $key = $file;
        if (is_string($file) && !empty($GLOBALS['_wp_current_plugin'])) {
            $key = $GLOBALS['_wp_current_plugin'];
        }
        $GLOBALS['_wp_deactivation_hooks'][$key][] = $callback;
    }
}

if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook($file, $callback)
    {
        if (empty($GLOBALS['_wp_uninstall_hooks'])) {
            $GLOBALS['_wp_uninstall_hooks'] = array();
        }
        $key = is_string($file) && !empty($GLOBALS['_wp_current_plugin']) ? $GLOBALS['_wp_current_plugin'] : $file;
        $GLOBALS['_wp_uninstall_hooks'][$key][] = $callback;
    }
}

// ======================== Option 系统 ========================

if (!function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        global $_wp_options;
        if (isset($_wp_options[$option])) {
            return $_wp_options[$option];
        }
        $alias = $GLOBALS['_wp_current_plugin'] ?? '';
        if ($alias) {
            $cfg = \ZhiCms\base\PluginManager::getConfig($alias);
            return $cfg['_wp_option_' . $option] ?? $default;
        }
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null)
    {
        global $_wp_options;
        $_wp_options[$option] = $value;
        $alias = $GLOBALS['_wp_current_plugin'] ?? '';
        if ($alias) {
            $cfg = \ZhiCms\base\PluginManager::getConfig($alias);
            $cfg['_wp_option_' . $option] = $value;
            \ZhiCms\base\PluginManager::setConfig($alias, $cfg);
        }
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option)
    {
        global $_wp_options;
        unset($_wp_options[$option]);
        $alias = $GLOBALS['_wp_current_plugin'] ?? '';
        if ($alias) {
            $cfg = \ZhiCms\base\PluginManager::getConfig($alias);
            unset($cfg['_wp_option_' . $option]);
            \ZhiCms\base\PluginManager::setConfig($alias, $cfg);
        }
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value = '', $deprecated = '', $autoload = 'yes')
    {
        global $_wp_options;
        if (!isset($_wp_options[$option])) {
            return update_option($option, $value, $autoload);
        }
        return false;
    }
}

// ======================== 转义/清理函数 ========================

if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_url')) {
    function esc_url($url, $protocols = null, $_context = 'display') { return filter_var($url, FILTER_SANITIZE_URL); }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null) { return esc_url($url, $protocols); }
}

if (!function_exists('esc_js')) {
    function esc_js($text) { return addcslashes($text, "\\\'\"\n\r/"); }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_sql')) {
    function esc_sql($data) { return addslashes($data); }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) { return strip_tags($data, '<p><a><br><strong><em><img><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><span><div><table><tr><td><th><thead><tbody><hr>'); }
}

if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html, $allowed_protocols = array()) { return strip_tags($string); }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags($str)); }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) { return filter_var(trim($email), FILTER_SANITIZE_EMAIL); }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title, $fallback_title = '', $context = 'save') { return preg_replace('/[^a-z0-9\-_]/', '', strtolower(trim($title))); }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($key))); }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user($username, $strict = false) { return preg_replace('/[^a-zA-Z0-9_\-\.@ ]/', '', trim($username)); }
}

if (!function_exists('absint')) {
    function absint($maybeint) { return abs((int)$maybeint); }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false) {
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
        $string = strip_tags($string);
        if ($remove_breaks) $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        return trim($string);
    }
}

// ======================== Nonce / CSRF ========================

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true)
    {
        $out = '<input type="hidden" name="' . esc_attr($name) . '" value="' . md5($action) . '">';
        if ($echo) echo $out;
        return $out;
    }
}

if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce')
    {
        return $actionurl . (strpos($actionurl, '?') === false ? '?' : '&') . $name . '=' . md5($action);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) { return $nonce === md5($action); }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) { return substr(md5($action . time()), 0, 10); }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') { return true; }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = false) { return true; }
}

// ======================== 通用工具函数 ========================

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null)
    {
        return array(
            'path'    => BASE_PATH . 'upload/',
            'url'     => defined('ROOT_URL') ? ROOT_URL . 'upload/' : '/upload/',
            'subdir'  => '',
            'basedir' => BASE_PATH . 'upload/',
            'baseurl' => defined('ROOT_URL') ? ROOT_URL . 'upload/' : '/upload/',
            'error'   => false,
        );
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) { return rtrim($string, '/\\') . '/'; }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($string) { return rtrim($string, '/\\'); }
}

if (!function_exists('wp_basename')) {
    function wp_basename($path, $suffix = '') { return urldecode(basename(str_replace(array('%2F', '%5C'), '/', urlencode($path)), $suffix)); }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file)
    {
        $file = str_replace('\\', '/', $file);
        $plugin_dir = str_replace('\\', '/', WP_PLUGIN_DIR);
        return trim(str_replace($plugin_dir, '', $file), '/');
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return trailingslashit(dirname($file)); }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) { return trailingslashit(plugins_url(basename(dirname($file)))); }
}

if (!function_exists('content_url')) {
    function content_url($path = '') { return WP_CONTENT_URL . ltrim($path, '/'); }
}

if (!function_exists('site_url')) {
    function site_url($path = '', $scheme = null) { return defined('ROOT_URL') ? ROOT_URL . ltrim($path, '/') : '/' . ltrim($path, '/'); }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null) { return defined('ROOT_URL') ? ROOT_URL . ltrim($path, '/') : '/' . ltrim($path, '/'); }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') { return defined('ROOT_URL') ? ROOT_URL . 'index.php?r=manage/' . ltrim($path, '/') : '/index.php?r=manage/' . ltrim($path, '/'); }
}

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') { return defined('ROOT_URL') ? ROOT_URL . 'plugins/' . ltrim($path, '/') : '/plugins/' . ltrim($path, '/'); }
}

if (!function_exists('includes_url')) {
    function includes_url($path = '', $scheme = null) { return defined('ROOT_URL') ? ROOT_URL . 'wp-includes/' . ltrim($path, '/') : '/wp-includes/' . ltrim($path, '/'); }
}

// ======================== 请求信息 ========================

if (!function_exists('is_admin')) {
    function is_admin() { return defined('APP_NAME') && APP_NAME === 'manage'; }
}

if (!function_exists('is_ajax')) {
    function is_ajax() { return defined('DOING_AJAX') && DOING_AJAX; }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() { return !empty($_SESSION['manage_system']) || !empty($_SESSION['user']); }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return !empty($_SESSION['manage_system']); }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return $_SESSION['user_id'] ?? 0; }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        $u = new \stdClass();
        $u->ID = $_SESSION['user_id'] ?? 0;
        $u->user_login = $_SESSION['user'] ?? '';
        return $u;
    }
}

// ======================== 字符串/数组工具 ========================

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = '')
    {
        if (is_object($args)) $args = get_object_vars($args);
        if (is_array($defaults)) return array_merge($defaults, (array)$args);
        return (array)$args;
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str($string, &$array) { parse_str($string, $array); }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) { return @parse_url($url, $component); }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options | JSON_UNESCAPED_UNICODE, $depth); }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($response, $status_code = null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null, $options = 0) {
        wp_send_json(array('success' => true, 'data' => $data));
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null, $options = 0) {
        wp_send_json(array('success' => false, 'data' => $data));
    }
}

// ======================== WP_Error 兼容 ========================

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '')
        {
            if (!empty($code)) $this->add($code, $message, $data);
        }

        public function add($code, $message, $data = '')
        {
            $this->errors[$code][] = $message;
            if (!empty($data)) $this->error_data[$code] = $data;
        }

        public function get_error_codes() { return array_keys($this->errors); }
        public function get_error_messages($code = '') { return $code ? ($this->errors[$code] ?? array()) : array_merge(...array_values($this->errors)); }
        public function get_error_message($code = '') { $m = $this->get_error_messages($code); return $m[0] ?? ''; }
        public function has_errors() { return !empty($this->errors); }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return $thing instanceof WP_Error; }
}

// ======================== 文件系统 ========================

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) { if (!is_dir($target)) @mkdir($target, 0755, true); return is_dir($target); }
}

if (!function_exists('wp_upload_bits')) {
    function wp_upload_bits($name, $deprecated, $bits, $time = null)
    {
        $upload = wp_upload_dir($time);
        $filename = wp_unique_filename($upload['path'], $name);
        $new_file = $upload['path'] . $filename;
        if (@file_put_contents($new_file, $bits) === false) return array('error' => 'Could not write file');
        @chmod($new_file, 0644);
        return array('file' => $new_file, 'url' => $upload['url'] . $filename, 'type' => '', 'error' => false);
    }
}

if (!function_exists('wp_unique_filename')) {
    function wp_unique_filename($dir, $filename, $unique_filename_callback = null)
    {
        $name = $filename;
        $i = 1;
        while (is_file($dir . '/' . $name)) {
            $info = pathinfo($filename);
            $name = $info['filename'] . '-' . $i . (isset($info['extension']) ? '.' . $info['extension'] : '');
            $i++;
        }
        return $name;
    }
}

// ======================== HTTP API 桩 ========================

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array())
    {
        $ctx = stream_context_create(array('http' => array(
            'timeout' => $args['timeout'] ?? 15,
            'header'  => $args['headers'] ?? array(),
        )));
        $body = @file_get_contents($url, false, $ctx);
        return array('body' => $body, 'response' => array('code' => $body !== false ? 200 : 500), 'headers' => $http_response_header ?? array());
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array())
    {
        $ctx = stream_context_create(array('http' => array(
            'method'  => 'POST',
            'timeout' => $args['timeout'] ?? 15,
            'header'  => $args['headers'] ?? array('Content-Type: application/x-www-form-urlencoded'),
            'content' => $args['body'] ?? '',
        )));
        $body = @file_get_contents($url, false, $ctx);
        return array('body' => $body, 'response' => array('code' => $body !== false ? 200 : 500), 'headers' => $http_response_header ?? array());
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) { return $response['response']['code'] ?? 0; }
}

// ======================== 其他常用函数 ========================

if (!function_exists('__return_true')) {
    function __return_true() { return true; }
}
if (!function_exists('__return_false')) {
    function __return_false() { return false; }
}
if (!function_exists('__return_null')) {
    function __return_null() { return null; }
}
if (!function_exists('__return_zero')) {
    function __return_zero() { return 0; }
}
if (!function_exists('__return_empty_string')) {
    function __return_empty_string() { return ''; }
}
if (!function_exists('__return_empty_array')) {
    function __return_empty_array() { return array(); }
}

if (!function_exists('wp_rand')) {
    function wp_rand($min = 0, $max = 0) { return rand($min, $max); }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) $chars .= '!@#$%^&*()';
        $password = '';
        for ($i = 0; $i < $length; $i++) $password .= $chars[random_int(0, strlen($chars) - 1)];
        return $password;
    }
}

if (!function_exists('wp_hash')) {
    function wp_hash($data, $scheme = 'auth') { return md5($data); }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') { return defined('AUTH_KEY') ? AUTH_KEY : 'zhicms_salt'; }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) { echo htmlspecialchars(is_array($message) ? json_encode($message) : $message); exit; }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect($location, $status = 302, $x_redirect_by = 'WordPress') {
        header("Location: $location", true, $status);
        exit;
    }
}

if (!function_exists('status_header')) {
    function status_header($code, $description = '') { http_response_code($code); }
}

if (!function_exists('wp_dashboard_setup')) {
    function wp_dashboard_setup() { do_action('wp_dashboard_setup'); }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'timestamp') return time();
        return date($type, time());
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null) {
        return date($format, $timestamp ?? time());
    }
}

if (!function_exists('wp_timezone_string')) {
    function wp_timezone_string() { return date_default_timezone_get(); }
}

if (!function_exists('_doing_it_wrong')) {
    function _doing_it_wrong($function, $message, $version) { /* silent */ }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null) { $found = false; return false; }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) { return true; }
}
if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') { return true; }
}
if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() { return true; }
}

// ======================== 全局变量初始化 ========================

global $_wp_options;
if (!isset($_wp_options)) { $_wp_options = array(); }

if (!isset($GLOBALS['_wp_current_plugin'])) { $GLOBALS['_wp_current_plugin'] = null; }
if (!isset($GLOBALS['_wp_plugin_dir'])) { $GLOBALS['_wp_plugin_dir'] = null; }

// ======================== 国际化桩函数 ========================

if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('_e')) {
    function _e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default') { return $text; }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') { echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') { echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) { return true; }
}
