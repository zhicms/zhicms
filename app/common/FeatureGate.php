<?php
namespace app\common;

/**
 * 功能开关门面（Feature Gate）
 *
 * 集中封装「某项功能（插件/配置）是否可用」的判断，避免在 api/前台/小程序配置
 * 接口各处散写 PluginManager 调用。商城、社区、AI 等依赖插件或配置的功能，
 * 统一走这里判断是否对用户开放。
 *
 * 设计原则：
 *  - 插件未启用 → 视为功能关闭（即使相关表/配置碰巧存在，也不对外提供服务）
 *  - 任何 DB/插件读取异常都兜底为「关闭」，避免未装插件时报 500 暴露 SQL
 *  - 纯读、无副作用，可安全在任意入口（api/index/manage）调用
 */
class FeatureGate
{
    /**
     * 自营商城（miniapp 插件）是否开启
     * 商城表由 miniapp 插件安装时创建，插件未启用则对外不提供商城服务。
     * @return bool
     */
    public static function shopEnabled()
    {
        try {
            return (bool) \ZhiCms\base\PluginManager::isEnabled('miniapp');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 插件是否启用（通用）
     * @param string $alias
     * @return bool
     */
    public static function pluginEnabled($alias)
    {
        try {
            return (bool) \ZhiCms\base\PluginManager::isEnabled($alias);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 读取插件配置（异常兜底为空数组）
     * @param string $alias
     * @return array
     */
    public static function pluginConfig($alias)
    {
        try {
            $cfg = \ZhiCms\base\PluginManager::getConfig($alias);
            return is_array($cfg) ? $cfg : array();
        } catch (\Throwable $e) {
            return array();
        }
    }

    /**
     * 社区（论坛）总开关是否开启
     * 配置键 forum_on = '1' 表示开启，其余（含空）视为关闭。
     * @return bool
     */
    public static function forumEnabled()
    {
        try {
            $row = obj('api/ApiData')->thisQuery(
                "SELECT `value` FROM `{pre}config` WHERE `key` = ?",
                array('forum_on')
            );
            $v = isset($row[0]['value']) ? $row[0]['value'] : '';
            return $v === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * AI 对话功能是否可用（至少有一可用 chat 模型）
     * @return bool
     */
    public static function aiEnabled()
    {
        try {
            return (bool) \app\common\AiService::isChatAvailable();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 聚合所有面向前端的模块开关，供 ConfigController 下发小程序/App 使用。
     * @return array { shop:bool, forum:bool, ai:bool }
     */
    public static function modules()
    {
        return array(
            'shop'  => self::shopEnabled(),
            'forum' => self::forumEnabled(),
            'ai'    => self::aiEnabled(),
        );
    }
}
