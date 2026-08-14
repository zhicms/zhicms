<?php
/**
 * ZhiCms 站点地图生成（sitemap.xml）
 * 访问 /sitemap.php 即输出标准 XML（Content-Type: application/xml）。
 * 如需标准 .xml 后缀，可在 Nginx 增加：
 *   location = /sitemap.xml { try_files /sitemap.php /sitemap.php; }
 * robots.txt 中已指向本文件。
 */
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$base = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.example.com');
$base = rtrim($base, '/');

$urls = array();
// 始终包含首页与核心频道页
$urls[] = array('loc' => $base . '/', 'changefreq' => 'daily', 'priority' => '1.0');
$channels = array('cheaps', 'brand', 'rank', 'hot', 'forum');
foreach ($channels as $c) {
    $urls[] = array('loc' => $base . '/' . $c . '.html', 'changefreq' => 'daily', 'priority' => '0.8');
}

// 尝试连接数据库补齐动态页
try {
    $cfg = include __DIR__ . '/data/config/db.php';
    $d = $cfg['DB']['default'];
    $dsn = 'mysql:host=' . $d['DB_HOST'] . ';port=' . ($d['DB_PORT'] ?? 3306) . ';dbname=' . $d['DB_NAME'] . ';charset=' . ($d['DB_CHARSET'] ?? 'utf8mb4');
    $pdo = new \PDO($dsn, $d['DB_USER'], $d['DB_PWD'], array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT));
    $pre = $d['DB_PREFIX'];

    // 文章详情
    $stmt = $pdo->query("SELECT `id`,`date` FROM `{$pre}article` WHERE 1 ORDER BY `id` DESC LIMIT 5000");
    if ($stmt) {
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $urls[] = array(
                'loc'       => $base . '/index/index/view/id=' . $r['id'],
                'lastmod'   => date('c', strtotime($r['date'] ?? 'now')),
                'changefreq'=> 'weekly',
                'priority'  => '0.6',
            );
        }
    }

    // 商品详情（buy-<平台>-<id>.html 伪静态）
    $stmt = $pdo->query("SELECT `goodsId`,`item_from` FROM `{$pre}items` WHERE 1 ORDER BY `id` DESC LIMIT 5000");
    if ($stmt) {
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $plat = $r['item_from'] ?: 'tb';
            $gid = $r['goodsId'];
            if (!$gid) continue;
            $urls[] = array(
                'loc'       => $base . '/buy-' . $plat . '-' . $gid . '.html',
                'changefreq'=> 'weekly',
                'priority'  => '0.5',
            );
        }
    }

    // 文章资讯分类（yun_nav，type=0 为网页端资讯分类）
    $stmt = $pdo->query("SELECT `id` FROM `{$pre}nav` WHERE `type` = 0 ORDER BY `id` DESC LIMIT 200");
    if ($stmt) {
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $urls[] = array(
                'loc'       => $base . '/index/index/index/nav=' . $r['id'],
                'changefreq'=> 'daily',
                'priority'  => '0.5',
            );
        }
    }
} catch (\Throwable $e) {
    // 数据库不可用时仅返回频道页，不影响输出
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n";
    if (isset($u['lastmod'])) echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';
exit;
