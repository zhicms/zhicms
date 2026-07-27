<?php
/**
 * Plugin Name: WP Demo 兼容示例
 * Version: 1.0.0
 * Plugin URI: https://www.zhicms.com
 * Description: 演示以 WordPress 格式编写、在 ZhiCms 下直接运行的插件（兼容层）。展示 add_action / add_filter 等 API 的兼容。
 * Author: ZhiCms
 * Author URI: https://www.zhicms.com
 * Text Domain: wpdemo
 */

// 防止直接访问（兼容层中 ABSPATH 已由 WordPressBridge 预定义）
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件激活回调
 */
function wpdemo_activate()
{
    // 激活时执行的操作（日志、建表等）
}

/**
 * 插件停用回调
 */
function wpdemo_deactivate()
{
    // 停用时清理
}

// 注册激活/停用钩子
register_activation_hook(__FILE__, 'wpdemo_activate');
register_deactivation_hook(__FILE__, 'wpdemo_deactivate');

/**
 * 后台头部注入样式
 */
function wpdemo_admin_head()
{
    echo '<style>.wpdemo-badge{display:inline-block;padding:2px 8px;border-radius:4px;background:#0073aa;color:#fff;font-size:11px;margin-left:6px;}</style>';
}

/**
 * 后台菜单
 */
function wpdemo_admin_menu()
{
    echo '<div style="padding:6px 12px;margin:6px 0;background:#f0f6fc;border-left:3px solid #0073aa;color:#005a87;">'
        . '<strong>WP Demo 插件活跃</strong> — 通过 WordPress 兼容层运行'
        . '</div>';
}

/**
 * 前台页脚
 */
function wpdemo_footer()
{
    echo '<div style="text-align:center;color:#767676;padding:16px 0;font-size:12px;">— Powered by ZhiCms + WordPress 兼容层（wpdemo）—</div>';
}

/**
 * 仪表盘 Widget
 */
function wpdemo_dashboard_widget()
{
    echo '<div style="padding:12px;margin:8px 0;border:1px solid #ccd0d4;border-radius:4px;">'
        . '<p style="margin:0;color:#23282d;">这是一个 WordPress 格式的示例插件，在 ZhiCms 兼容层下正常运行。</p>'
        . '<p style="margin:8px 0 0;color:#555;">支持 API：add_action / add_filter / register_activation_hook / get_option 等</p>'
        . '</div>';
}

// 注册 WordPress 风格的钩子
add_action('admin_head', 'wpdemo_admin_head');
add_action('admin_menu', 'wpdemo_admin_menu');
add_action('wp_footer', 'wpdemo_footer');
add_action('wp_dashboard_setup', 'wpdemo_dashboard_widget');
