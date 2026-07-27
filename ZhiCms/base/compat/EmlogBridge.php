<?php
namespace ZhiCms\base\compat;

/**
 * Emlog 插件桥接器
 * - 把 emlog 挂载点名映射为 ZhiCms 原生钩子
 * - 加载 emlog 格式插件（{alias}.php + 后缀文件）并触发生命周期
 * - ⚠️ 安全机制：加载时通过常量预定义防止 exit('access denied!') + 输出缓冲隔离
 */
class EmlogBridge
{
    /** emlog 挂载点 => ZhiCms 原生钩子 */
    public static $hookMap = array(
        'adm_head'            => 'admin_head',
        'adm_menu'            => 'admin_menu',
        'adm_footer'          => 'admin_footer',
        'adm_main_top'        => 'admin_dashboard',
        'adm_main_content'    => 'admin_dashboard',
        'adm_comment_display' => 'admin_dashboard',
        'adm_widget'          => 'admin_dashboard',
        'adm_plugin_page'     => 'admin_dashboard',
        'index_head'          => 'index_head',
        'index_footer'        => 'index_footer',
        'index_loglist_top'   => 'index_loglist_top',
        'log_related'         => 'article_view',
        'save_log'            => 'article_save',
        'del_log'             => 'article_delete',
        'save_page'           => 'page_save',
        'del_page'            => 'page_delete',
        'comment_post'        => 'comment_post',
        'comment_saved'       => 'comment_saved',
        'login_succeed'       => 'user_login',
        'login_fail'          => 'user_login_fail',
        'register_succeed'    => 'user_register',
        'delete_user'         => 'user_delete',
        'attach_upload'       => 'upload',
        'login_head'          => 'admin_head',
        'user_menu'           => 'admin_menu',
        'article_content_echo'=> 'article_view',
        'upload_media'        => 'upload',
        'footer'              => 'index_footer',
        'side_menu'           => 'admin_menu',
        'data_preview'        => 'data_preview',
    );

    public static function mapHook($hook)
    {
        return self::$hookMap[$hook] ?? $hook;
    }

    /** 安全加载 emlog 插件：预置常量 → API → 主文件 → 后缀文件 */
    public static function load($alias, $dir)
    {
        // 预定义所有 Emlog 防护常量（必须在 require 插件文件之前）
        self::predefineConstants();

        require_once BASE_PATH . 'ZhiCms/base/compat/emlog_api.php';

        $main = $dir . '/' . $alias . '.php';
        if (is_file($main)) {
            self::safeRequire($main, $alias);
        }
        foreach (array('_callback', '_setting', '_user', '_show') as $suffix) {
            $f = $dir . '/' . $alias . $suffix . '.php';
            if (is_file($f)) {
                self::safeRequire($f, $alias);
            }
        }
    }

    /** 安装时触发 callback_init() */
    public static function install($alias, $dir)
    {
        self::load($alias, $dir);
        if (function_exists('callback_init')) {
            try { call_user_func('callback_init'); } catch (\Throwable $e) {}
        }
    }

    /** 卸载时触发 callback_rm() */
    public static function uninstall($alias, $dir)
    {
        self::load($alias, $dir);
        if (function_exists('callback_rm')) {
            try { call_user_func('callback_rm'); } catch (\Throwable $e) {}
        }
    }

    /** 更新时触发 callback_up() */
    public static function upgrade($alias, $dir)
    {
        self::load($alias, $dir);
        if (function_exists('callback_up')) {
            try { call_user_func('callback_up'); } catch (\Throwable $e) {}
        }
    }

    /**
     * 在加载任何插件文件前预置必需常量（三层防护中的第二层）
     * - 第一层：Compat::predefineAll() 在 App::run() 中全量预置
     * - 第二层：本方法在 Bridge::load() 中兜底，并额外定义本平台常量
     * - 第三层：对应 _api.php 文件中也定义
     */
    protected static function predefineConstants()
    {
        // Emlog 核心常量
        if (!defined('EMLOG_ROOT'))    define('EMLOG_ROOT', BASE_PATH);
        if (!defined('TEMPLATE_PATH')) define('TEMPLATE_PATH', BASE_PATH . 'public/');
        if (!defined('TPLS_URL'))      define('TPLS_URL', defined('ROOT_URL') ? ROOT_URL . 'public/' : '/public/');
        // 兜底：其他平台的常量也预置，防止因 detectType 误判导致 exit
        if (!defined('ABSPATH'))       define('ABSPATH', BASE_PATH);
        if (!defined('ZBP_PATH'))      define('ZBP_PATH', BASE_PATH);
    }

    /**
     * 安全 require：用输出缓冲包裹
     * 通过 predefineConstants() 预置常量，常见 exit('access denied!') 已不会触发。
     */
    protected static function safeRequire($file, $alias = '')
    {
        try {
            ob_start();
            require_once $file;
            $output = ob_get_clean();
            if ($output !== '' && trim($output) !== '') {
                error_log("[EmlogBridge] Plugin '$alias' produced unexpected output during load: " . substr($output, 0, 500));
            }
        } catch (\Throwable $e) {
            @ob_end_clean();
            error_log("[EmlogBridge] Plugin '$alias' error during load: " . $e->getMessage());
        }
    }
}
