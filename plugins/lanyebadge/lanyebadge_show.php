<?php
!defined('EMLOG_ROOT') && exit('access deined!');
$db = Database::getInstance();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('HTTP/1.1 404 Not Found');
    exit('404 Not Found');
}
$res = $db->query("SELECT svg_content FROM " . DB_PREFIX . "lanyebadge WHERE id = $id");
$row = $db->fetch_array($res);
if (!$row) {
    header('HTTP/1.1 404 Not Found');
    exit('404 Not Found');
}
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $row['svg_content'];