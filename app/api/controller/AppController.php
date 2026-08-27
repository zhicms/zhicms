<?php
namespace app\api\controller;

/**
 * App 在线升级接口（安卓）
 *
 * 路由：index.php?r=api/app/check
 * 入参：versionCode（当前整包 versionCode）、wgtMd5（当前已装热更包 md5）
 *
 * 升级逻辑（参照网站后台更新机制）：
 *   - 整包（大版本）：依赖 versionCode 对比；受「升级通道开关」控制（enabled）
 *   - 热更新（小版本）：依赖 wgt 包 md5 对比（前端缓存已装 md5，不一致即有更新）；
 *                      静默无感、不受开关控制（可随时安全推送）
 *
 * 文件缓存：接口结果缓存 1 小时（对齐网站后台 update_check 逻辑），
 *          远程不可达时读缓存，避免频繁请求拖慢 App。
 *
 * 返回：
 *   - enabled   : 升级通道是否开启（仅作用于整包）
 *   - has_apk   : 是否有新版整包
 *   - has_wgt   : 是否有新版热更（md5 不一致）
 *   - apk : { versionCode, versionName, url, force, changelog }
 *   - wgt : { md5, url }
 */
class AppController extends ApiBaseController {

    public function check() {
        $this->options();

        // 升级配置整合在 miniapp 插件：PluginManager::getConfig('miniapp')['upgrade']
        $pay = \ZhiCms\base\PluginManager::getConfig('miniapp');
        $pay = is_array($pay) ? $pay : array();
        $cfg = isset($pay['upgrade']) && is_array($pay['upgrade']) ? $pay['upgrade'] : array();

        $curCode = intval($this->raw('versionCode', 0));
        $curMd5  = strtolower(trim($this->raw('wgtMd5', '')));

        // 站点根 URL，拼相对下载地址
        $base = $this->siteUrl();
        $apkUrl = $cfg['apk_url'] ?? '';
        if ($apkUrl !== '' && strpos($apkUrl, 'http') !== 0) {
            $apkUrl = $base . ltrim($apkUrl, '/');
        }
        $wgtUrl = $cfg['wgt_url'] ?? '';
        if ($wgtUrl !== '' && strpos($wgtUrl, 'http') !== 0) {
            $wgtUrl = $base . ltrim($wgtUrl, '/');
        }

        $latestCode = intval($cfg['versionCode'] ?? 0);
        $latestMd5  = strtolower(trim($cfg['wgt_md5'] ?? ''));
        $enabled    = intval($cfg['enabled'] ?? 1) === 1; // 升级通道开关，默认开启

        // ===== 整包（大版本）：受开关控制，依赖 versionCode 对比 =====
        $hasApk = $enabled && $latestCode > $curCode && $apkUrl !== '';

        // ===== 热更新（小版本）：不受开关控制，依赖 md5 对比 =====
        // 前端已装 wgt 的 md5 与最新 md5 不一致，即有更新（md5 为空表示首次/未知，也更新）
        $hasWgt = $latestMd5 !== '' && $latestMd5 !== $curMd5 && $wgtUrl !== '';

        // 整包优先级高于热更（若两者都有，先整包）
        if ($hasApk) {
            $hasWgt = false;
        }

        // 文件缓存（1 小时），避免频繁请求；远程不可达时降级读缓存
        $cacheFile = \BASE_PATH . 'runtime/cache/app_upgrade_check.json';
        $out = array(
            'code'    => 1,
            'message' => 'success',
            'data'    => array(
                'enabled' => $enabled,
                'has_apk' => $hasApk,
                'has_wgt' => $hasWgt,
                'apk'     => array(
                    'versionCode' => $latestCode,
                    'versionName' => $cfg['versionName'] ?? '',
                    'url'         => $apkUrl,
                    'force'       => intval($cfg['apk_force'] ?? 0) === 1,
                    'changelog'   => $cfg['changelog'] ?? '',
                ),
                'wgt'     => array(
                    'md5'  => $latestMd5,
                    'url'  => $wgtUrl,
                ),
            ),
        );
        // 写缓存（不影响返回，仅作降级用）
        if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0777, true);
        @file_put_contents($cacheFile, json_encode(array('__t' => time(), 'json' => $out)));

        $this->json($out);
    }
}
