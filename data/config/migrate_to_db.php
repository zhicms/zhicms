<?php
/**
 * 配置迁移脚本：将 data/config/*.php 中的网站配置导入到 {pre}config 表（JSON 格式）
 * 
 * 使用方式：
 *   浏览器访问：   http://你的域名/index.php?r=manage/set/migrateConfig
 *   或命令行：     php data/config/migrate_to_db.php
 * 
 * 迁移后，原有的 .php 配置文件不会被删除，仍然作为 ConfigStore 的兜底读取。
 * 如果希望完全依赖 DB，可在迁移成功后将原文件移走（建议备份）。
 */

// ===== 命令行模式入口 =====
if (php_sapi_name() === 'cli') {
    $basePath = dirname(dirname(__DIR__)) . '/';
    define('ROOT_PATH', $basePath);
    define('BASE_PATH', $basePath);
    define('CONFIG_PATH', $basePath . 'data/config/');
    
    // 加载数据库配置
    include CONFIG_PATH . 'db.php';
    $dsn = "mysql:host={$db['DB_HOST']};port={$db['DB_PORT']};dbname={$db['DB_NAME']};charset={$db['DB_CHARSET']}";
    try {
        $pdo = new PDO($dsn, $db['DB_USER'], $db['DB_PWD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        die("数据库连接失败: " . $e->getMessage() . "\n");
    }
    
    $tableName = ($db['DB_PREFIX'] ?? 'yun_') . 'config';
    
    // 确保表存在
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$tableName}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `key` varchar(100) NOT NULL DEFAULT '',
        `value` text,
        `desc` varchar(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        UNIQUE KEY `key` (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    printf("开始迁移配置...\n");
    migrateAll($pdo, $tableName);
    printf("迁移完成！\n");
    exit;
}

// ===== Controller 模式入口（由 SetController::migrateConfig() 调用）=====
// 通过 app\common\ConfigStore 使用 DB 连接
function migrate_all_to_db() {
    $result = ['success' => 0, 'skipped' => 0, 'errors' => []];
    
    $groups = [
        'site'       => ['file' => 'siteconfig.php',  'var' => 'Siteinfo', 'desc' => '网站基础配置'],
        'seo'        => ['file' => 'seo.php',         'var' => 'SEO',      'desc' => 'SEO 配置'],
        'api'        => ['file' => 'apiset.php',      'var' => 'api',      'desc' => 'API 密钥配置'],
        'sms'        => ['file' => 'sms.php',         'var' => 'sms',      'desc' => '短信配置'],
        'aichat'     => ['file' => 'aichat.php',      'var' => 'return',   'desc' => 'AI 对话配置'],
        'seopush'    => ['file' => 'seopush.php',     'var' => 'pu',       'desc' => 'SEO 推送配置'],
        'weixin'     => ['file' => 'apicache/weixin.php','var' => 'weixin', 'desc' => '微信配置'],
        'ai'         => ['file' => 'ai.php',          'var' => 'AI',       'desc' => 'AI 开放平台配置'],
        'version'    => ['file' => 'version.php',     'var' => 'v',        'desc' => '版本号'],
    ];
    
    foreach ($groups as $groupName => $info) {
        $filePath = CONFIG_PATH . $info['file'];
        if (!is_file($filePath)) {
            $result['skipped']++;
            continue;
        }
        
        try {
            if ($info['var'] === 'return') {
                $data = include $filePath;
            } else {
                include $filePath;
                $varName = $info['var'];
                $data = $$varName ?? [];
            }
            
            // version 是字符串，转为数组
            if ($groupName === 'version' && is_string($data)) {
                $data = ['version' => $data];
            }
            
            if (is_array($data) && !empty($data)) {
                \app\common\ConfigStore::save($groupName, $data);
                \app\common\ConfigStore::clearCache($groupName);
                $result['success']++;
            } else {
                $result['skipped']++;
            }
        } catch (\Exception $e) {
            $result['errors'][] = "{$groupName}: " . $e->getMessage();
        }
    }
    
    return $result;
}

// ===== CLI 辅助函数 =====
function migrateAll($pdo, $table) {
    $groups = [
        'cfg_site'    => ['file' => 'siteconfig.php',  'var' => 'Siteinfo', 'desc' => '网站基础配置'],
        'cfg_seo'     => ['file' => 'seo.php',         'var' => 'SEO',      'desc' => 'SEO 配置'],
        'cfg_api'     => ['file' => 'apiset.php',      'var' => 'api',      'desc' => 'API 密钥配置'],
        'cfg_sms'     => ['file' => 'sms.php',         'var' => 'sms',      'desc' => '短信配置'],
        'cfg_aichat'  => ['file' => 'aichat.php',      'var' => 'return',   'desc' => 'AI 对话配置'],
        'cfg_seopush' => ['file' => 'seopush.php',     'var' => 'pu',       'desc' => 'SEO 推送配置'],
        'cfg_weixin'  => ['file' => 'apicache/weixin.php','var' => 'weixin', 'desc' => '微信配置'],
        'cfg_ai'      => ['file' => 'ai.php',          'var' => 'AI',       'desc' => 'AI 开放平台配置'],
        'cfg_version' => ['file' => 'version.php',     'var' => 'v',        'desc' => '版本号'],
    ];
    
    $stmt = $pdo->prepare(
        "INSERT INTO `{$table}` (`key`, `value`, `desc`) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `desc` = VALUES(`desc`)"
    );
    
    foreach ($groups as $cfgKey => $info) {
        $filePath = CONFIG_PATH . $info['file'];
        if (!is_file($filePath)) {
            printf("  [跳过] %s (文件不存在)\n", $info['file']);
            continue;
        }
        
        if ($info['var'] === 'return') {
            $data = include $filePath;
        } else {
            include $filePath;
            $varName = $info['var'];
            $data = $$varName ?? [];
        }
        
        // version 是字符串，转为数组
        if ($cfgKey === 'cfg_version' && is_string($data)) {
            $data = ['version' => $data];
        }
        
        if (!is_array($data)) {
            printf("  [跳过] %s (数据非数组)\n", $info['file']);
            continue;
        }
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $stmt->execute([$cfgKey, $json, $info['desc']]);
        printf("  [成功] %s → %s (%d 个配置项)\n", $info['file'], $cfgKey, count($data));
    }
}
