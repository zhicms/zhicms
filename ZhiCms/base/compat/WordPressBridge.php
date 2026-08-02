<?php
namespace ZhiCms\base\compat;

/**
 * WordPress 插件桥接器
 * - 把 WordPress 的 add_action / add_filter 映射为 ZhiCms 原生钩子
 * - 加载 WordPress 格式插件（主 PHP 文件含 Plugin Name 头注释）并触发生命周期
 * - WordPress 插件通过 register_activation_hook / register_deactivation_hook
 *   注册安装/卸载回调，由兼容层在对应时机调用
 * - ⚠️ 安全机制：加载时通过常量预定义防止 exit('Access denied') + 输出缓冲隔离
 */
class WordPressBridge
{
    /** WordPress 常用钩子 => ZhiCms 原生钩子 */
    public static $hookMap = array(
        'admin_head'            => 'admin_head',
        'admin_menu'            => 'admin_menu',
        'admin_footer'          => 'admin_footer',
        'admin_init'            => 'admin_init',
        'admin_notices'         => 'admin_notices',
        'wp_head'               => 'index_head',
        'wp_footer'             => 'index_footer',
        'wp_enqueue_scripts'    => 'wp_enqueue_scripts',
        'wp_loaded'             => 'appBegin',
        'init'                  => 'appBegin',
        'save_post'             => 'article_save',
        'delete_post'           => 'article_delete',
        'comment_post'          => 'comment_post',
        'wp_insert_comment'     => 'comment_saved',
        'wp_login'              => 'user_login',
        'user_register'         => 'user_register',
        'delete_user'           => 'user_delete',
        'wp_ajax_nopriv_'       => 'cmd_ajax',
        'wp_ajax_'              => 'cmd_ajax',
        'template_redirect'     => 'template_redirect',
        'the_content'           => 'article_view',
        'the_excerpt'           => 'article_excerpt',
        'wp_title'              => 'page_title',
        'admin_bar_menu'        => 'admin_bar',
        'admin_enqueue_scripts' => 'admin_enqueue',
        'widgets_init'          => 'widget_init',
        'rest_api_init'         => 'rest_api',
        'plugins_loaded'        => 'plugins_loaded',
        'after_setup_theme'     => 'after_setup_theme',
        'wp_dashboard_setup'    => 'admin_dashboard',
        'shutdown'              => 'shutdown',
        'parse_request'         => 'parse_request',
        'send_headers'          => 'send_headers',
    );

    public static function mapHook($hook)
    {
        // 先精确匹配
        if (isset(self::$hookMap[$hook])) {
            return self::$hookMap[$hook];
        }
        // 尝试前缀匹配 wp_ajax_nopriv_xxx / wp_ajax_xxx
        if (strpos($hook, 'wp_ajax_nopriv_') === 0 || strpos($hook, 'wp_ajax_') === 0) {
            return 'cmd_ajax';
        }
        // 未知钩子走通用钩子
        return $hook;
    }

    /** 安全加载 WordPress 格式插件 */
    public static function load($alias, $dir)
    {
        // 预定义所有 WordPress 防护常量（必须在 require 插件文件之前）
        self::predefineConstants();

        require_once \BASE_PATH . 'ZhiCms/base/compat/wordpress_api.php';

        // 设置当前加载的插件上下文
        $GLOBALS['_wp_current_plugin'] = $alias;
        $GLOBALS['_wp_plugin_dir'] = $dir;

        $main = self::findMainFile($alias, $dir);
        if ($main && is_file($main)) {
            self::safeRequire($main, $alias);
        }
    }

