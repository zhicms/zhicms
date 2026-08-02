<?php
/**
 * 入口引导文件（由 index.php → core.php 在最前引入）
 * 集中处理：Session 配置、移动端跳转、编码/错误处理、响应头、gzip 缓冲。
 */
// 项目根目录（本文件位于 ZhiCms/ 下，上一级即根目录）
$rootDir = dirname(__DIR__);

// ======================== Session 配置 ========================
$sessionParams = [
    'lifetime' => 86400,  // 24h 超时
    'path' => '/',        // 全站有效
    'domain' => '',       // 当前域名
    'secure' => false,    // 生产环境建议设为 true（仅 HTTPS）
    'httponly' => true,   // 防止 JavaScript 访问 Cookie
    'samesite' => 'Lax'   // 防止 CSRF 攻击
];
session_set_cookie_params($sessionParams);
session_start();

/* ============ 移动端全局重定向到 m 端（MController 渲染 super_search 前端） ============
 * 整站 m 端由 MController 渲染已移植 super_search 前端的 m/index.html
 * 规则：移动端 UA + 访问 HTML 页面(或首页) 且 非 m 端本身 / 非 super_search / 未显式要求桌面(?m=1 / ?pc=1)
 * 注意：基于 UA 判断，避免 localStorage 标记导致的“访问过一次就再也不跳”的问题
 */
function _isMobileUA(){
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
    return (bool) preg_match('/ipad|iphone os|ipod|midp|rv:1\.2\.3\.4|ucweb|android|windows ce|windows mobile|blackberry|webos|micromessenger|mobile/i', $ua);
}
function _mobileRedirectToM(){
    $reqUri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $reqPath = parse_url($reqUri, PHP_URL_PATH);
    // 桌面模式标记：点频道进入站内页时带 ?m=1，或显式 ?pc=1，均不再跳回 m 端
    $desktopFlag = (isset($_GET['m']) && in_array($_GET['m'], array('1','0'), true)) || isset($_GET['pc']);
    if ($desktopFlag) return;
    // 已经是 m 端（MController 渲染 super_search 前端）或 super_search 页面，避免死循环
    if (preg_match('#^/m(\.|\-)|super_search#i', $reqPath)) return;
    // 静态资源不处理
    if (preg_match('/\.(css|js|png|jpe?g|gif|ico|bmp|webp|svg|woff2?|ttf|eot|map|mp4|mp3|json|xml|txt|zip|pdf|htaccess)$/i', $reqPath)) return;
    // 只对 HTML 页面/首页做重定向（/go/ 等功能性路由不重定向）
    $isHtmlPage = ($reqPath === '/' || $reqPath === '' || preg_match('/\.html$/i', $reqPath));
    if (!$isHtmlPage) return;
    if (!_isMobileUA()) return;
    header('Location: /m.html', true, 302);
    exit;
}
_mobileRedirectToM();

// ======================== 编码保护（必须最先设置） ========================
// 确保 PHP 内部和输出均使用 UTF-8，防止中文乱码
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// 错误报告和显示配置
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// 错误日志配置
$logDir = defined('ROOT_PATH') ? ROOT_PATH . 'data/log/' : $rootDir . '/data/log/';
$logFile = $logDir . 'error.log';

// 创建日志目录（如果不存在）
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// 自定义错误处理函数
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    global $logFile;
    $errorMsg = "\n[{$errno}] " . date('Y-m-d H:i:s') . ": {$errstr} in {$errfile} on line {$errline}";
    @file_put_contents($logFile, $errorMsg, FILE_APPEND | LOCK_EX);

    // 生产环境不显示详细错误
    if (ini_get('display_errors') && ($errno & (E_ERROR | E_PARSE | E_COMPILE_ERROR))) {
        echo "<strong>错误:</strong> {$errstr}\n";
    }
    return true;
}

// 自定义异常处理函数
function customExceptionHandler($exception) {
    global $logFile;
    $errorMsg = "\n[Exception] " . date('Y-m-d H:i:s') . ": " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    $errorMsg .= "\n堆栈跟踪:\n" . $exception->getTraceAsString();
    @file_put_contents($logFile, $errorMsg, FILE_APPEND | LOCK_EX);

    // 生产环境不显示详细异常
    if (ini_get('display_errors')) {
        echo "<strong>异常:</strong> " . $exception->getMessage() . "\n";
    }
}

// 注册错误和异常处理器
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// 注册关闭函数，捕获 fatal error
register_shutdown_function(function() {
    global $logFile;
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        $errorMsg = "\n[Fatal Error] " . date('Y-m-d H:i:s') . ": " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
        @file_put_contents($logFile, $errorMsg, FILE_APPEND | LOCK_EX);
        // 安装阶段（display_errors 已开启）将致命错误输出到页面，避免 PHP 8.x 下空白页
        if (ini_get('display_errors')) {
            header('Content-Type: text/html; charset=utf-8');
            echo "<div style='padding:20px;font-family:monospace;color:#c00;background:#fff0f0;border:1px solid #f99;border-radius:6px;margin:20px;'>"
               . "<strong>致命错误（Fatal Error）：</strong><br>" . htmlspecialchars($error['message'], ENT_QUOTES)
               . "<br><span style='color:#666;'>in " . htmlspecialchars($error['file'], ENT_QUOTES) . " on line " . $error['line'] . "</span></div>";
        }
    }
});

// 先设置核心响应头（必须在 ob_start 之前，防止被 gzip handler 干扰）
header('Content-Type: text/html; charset=utf-8');
header('Vary: Accept-Encoding');
header('Cache-Control: must-revalidate');

// 启动 gzip 输出缓冲（ob_gzhandler 会自动设置 Content-Encoding: gzip，无需手动设置）
if (!ob_start('ob_gzhandler')) {
    ob_start();
}
