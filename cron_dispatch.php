<?php
/**
 * 计划任务独立触发脚本（C 方案）
 * ------------------------------------------------------------
 * 用法：
 *   1) CLI（推荐，最稳，不依赖 Web 服务可达）：
 *        php /www/cron_dispatch.php
 *   2) Web（与 index.php?r=manage/task/run&token=XXX 等价）：
 *        http://域名/cron_dispatch.php?token=XXX
 *
 * 该脚本引导框架后，复用 TaskController::run() 的完整逻辑
 * （token 校验 / 并发锁 / next_run 到期判断 / 执行到期任务），
 * 不重复实现调度逻辑。
 *
 * 配合「B 方案」(TaskController::syncOsCron) 在保存/启用任务时
 * 自动把本脚本写进操作系统计划任务，即可实现「设置好时间就自动执行」。
 */

// ---- CLI 环境下补齐 $_SERVER 默认值，保证 core.php / App::init 不报错 ----
if (PHP_SAPI === 'cli') {
    $_SERVER['SCRIPT_NAME']    = '/cron_dispatch.php';
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    $_SERVER['REQUEST_URI']     = '/cron_dispatch.php';
    $_SERVER['REQUEST_METHOD']  = 'GET';
    $_SERVER['HTTP_HOST']       = 'localhost';
    if (!isset($_SERVER['REQUEST_TIME'])) {
        $_SERVER['REQUEST_TIME'] = time();
    }
    if (!isset($_SERVER['argv'])) {
        $_SERVER['argv'] = array();
    }
}

// ---- 引导框架 ----
require_once __DIR__ . '/ZhiCms/core.php';

use ZhiCms\base\Config;
use ZhiCms\base\App;

// ---- 决定 token ----
// CLI 调用视为本地可信，直接从配置读取正确 token 注入，绕过 HTTP token 校验；
// Web 调用则必须与 URL 传入的 token 一致（由 TaskController::run 校验）。
if (PHP_SAPI === 'cli') {
    $cfg = \app\common\ConfigStore::load('task');
    $token = isset($cfg['run_token']) ? $cfg['run_token'] : '';
    // 若尚未生成 token，CLI 自动生成一个（保证可运行），与后台「运行令牌」页面一致
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        $arr = $cfg;
        $arr['run_token'] = $token;
        \app\common\ConfigStore::save('task', $arr);
    }
    $_REQUEST['token'] = $token;
} else {
    $_REQUEST['token'] = isset($_GET['token']) ? $_GET['token'] : '';
}

// ---- 模拟请求 manage/task/run ----
$_REQUEST['r'] = 'manage/task/run';

// 关闭前台输出缓冲，避免 CLI 下多余空白
if (ob_get_level() > 0) {
    ob_end_clean();
}

App::run();