    /** 查找 WordPress 插件的入口 PHP 文件 */
    public static function findMainFile($alias, $dir)
    {
        // 优先查找与目录同名的文件 {alias}.php
        $candidate = $dir . '/' . $alias . '.php';
        if (is_file($candidate) && self::hasWordPressHeader($candidate)) {
            return $candidate;
        }
        // 遍历目录找第一个带 WordPress 头注释的 PHP 文件
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.php') as $f) {
                if (self::hasWordPressHeader($f)) {
                    return $f;
                }
            }
        }
        return false;
    }

    /** 检测文件是否包含 WordPress 插件头部注释 */
    public static function hasWordPressHeader($file)
    {
        $c = @file_get_contents($file, false, null, 0, 8192);
        if ($c === false) return false;
        return (bool)preg_match('/Plugin\s+Name\s*:\s*.+/i', $c);
    }

    /** 读取 WordPress 插件头部元信息 */
    public static function readHeader($file)
    {
        $c = @file_get_contents($file, false, null, 0, 8192);
        $meta = array('name' => '', 'version' => '1.0', 'author' => '', 'description' => '');
        if ($c === false) return $meta;
        $fields = array(
            'Plugin Name'       => 'name',
            'Version'           => 'version',
            'Author'            => 'author',
            'Description'       => 'description',
            'Plugin URI'        => 'uri',
            'Author URI'        => 'author_url',
            'Text Domain'       => 'text_domain',
            'Domain Path'       => 'domain_path',
            'Requires PHP'      => 'requires_php',
            'Requires at least' => 'requires_wp',
        );
        foreach ($fields as $key => $kk) {
            if (preg_match('/\*\s*' . preg_quote($key, '/') . '\s*:\s*(.+)/i', $c, $m)) {
                $meta[$kk] = trim($m[1]);
            }
        }
        return $meta;
    }

    /** 安装时触发 register_activation_hook 回调 */
    public static function install($alias, $dir)
    {
        self::load($alias, $dir);
        if (!empty($GLOBALS['_wp_activation_hooks'][$alias])) {
            $h = $GLOBALS['_wp_activation_hooks'][$alias];
            foreach ($h as $cb) {
                try { if (is_callable($cb)) call_user_func($cb); } catch (\Throwable $e) {}
            }
        }
        // 某些插件通过 register_activation_hook( __FILE__, 'xxx' ) 注册
        $main = self::findMainFile($alias, $dir);
        if ($main && !empty($GLOBALS['_wp_activation_hooks'][$main])) {
            foreach ($GLOBALS['_wp_activation_hooks'][$main] as $cb) {
                try { if (is_callable($cb)) call_user_func($cb); } catch (\Throwable $e) {}
            }
        }
    }

    /** 卸载时触发 register_deactivation_hook 回调 */
    public static function uninstall($alias, $dir)
    {
        self::load($alias, $dir);
        if (!empty($GLOBALS['_wp_deactivation_hooks'][$alias])) {
            $h = $GLOBALS['_wp_deactivation_hooks'][$alias];
            foreach ($h as $cb) {
                try { if (is_callable($cb)) call_user_func($cb); } catch (\Throwable $e) {}
            }
        }
        $main = self::findMainFile($alias, $dir);
        if ($main && !empty($GLOBALS['_wp_deactivation_hooks'][$main])) {
            foreach ($GLOBALS['_wp_deactivation_hooks'][$main] as $cb) {
                try { if (is_callable($cb)) call_user_func($cb); } catch (\Throwable $e) {}
            }
        }
    }

    /**
     * 在加载任何插件文件前预置必需常量（三层防护中的第二层）
     */
    protected static function predefineConstants()
    {
        // WordPress 核心常量
        if (!defined('ABSPATH'))         define('ABSPATH', \BASE_PATH);
        if (!defined('WPINC'))           define('WPINC', 'wp-includes');
        if (!defined('WP_CONTENT_DIR'))  define('WP_CONTENT_DIR', \BASE_PATH);
        if (!defined('WP_PLUGIN_DIR'))   define('WP_PLUGIN_DIR', \BASE_PATH . 'plugins/');
        if (!defined('WP_PLUGIN_URL'))   define('WP_PLUGIN_URL', defined('\ROOT_URL') ? \ROOT_URL . 'plugins/' : '/plugins/');
        if (!defined('WP_CONTENT_URL'))  define('WP_CONTENT_URL', defined('\ROOT_URL') ? \ROOT_URL : '/');
        if (!defined('WP_DEBUG'))        define('WP_DEBUG', false);
        if (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', false);
        // 兜底：其他平台的常量也预置，防止因 detectType 误判导致 exit
        if (!defined('EMLOG_ROOT'))      define('EMLOG_ROOT', \BASE_PATH);
        if (!defined('ZBP_PATH'))        define('ZBP_PATH', \BASE_PATH);
    }

    /**
     * 安全 require：用输出缓冲包裹
     * 通过 predefineConstants() 预置常量，常见 exit('Access denied') 已不会触发。
     */
    protected static function safeRequire($file, $alias = '')
    {
        try {
            ob_start();
            require_once $file;
            $output = ob_get_clean();
            if ($output !== '' && trim($output) !== '') {
                error_log("[WordPressBridge] Plugin '$alias' produced unexpected output during load: " . substr($output, 0, 500));
            }
        } catch (\Throwable $e) {
            @ob_end_clean();
            error_log("[WordPressBridge] Plugin '$alias' error during load: " . $e->getMessage());
        }
    }
}
