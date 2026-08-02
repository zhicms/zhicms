<?php
/**
 * ZhiCms 远程一键升级脚本 (最终修复版)
 * 功能：下载 -> 解压(修复路径) -> 覆盖文件 -> 执行SQL -> 稳健更新版本
 */
// 1. 基础配置与安全
set_time_limit(300);
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
ini_set('display_errors', 1);


header("Content-Type: text/html; charset=utf-8");
echo "<html><head><title>系统升级中...</title></head><body><pre>";

// 2. 升级配置
$config = [
    'version'   => '5.0.1', // 目标版本号
    'zipUrl'    => 'https://www.zhi.red/d/update/full_update_5.0.1.zip',
    'sqlFile'   => __DIR__ . '/zhicms_update.sql',
    'tempDir'   => __DIR__ . '/data/remote_update_temp/',
    'zipFile'   => __DIR__ . '/data/remote_update.zip',
    'rootDir'   => __DIR__,
];

echo "=== 开始升级流程 ===\n";
echo "目标版本: {$config['version']}\n";
echo "升级包地址: {$config['zipUrl']}\n\n";

// 3. 下载升级包
echo "[1/5] 正在下载升级包...\n";
if (!is_dir(dirname($config['zipFile']))) {
    mkdir(dirname($config['zipFile']), 0755, true);
}

$content = downloadFile($config['zipUrl'], 300);
if ($content === false) {
    die("❌ 下载失败，请检查网络连接或 URL：{$config['zipUrl']}\n");
}

if (file_put_contents($config['zipFile'], $content) === false) {
    die("❌ 保存压缩包失败，请检查 data 目录权限。\n");
}
echo "✅ 下载完成 (" . round(strlen($content) / 1024 / 1024, 2) . " MB)\n";

// 4. 解压升级包
echo "\n[2/5] 正在解压并修复路径...\n";
if (is_dir($config['tempDir'])) {
    delDirRecursive($config['tempDir']);
}
mkdir($config['tempDir'], 0755, true);

$zip = new ZipArchive();
if ($zip->open($config['zipFile']) !== true) {
    die("❌ 无法打开压缩包。\n");
}

$fileCount = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entryName = $zip->getNameIndex($i);
    $safeName = str_replace('\\', '/', $entryName);
    
    if (substr($safeName, -1) === '/') {
        $dirPath = $config['tempDir'] . $safeName;
        if (!is_dir($dirPath)) mkdir($dirPath, 0755, true);
        continue;
    }
    
    $targetPath = $config['tempDir'] . $safeName;
    if (!is_dir(dirname($targetPath))) mkdir(dirname($targetPath), 0755, true);
    
    $content = $zip->getFromIndex($i);
    if ($content !== false) {
        file_put_contents($targetPath, $content);
        $fileCount++;
    }
}
$zip->close();
unlink($config['zipFile']);
echo "✅ 解压完成，共处理 {$fileCount} 个文件\n";

// 5. 覆盖文件
echo "\n[3/5] 正在覆盖系统文件...\n";
$sourceRoot = findSourceRoot($config['tempDir']);
if (!$sourceRoot) {
    die("❌ 错误：在压缩包中未找到核心目录。\n");
}
$copiedCount = copyFilesSafe($sourceRoot, $config['rootDir']);
echo "✅ 文件覆盖完成，共更新 {$copiedCount} 个文件\n";

// 6. 执行 SQL
echo "\n[4/5] 正在执行数据库更新...\n";
if (file_exists($config['sqlFile'])) {
    try {
        executeDatabaseUpdate($config['sqlFile']);
        echo "✅ 数据库脚本执行成功\n";
    } catch (Exception $e) {
        echo "❌ 数据库更新失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "ℹ️ 未检测到 SQL 脚本，跳过\n";
}

// 7. 更新版本 (核心修复点)
echo "\n[5/5] 正在更新版本标识...\n";
updateVersionConfig($config['version']);

// 8. 清理
delDirRecursive($config['tempDir']);
echo "\n=== 升级全部完成 ===\n";

// 9. 让文件校对基线失效：升级后程序文件已变化，旧基线会导致后台误报“文件被篡改”
$manifest = __DIR__ . '/data/filecheck/manifest.json';
if (is_file($manifest)) {
    @unlink($manifest);
    echo "ℹ️ 已清除旧的文件校对基线，请到【文件校对】点击「建立基线」重新建立（否则会误报文件被篡改）。\n";
}

echo "</pre></body></html>";
echo "</pre></body></html>";

// ================= 辅助函数区 =================

/**
 * 稳健下载：优先 curl（关闭 SSL 校验、跟随跳转、长超时），
 * 回退到 file_get_contents（同样关闭 SSL 校验），并返回文件内容或 false
 */
function downloadFile($url, $timeout = 300) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZhiCmsUpdater');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($data !== false && $code >= 200 && $code < 300 && strlen($data) > 0) {
            return $data;
        }
        error_log("Upgrade download(curl) failed: code=$code err=$err");
    }
    // 回退：file_get_contents（关闭 SSL 校验）
    $ctx = stream_context_create([
        'http'  => ['timeout' => $timeout, 'user_agent' => 'ZhiCmsUpdater', 'follow_location' => true],
        'https' => ['timeout' => $timeout, 'user_agent' => 'ZhiCmsUpdater', 'follow_location' => true,
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return $data === false ? false : $data;
}

function delDirRecursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delDirRecursive($path) : unlink($path);
    }
    rmdir($dir);
}

