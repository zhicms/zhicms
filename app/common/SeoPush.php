<?php

namespace app\common;

/**
 * SEO 推送引擎
 * 
 * 对接各大搜索引擎的 URL 提交 API，主动推送网站链接以加速收录。
 * 支持平台：
 *   - 百度站长平台（普通收录 + 快速收录）
 *   - Bing Webmaster API
 *   - 微博开放平台（社交信号）
 * 
 * 使用方式：
 *   $push = new SeoPush();
 *   $push->push('http://example.com/article/123.html');
 *   $push->pushBatch(['url1', 'url2', 'url3']);
 *   $push->pushRecentArticles($limit);   // 推送最近N篇文章
 *   $push->pushAll();                     // 推送全站核心页面
 */
class SeoPush {

    /** @var array 推送配置（令牌、开关等） */
    private $config;

    /** @var string 站点域名（hosturl，用于补全相对路径） */
    private $siteUrl;

    /** @var string 站点名称 */
    private $siteName;

    /** @var array 推送结果汇总 */
    private $results = array();

    public function __construct() {
        $this->config = \app\common\ConfigStore::load('seopush');
        $this->siteUrl = rtrim(obj('base/Base')->SiteConfig('hosturl'), '/');
        $this->siteName = obj('base/Base')->SiteConfig('sitename');
    }

    /**
     * 推送单个 URL 到所有已启用的平台
     * @param string $url 完整 URL 或相对路径
     * @return array 各平台推送结果
     */
    public function push($url) {
        $url = $this->normalizeUrl($url);
        $this->results = array(
            'url'     => $url,
            'time'    => date('Y-m-d H:i:s'),
            'results' => array(),
        );

        // 百度普通收录
        if (!empty($this->config['baidu_token']) && $this->config['baidu_enabled'] == '1') {
            $this->results['results']['baidu'] = $this->pushToBaidu($url);
        }

        // Bing
        if (!empty($this->config['bing_apikey']) && $this->config['bing_enabled'] == '1') {
            $this->results['results']['bing'] = $this->pushToBing($url);
        }

        // 微博（需 token 且已启用）
        if (!empty($this->config['weibo_token']) && $this->config['weibo_enabled'] == '1') {
            $this->results['results']['weibo'] = $this->pushToWeibo($url);
        }

        // 写入日志
        $this->writeLog();
        return $this->results;
    }

    /**
     * 批量推送 URL 列表
     * @param array $urls URL 数组
     * @return array 各平台总结果
     */
    public function pushBatch(array $urls) {
        $normalized = array_map(array($this, 'normalizeUrl'), $urls);
        $summary = array(
            'total'   => count($normalized),
            'time'    => date('Y-m-d H:i:s'),
            'urls'    => $normalized,
            'results' => array(),
        );

        // 百度：支持 POST 纯文本批量提交（一行一条，最多2000条）
        if (!empty($this->config['baidu_token']) && $this->config['baidu_enabled'] == '1') {
            $summary['results']['baidu'] = $this->pushToBaiduBatch($normalized);
        }

        // Bing：逐条或批量（API 限制一次最多 100 条）
        if (!empty($this->config['bing_apikey']) && $this->config['bing_enabled'] == '1') {
            $summary['results']['bing'] = $this->pushToBingBatch($normalized);
        }

        // 微博
        if (!empty($this->config['weibo_token']) && $this->config['weibo_enabled'] == '1') {
            $summary['results']['weibo'] = $this->pushToWeiboBatch($normalized);
        }

        $this->results = $summary;
        $this->writeLog();
        return $summary;
    }

    /**
     * 推送最近 N 篇文章（按 id DESC）
     * @param int $limit 文章数量，默认 100
     * @return array
     */
    public function pushRecentArticles($limit = 100) {
        $rows = obj("api/ApiData")->thisQuery(
            "SELECT `id`,`title` FROM `{pre}article` ORDER BY `id` DESC LIMIT " . intval($limit)
        );
        if (empty($rows)) {
            return array('error' => '没有可推送的文章', 'total' => 0);
        }

        $urls = array();
        foreach ($rows as $row) {
            $urls[] = $this->siteUrl . '/view-' . $row['id'] . '.html';
        }
        return $this->pushBatch($urls);
    }

