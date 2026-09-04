<?php
namespace ZhiCms\ext;

/**
 * 蜘蛛（爬虫）访问限制
 * 在框架引导阶段（仅前台）调用 zhi_spider_guard()，
 * 根据后台「蜘蛛限制」配置拦截垃圾蜘蛛或超频请求。
 */
class SpiderGuard {

    /** 工具型/可疑 UA 特征（命中即拦截，避免漏放采集器/扫描器/非常规爬虫，如 Gitee 爬虫） */
    private static $TOOL_UA = '/(?:gitee)|(?:\b(go-http-client|python-requests|python-urllib|python-httpx|libwww-perl|lwp::|www-mechanize|httpclient|apache-httpclient|webclient|okhttp|scrapy|zgrab|masscan|nmap|curl\/|wget|httrack|teleport|webcopier|winhttp|node-fetch|axios|undici|guzzle|java\/|headlesschrome|phantomjs|semrush|ahrefs|mj12|dotbot|petalbot|yandex|blexbot|megaindex|dataprovider|gitcrawler|archive\.org_bot|heritrix|scan|probe|exploit)\b)/i';

    public static function shouldBlock(){
        if (!class_exists('\\app\\common\\ConfigStore')) return false;
        $cfg = \app\common\ConfigStore::load('spider');
        if (empty($cfg) || empty($cfg['enable'])) return false;

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

        // 是否为“疑似蜘蛛”UA（便于记录访问）
        $isSpider = (strpos($ua, 'bot') !== false || strpos($ua, 'spider') !== false
            || strpos($ua, 'crawl') !== false || strpos($ua, 'slurp') !== false
            || strpos($ua, 'spider') !== false);

        if ($cfg['mode'] === 'whitelist') {
            $wl = preg_split('/\r\n|\r|\n/', $cfg['whitelist']);
            $allow = false;
            foreach ($wl as $w) {
                $w = trim($w);
                if ($w && strpos($ua, strtolower($w)) !== false) { $allow = true; break; }
            }
            if ($isSpider && !$allow) {
                self::logVisit('blocked_whitelist', $ua);
                return true;
            }
        } else {
            $bl = preg_split('/\r\n|\r|\n/', $cfg['blacklist']);
            foreach ($bl as $b) {
                $b = trim($b);
                if ($b && strpos($ua, strtolower($b)) !== false) {
                    self::logVisit('blocked_blacklist', $ua);
                    return true;
                }
            }
        }

        // 工具型/可疑 UA 自动拦截（无论黑白名单模式都生效）：
        // 这类 UA 几乎不可能是正常浏览器或正规搜索引擎蜘蛛（百度/谷歌/必应等均含 bot/spider 字样，不会命中），
        // 但常被采集器、扫描器、AI 训练爬虫、以及“码云/Gitee”等非常规爬虫使用，故统一拦截。
        if (preg_match(self::$TOOL_UA, $ua)) {
            self::logVisit('blocked_toolua', $ua);
            return true;
        }

        // 频率限制
        if (!empty($cfg['rate_limit']) && (int)$cfg['rate_limit'] > 0) {
            $key = md5(($ua ?: 'empty') . '|' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0'));
            $dir = defined('ROOT_PATH') ? ROOT_PATH . 'data/spiderlog/' : 'data/spiderlog/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $cf = $dir . 'rate_' . $key . '.tmp';
            $now = time();
            $win = 60;
            $arr = is_file($cf) ? @json_decode(@file_get_contents($cf), true) : array();
            if (!is_array($arr)) $arr = array('t' => $now, 'c' => 0);
            if ($now - $arr['t'] > $win) { $arr = array('t' => $now, 'c' => 0); }
            $arr['c']++;
            @file_put_contents($cf, json_encode($arr));
            if ($arr['c'] > (int)$cfg['rate_limit']) {
                self::logVisit('blocked_ratelimit', $ua);
                return true;
            }
        }

        // 记录所有蜘蛛访问（开启了 log_all 时）
        if ($isSpider && !empty($cfg['log_all'])) {
            self::logVisit('visit', $ua);
        }
        return false;
    }

    /**
     * 记录一次蜘蛛访问（visit.log）
     */
    public static function logVisit($reason, $ua){
        $dir = defined('ROOT_PATH') ? ROOT_PATH . 'data/spiderlog/' : 'data/spiderlog/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $line = date('Y-m-d H:i:s') . "\t" . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-')
            . "\t" . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-')
            . "\t" . $reason . "\t" . $ua . "\n";
        @file_put_contents($dir . 'visit.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 记录一次拦截
     */
    public static function logBlock($reason, $ua){
        $dir = defined('ROOT_PATH') ? ROOT_PATH . 'data/spiderlog/' : 'data/spiderlog/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $line = date('Y-m-d H:i:s') . "\t" . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-') . "\t" . $reason . "\t" . $ua . "\n";
        @file_put_contents($dir . 'blocked.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function blockMessage(){
        $msg = '您的访问频率过高或不在允许范围内，已被限制。';
        if (class_exists('\\app\\common\\ConfigStore')) {
            $cfg = \app\common\ConfigStore::load('spider');
            if (!empty($cfg['block_message'])) $msg = $cfg['block_message'];
        }
        return $msg;
    }
}

if (!function_exists('zhi_spider_guard')) {
    function zhi_spider_guard(){
        if (\ZhiCms\ext\SpiderGuard::shouldBlock()) {
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            \ZhiCms\ext\SpiderGuard::logBlock('spider_blocked', $ua);
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>访问受限</title></head><body style="font-family:system-ui,\'Microsoft YaHei\',sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;background:#f5f6fa;"><div style="background:#fff;padding:40px 50px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08);text-align:center;max-width:480px"><h2 style="margin:0 0 12px;color:#2d3748">访问受限</h2><p style="color:#718096;line-height:1.7;margin:0">' . htmlspecialchars(\ZhiCms\ext\SpiderGuard::blockMessage(), ENT_QUOTES) . '</p></div></body></html>';
            exit;
        }
    }
}