function findSourceRoot($dir) {
    if (is_dir($dir . '/app') || is_dir($dir . '/application')) return $dir;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $subDir = $dir . '/' . $item;
        if (is_dir($subDir) && (is_dir($subDir . '/app') || is_dir($subDir . '/application'))) {
            return $subDir;
        }
    }
    return null;
}

function copyFilesSafe($src, $dst, &$count = 0) {
    $dh = opendir($src);
    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        
        // 保护本地数据库配置
        if ($file === 'db.php' && strpos($srcPath, '/config/') !== false) continue;
        
        if (is_dir($srcPath)) {
            if (!is_dir($dstPath)) mkdir($dstPath, 0755, true);
            copyFilesSafe($srcPath, $dstPath, $count);
        } else {
            if (!is_dir(dirname($dstPath))) mkdir(dirname($dstPath), 0755, true);
            copy($srcPath, $dstPath);
            $count++;
        }
    }
    closedir($dh);
    return $count;
}

function executeDatabaseUpdate($sqlFile) {
    $dbConfigFile = __DIR__ . '/data/config/db.php';
    if (!file_exists($dbConfigFile)) throw new Exception("DB config missing");
    include $dbConfigFile;
    
    $conf = $db['DB']['default'];
    $dsn = "mysql:host={$conf['DB_HOST']};port={$conf['DB_PORT']};dbname={$conf['DB_NAME']};charset={$conf['DB_CHARSET']}";
    $pdo = new PDO($dsn, $conf['DB_USER'], $conf['DB_PWD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sqlContent = file_get_contents($sqlFile);
    $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
    $sqlContent = preg_replace('/\/\*[\s\S]*?\*\//', '', $sqlContent);
    $statements = array_filter(explode(';', $sqlContent), function($v) { return trim($v) !== ''; });

    // 注意：MySQL 的 ALTER 等 DDL 会隐式提交，事务无法回滚 DDL，
    // 因此改为逐条执行，忽略“列/索引已存在”类错误（SQLSTATE 42S21/42S11），
    // 其余错误仍抛出，避免旧库重复执行升级 SQL 时报 Duplicate column。
    $skippable = array('42S21', '42S11'); // 列已存在 / 键已存在
    $skipped = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (Exception $e) {
            if (in_array($e->getCode(), $skippable, true)) {
                $skipped++;
                continue;
            }
            throw $e;
        }
    }
    if ($skipped > 0) {
        echo "ℹ️ 已跳过 {$skipped} 条“字段/索引已存在”的语句（属正常，可忽略）\n";
    }
}

/**
 * 【核心修复】稳健的版本更新函数
 * 逻辑：先尝试 UPDATE，若受影响行数为 0（说明记录不存在），则执行 INSERT
 */
function updateVersionConfig($version) {
    // 1. 更新本地文件（ConfigStore 兼容格式：$v='版本号';）
    $verFile = __DIR__ . '/data/config/version.php';
    $content = "<?php\n\$v='{$version}';\n";
    file_put_contents($verFile, $content);
    echo "✅ 本地版本文件已更新\n";

    // 2. 更新数据库
    try {
        $dbConfigFile = __DIR__ . '/data/config/db.php';
        if (!file_exists($dbConfigFile)) return;
        include $dbConfigFile;
        
        $conf = $db['DB']['default'];
        $dsn = "mysql:host={$conf['DB_HOST']};port={$conf['DB_PORT']};dbname={$conf['DB_NAME']};charset={$conf['DB_CHARSET']}";
        $pdo = new PDO($dsn, $conf['DB_USER'], $conf['DB_PWD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // 使用 json_encode 确保格式标准，避免手动拼接出错
        $jsonValue = json_encode(['version' => $version], JSON_UNESCAPED_UNICODE);

        // 使用 INSERT ... ON DUPLICATE KEY UPDATE（upsert），原子处理“存在则更新、不存在则插入”，
        // 避免先 UPDATE 再按 rowCount 判断导致的误插（值未变时 rowCount=0 会误判为不存在，引发唯一键冲突）。
        $upsertSql = "INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES ('cfg_version', :val, '版本号') "
            . "ON DUPLICATE KEY UPDATE `value` = :val2";
        $stmt = $pdo->prepare($upsertSql);
        $stmt->execute([':val' => $jsonValue, ':val2' => $jsonValue]);
        echo "✅ 数据库版本记录已更新\n";

    } catch (Exception $e) {
        echo "⚠️ 数据库版本操作失败: " . $e->getMessage() . "\n";
        error_log("Upgrade DB Error: " . $e->getMessage());
    }
}
?>
