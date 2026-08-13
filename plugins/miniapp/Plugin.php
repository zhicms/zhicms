<?php
namespace plugins\miniapp;
use ZhiCms\base\plugin\BasePlugin;

/**
 * 小程序 & App 原生插件入口
 *
 * 设计原则（用户拍板）：
 *   - 合并 zhuige_xcx + zhuige_shop 为 ZhiCms 原生插件，alias = miniapp
 *   - 不走任何 plug-xxx.html / plugin URL 体系，所有前后端通信走 ZhiCms api 接口层
 *   - 本插件只承载「自营商城」缺口：新建 yun_shop_* 表 + 后台配置（微信支付）+ 可选展示页
 *   - 资讯/淘客/论坛/用户 全部复用主站 app/api/controller/* 已有接口
 *
 * 配套 uni-app 工程：mini/（主色 #000/#fff 黑白风，商城用 subpackages/shop 分包）
 */
class Plugin extends BasePlugin
{
    public function register()
    {
        // 无前台展示页钩子需求：api 层由框架原生路由 index.php?r=api/shop/* 调度
    }

    /**
     * 安装：给 yun_user 追加小程序所需字段（幂等容错，重复列忽略）
     */
    public function install()
    {
        $cols = array(
            'wx_openid' => "ALTER TABLE {pre}user ADD `wx_openid` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '微信openid'",
            'wx_unionid'=> "ALTER TABLE {pre}user ADD `wx_unionid` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '微信unionid'",
            'balance'   => "ALTER TABLE {pre}user ADD `balance` DECIMAL(10,2) NOT NULL DEFAULT '0.00' COMMENT '账户余额'",
        );
        foreach ($cols as $sql) {
            try {
                \ZhiCms\base\PluginManager::db()->execute(str_replace('{pre}', $this->prefix(), $sql));
            } catch (\Throwable $e) {
                // 列已存在则忽略
            }
        }
    }

    /**
     * 卸载：保留用户字段（避免误删数据），仅删除商城表由 uninstall.sql 处理
     */
    public function uninstall()
    {
    }

    private function prefix()
    {
        $db = \ZhiCms\base\PluginManager::db();
        return isset($db->prefix) ? $db->prefix : 'yun_';
    }
}
