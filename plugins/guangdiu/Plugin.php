<?php
namespace plugins\guangdiu;

use ZhiCms\base\plugin\BasePlugin;

/**
 * 逛丢（Guangdiu）导购插件入口
 * - type=template：可在后台「系统设置 - 站点设置」中设为站点主页（home_plug）
 * - 前台展示页由 Plugin.php 的 displayPage() 调度到 SiteController
 */
class Plugin extends BasePlugin
{
    /**
     * 插件引导：注册路由重写、注册公共钩子（如页脚链接等）
     */
    public function boot()
    {
        // 插件路由重写已在 plugin.json 的 rewrite 中声明，框架启动自动合并，无需手动注册。
    }

    /**
     * 站点主页 / 插件展示页统一入口
     * 框架 PlugController::view() 会把除 alias/r 外的所有请求参数透传进来（mod/id/kw/page 等）。
     */
    public function displayPage($params = array())
    {
        $controller = new \plugins\guangdiu\controller\SiteController();
        $controller->run($params);
        exit;
    }
}