    /**
     * 推送全站核心页面（首页 + 栏目 + 最新文章）
     * @return array
     */
    public function pushAll() {
        $urls = array();

        // 首页
        $urls[] = $this->siteUrl . '/index.html';

        // 核心栏目页
        $landingPages = array('brand.html', 'cheaps.html', 'rank.html', 'index.php?r=index/forum/index');
        foreach ($landingPages as $p) {
            $urls[] = $this->siteUrl . '/' . $p;
        }

        // 最近 50 篇文章
        $rows = obj("api/ApiData")->thisQuery(
            "SELECT `id` FROM `{pre}article` ORDER BY `id` DESC LIMIT 50"
        );
        if ($rows) {
            foreach ($rows as $row) {
                $urls[] = $this->siteUrl . '/view-' . $row['id'] . '.html';
            }
        }

        return $this->pushBatch($urls);
    }

    /**
     * 供后台手动调用：读取 POST 中的 urls 字段并推送
     * @return array
     */
    public function pushUrls() {
        $manual = isset($_POST['urls']) ? trim($_POST['urls']) : '';
        if ($manual === '') {
            return array('info' => '请输入要推送的URL', 'status' => 'n');
        }

        $urls = preg_split('/[\r\n]+/', $manual);
        $urls = array_filter(array_map('trim', $urls));

        if (empty($urls)) {
            return array('info' => '请输入有效的URL', 'status' => 'n');
        }

        $result = $this->pushBatch($urls);
        $success = 0; $fail = 0;
        foreach ($result['results'] as $plat => $r) {
            if (isset($r['success'])) $success += $r['success'];
            if (isset($r['remain'])) $fail += $r['remain'];
        }

        return array(
            'info'   => "推送完成：成功 {$success}，剩余 {$fail}（详情见日志）",
            'status' => 'y',
            'detail' => $result['results'],
        );
    }

    // ==================== 私有方法 ====================

    /**
     * 标准化 URL：补全域名
     */
    private function normalizeUrl($url) {
        $url = trim($url);
        if (strpos($url, 'http') !== 0) {
            $url = $this->siteUrl . '/' . ltrim($url, '/');
        }
        return $url;
    }

    /**
     * 百度站长平台 - 普通收录 API（单条）
     * POST text/plain，一行一条 URL，返回 JSON
     * API: http://data.zz.baidu.com/urls?site=SITE&token=TOKEN
     */
    private function pushToBaidu($url) {
        $api = 'http://data.zz.baidu.com/urls?site=' . urlencode($this->siteUrl) . '&token=' . $this->config['baidu_token'];
        return $this->httpPost($api, $url, 'text/plain');
    }

    /**
     * 百度站长平台 - 普通收录 API（批量）
     * 一行一条 URL，最多 2000 条
     */
    private function pushToBaiduBatch(array $urls) {
        $api = 'http://data.zz.baidu.com/urls?site=' . urlencode($this->siteUrl) . '&token=' . $this->config['baidu_token'];
        $body = implode("\n", array_slice($urls, 0, 2000));
        return $this->httpPost($api, $body, 'text/plain');
    }

    /**
     * Bing Webmaster IndexNow API（单条）
     * POST https://ssl.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey=KEY
     * Body: {"siteUrl":"...", "urlList":["..."]}
     */
    private function pushToBing($url) {
        $api = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey=' . urlencode($this->config['bing_apikey']);
        $body = json_encode(array(
            'siteUrl'  => $this->siteUrl,
            'urlList'  => array($url),
        ));
        return $this->httpPost($api, $body, 'application/json; charset=utf-8');
    }

    /**
     * Bing Webmaster IndexNow API（批量，一次最多 100 条）
     */
    private function pushToBingBatch(array $urls) {
        $api = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey=' . urlencode($this->config['bing_apikey']);
        $chunks = array_chunk($urls, 100);
        $totalResult = array('success' => 0, 'remain' => 0);

        foreach ($chunks as $chunk) {
            $body = json_encode(array(
                'siteUrl' => $this->siteUrl,
                'urlList' => $chunk,
            ));
            $res = $this->httpPost($api, $body, 'application/json; charset=utf-8');
            if ($res['code'] == 200) {
                $totalResult['success'] += count($chunk);
            } else {
                $totalResult['remain'] += count($chunk);
            }
        }
        return $totalResult;
    }

    /**
     * 微博开放平台 - 收录信号（非官方 API，基于开放平台接口）
     * 
     * 微博不提供直接的 URL 推送 API，但可以通过以下方式间接向搜索引擎发送社交信号：
     * 1. 写入本地 sitemap 供搜索引擎抓取
     * 2. 访问微博短链生成 API（需要 token）
     * 
     * 此处实现：生成 sitemap 标记 + 发送 ping 到搜索引擎
     */
    private function pushToWeibo($url) {
        // 微博平台不提供直接的 URL 推送，通过 ping 各大搜索引擎实现
        return $this->pingSearchEngines($url);
    }

    private function pushToWeiboBatch(array $urls) {
        $results = array('success' => 0, 'remain' => 0);
        // 写 sitemap
        if (!empty($this->config['weibo_token'])) {
            try {
                $this->writeSitemap($urls);
                $results['success'] += count($urls);
            } catch (\Exception $e) {
                $results['remain'] += count($urls);
            }
        }
        return $results;
    }

    /**
     * Ping 搜索引擎（通知有内容更新）
     */
    private function pingSearchEngines($url) {
        $pingUrls = array(
            'http://ping.baidu.com/sitemap/map?url=' . urlencode($url),
            'http://www.google.com/ping?sitemap=' . urlencode($url),
        );
        $results = array();
        foreach ($pingUrls as $pingUrl) {
            $results[] = $this->httpGet($pingUrl);
        }
        return array('ping_count' => count($pingUrls), 'results' => $results);
    }

    /**
     * 生成 / 更新 sitemap.txt（纯文本格式，一行一条 URL）
     */
    private function writeSitemap(array $urls) {
        $sitemapPath = ROOT_PATH . 'sitemap.txt';
        $existing = is_file($sitemapPath) ? file($sitemapPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
        $merged = array_unique(array_merge($existing, $urls));
        // 保留最新 50000 条（百度上限）
        $merged = array_slice($merged, -50000);
        file_put_contents($sitemapPath, implode("\n", $merged) . "\n");
        // 同时生成 XML 格式
        $this->writeSitemapXml($merged);
    }

    /**
     * 生成 sitemap.xml
     */
    private function writeSitemapXml(array $urls) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xml .= '    <changefreq>daily</changefreq>' . "\n";
            $xml .= '    <priority>0.8</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        $xml .= '</urlset>';
        file_put_contents(ROOT_PATH . 'sitemap.xml', $xml);
    }

    /**
     * 写入推送日志（JSON Lines，追加模式，保留最近 200 条）
     */
    private function writeLog() {
        $logFile = CONFIG_PATH . 'seopush_log.json';
        $logs = array();
        if (is_file($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true);
            if (!is_array($logs)) $logs = array();
        }

        // 新记录插到开头
        array_unshift($logs, $this->results);
        $logs = array_slice($logs, 0, 200);
        file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * HTTP POST 请求
     */
    private function httpPost($url, $body, $contentType = 'text/plain') {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: ' . $contentType,
                'User-Agent: ZhiCms-SeoPush/1.0',
            ),
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        $result = array('code' => (int)$httpCode, 'raw' => $response);
        if ($error) {
            $result['error'] = $error;
        }

        // 解析百度返回
        if (strpos($url, 'baidu.com') !== false && $httpCode == 200) {
            $json = json_decode($response, true);
            if ($json) {
                $result['success'] = isset($json['success']) ? (int)$json['success'] : 0;
                $result['remain']  = isset($json['remain']) ? (int)$json['remain'] : 0;
                $result['message'] = $json['message'] ?? '';
            }
        }

        // 解析 Bing 返回
        if (strpos($url, 'bing.com') !== false && ($httpCode == 200 || $httpCode == 202)) {
            $result['success'] = 1;
        }

        return $result;
    }

    /**
     * HTTP GET 请求（用于 ping）
     */
    private function httpGet($url) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'ZhiCms-SeoPush/1.0',
        ));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array('code' => $httpCode);
    }
}
