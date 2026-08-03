<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class IndexController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;
    
    // 备份时排除的目录/文件
    private $excludeFromBackup = [
        'data/config/db.php',
        'data/cache/',
        'runtime/static_cache/',
        'runtime/html/',
        'upload/',
        'mini/',
        'backup/',
        '.codebuddy/',
        '.git/',
    ];
    
    // SQL黑名单 - 禁止执行
    private $forbiddenSqlPatterns = [
        'DROP DATABASE',
        'DROP TABLE',
        'TRUNCATE',
        'DELETE FROM',
        'UPDATE.*SET.*=.*\'\'',
    ];
    
    public function index(){
        // 禁止浏览器缓存本页，避免“在线升级”等按钮的前端脚本被旧缓存命中（导致接口返回数据异常）
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $this->checkManageSession();

        $this->pageText = array("后台首页");

        $this->userCount = obj("api/ApiData")->dataCount("yun_user", array("1"));
        $this->goodsCount = obj("api/ApiData")->dataCount("yun_items", array("1"));
        $this->articleCount = obj("api/ApiData")->dataCount("yun_article", array("1"));
        
        $today = date("Y-m-d");
        $todayWhere[] = "`date` >= '{$today} 00:00:00' AND `date` <= '{$today} 23:59:59'";
        $this->todayCount = obj("api/ApiData")->dataCount("yun_article", $todayWhere);
        
        $goodsTodayWhere[] = "`couponEndTime` >= '{$today}'";
        $this->todayGoodsCount = obj("api/ApiData")->dataCount("yun_items", $goodsTodayWhere);

        $v = \app\common\ConfigStore::load('version', 'version');
        $this->localVersion = $v;

        // 与 FilecheckController 一致：用 Http::doGet 稳健请求更新接口，并容错 JSON 解析
        $updateUrl = 'https://www.zhicms.cc/update_check.php';
        $ret = array();
        $json = \ZhiCms\ext\Http::doGet($updateUrl, 8);
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) $ret = $data;
        }
        
        $this->updateAvailable = false;
        if(isset($ret['version']) && version_compare($ret['version'], $v, '>')){
            $this->updateAvailable = true;
            $this->updateInfo = $ret;
        }

        $this->display();
    }   


   public function delCache(){
       $this->checkManageSession();
        self::clearAllCache();
        \ZhiCms\ext\AdminLog::write('cache', '清理了全站缓存');
        exit(json_encode(array("info" => "清除缓存成功", "status" => "y")));
    }

    /**
     * 一键清理全站缓存：模板缓存、数据库查询缓存、数据缓存、全站静态缓存
     */
    public static function clearAllCache(){
        // 1. data/cache 下所有内容（含子目录 tpl/db 及散落的 php 缓存文件）
        $dataCache = \ROOT_PATH . 'data/cache';
        if (is_dir($dataCache)) {
            self::delDirContents($dataCache);
        }
        // 2. runtime/static_cache 全站静态化缓存
        $staticCacheDir = \BASE_PATH . 'runtime/static_cache';
        if (is_dir($staticCacheDir)) {
            // 优先调用静态缓存插件的清理方法（若已启用）
            $cleared = false;
            if (class_exists('\\plugins\\static_cache\\Plugin')) {
                $plugin = new \plugins\static_cache\Plugin();
                if (method_exists($plugin, 'clearAllCache')) {
                    $plugin->clearAllCache();
                    $cleared = true;
                }
            }
            if (!$cleared) {
                self::delDirContents($staticCacheDir);
            }
        }
        // 3. runtime 下其它缓存目录（cache/log 等，若存在）
        foreach (array('cache', 'log') as $sub) {
            $d = \BASE_PATH . 'runtime/' . $sub;
            if (is_dir($d)) {
                self::delDirContents($d);
            }
        }
        return true;
    }

    /**
     * 删除目录下的所有内容，但保留目录本身（避免后续写入因目录不存在而失败）
     */
    public static function delDirContents($dir){
        if(!is_dir($dir)){
            return false;
        }
        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if($file != "." && $file != "..") {
                $fullpath = $dir . "/" . $file;
                if(!is_dir($fullpath)) {
                    @unlink($fullpath);
                } else {
                    self::delDirContents($fullpath);
                    @rmdir($fullpath);
                }
            }
        }
        closedir($dh);
        return true;
    }

    public function delDir($dir){
        return self::delDirContents($dir);
    }


    /**
     * 整包更新
     */
    public function downloadFile(){
        // 禁止浏览器缓存，避免前端脚本/接口被旧缓存命中导致返回数据异常
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $this->checkManageSession();

        $v = \app\common\ConfigStore::load('version', 'version');

        // 获取更新信息（与 FilecheckController 一致：Http::doGet 稳健请求 + 容错 JSON 解析）
        $updateUrl = 'https://www.zhicms.cc/update_check.php';
        $ret = array();
        $json = \ZhiCms\ext\Http::doGet($updateUrl, 8);
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) $ret = $data;
        }
        
        $zipUrl = isset($ret['full_zip']) ? $ret['full_zip'] : '';
        
        if(empty($zipUrl)){
            exit(json_encode(array("info" => "未获取到升级包地址，请检查服务器外网能否访问更新接口：" . $updateUrl, "status" => "n")));
        }

        // 创建备份目录
        $timestamp = date('Ymd_His');
        $backupDir = \ROOT_PATH . 'backup/full_update_' . $timestamp . '/';
        $zipPath = \ROOT_PATH . 'data/update.zip';
        $tempDir = \ROOT_PATH . 'data/update_temp/';
        $manifestPath = $backupDir . 'manifest.json';

        try{
            // 1. 创建备份目录
            if(!mkdir($backupDir, 0755, true)){
                throw new Exception('创建备份目录失败');
            }
            if(!mkdir($tempDir, 0755, true)){
                throw new Exception('创建临时目录失败');
            }

            // 2. 初始化manifest
            $manifest = [
                'type' => 'full',
                'timestamp' => $timestamp,
                'version' => $v,
                'target_version' => isset($ret['version']) ? $ret['version'] : $v,
                'created_at' => date('Y-m-d H:i:s'),
                'status' => 'preparing',
                'backup_completed' => false,
                'sql_executed' => false,
                'files_updated' => false,
            ];

            // 3. 备份数据库
            $dbBackupPath = $this->backupDatabase($backupDir);
            $manifest['database_backup'] = $dbBackupPath;

            // 4. 备份所有核心文件
            $this->backupAllFiles($backupDir);
            $manifest['backup_completed'] = true;

            // 5. 下载更新包（带超时和 User-Agent）
            $ctx = stream_context_create(array(
                'http' => array('timeout' => 300, 'user_agent' => 'ZhiCmsUpdater'),
                'ssl'  => array('verify_peer' => false, 'verify_peer_name' => false),
            ));
            $zipContent = @file_get_contents($zipUrl, false, $ctx);
            if($zipContent === false){
                throw new Exception('下载升级包失败：' . $zipUrl);
            }
            file_put_contents($zipPath, $zipContent);

            // 6. 解压更新包（逐文件提取 + 路径规范化，避免 ZIP 内反斜杠路径问题）
            $zip = new \ZipArchive;
            if($zip->open($zipPath) !== true){
                throw new Exception('打开压缩包失败');
            }
            $extractCount = 0;
            for($i = 0; $i < $zip->numFiles; $i++){
                $entryName = $zip->getNameIndex($i);
                $safeName = str_replace('\\', '/', $entryName);
                if(substr($safeName, -1) === '/'){
                    $dirPath = $tempDir . $safeName;
                    if(!is_dir($dirPath)){
                        mkdir($dirPath, 0755, true);
                    }
                    continue;
                }
                $targetPath = $tempDir . $safeName;
                if(!is_dir(dirname($targetPath))){
                    mkdir(dirname($targetPath), 0755, true);
                }
                $fileContent = $zip->getFromIndex($i);
                if($fileContent !== false){
                    file_put_contents($targetPath, $fileContent);
                    $extractCount++;
                }
            }
            $zip->close();
            unlink($zipPath);
            if($extractCount === 0){
                throw new Exception('解压后未找到任何文件');
            }

            // 7. 执行SQL（事务保护）
            $this->executeSqlWithTransaction($tempDir);
            $manifest['sql_executed'] = true;

            // 8. 全量覆盖文件
            $this->updateAllFiles($tempDir, \ROOT_PATH);
            $manifest['files_updated'] = true;

            // 9. 更新版本号（数据库 + 文件双重更新）
            $newVersion = isset($ret['version']) ? $ret['version'] : $v;

            // 记录日志便于排查
            error_log("Update: target_version={$newVersion}, current_version={$v}");

            // 先更新 version.php 文件（ConfigStore 兼容格式：$v='版本号';）
            $versionContent = "<?php\n\$v=" . var_export($newVersion, true) . ";\n";
            $writeResult = file_put_contents(\CONFIG_PATH . 'version.php', $versionContent);
            if($writeResult === false){
                throw new Exception('更新版本号文件失败');
            }

            // 使用 ConfigStore 更新数据库（清除缓存后再保存）
            \app\common\ConfigStore::clearCache('version');
            \app\common\ConfigStore::save('version', ['version' => $newVersion]);

            // 验证：从 DB 重新加载确认
            $verifyVersion = \app\common\ConfigStore::load('version', 'version');
            if($verifyVersion !== $newVersion){
                throw new Exception("版本号验证失败：期望 {$newVersion}，实际 {$verifyVersion}");
            }
            
            $manifest['new_version'] = $newVersion;

            // 10. 清理临时文件
            $this->delDir($tempDir);

            // 11. 保存manifest
            $manifest['status'] = 'success';
            $manifest['completed_at'] = date('Y-m-d H:i:s');
            file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            exit(json_encode(array(
                "info" => "整包更新成功，当前版本：{$newVersion}",
                "status" => "y"
            )));

        }catch(Exception $e){
            // 回滚
            $this->rollback($backupDir, $tempDir, $manifestPath);
            exit(json_encode(array("info" => "升级失败：" . $e->getMessage(), "status" => "n")));
        }
    }

    /**
     * 备份所有核心文件
     */
    private function backupAllFiles($backupDir){
        $backupFilesDir = $backupDir . 'files/';
        
        // 递归备份整个网站根目录（排除不需要备份的目录）
        $this->copyDirectoryWithExclude(\ROOT_PATH, $backupFilesDir, true);
    }
    
    /**
     * 备份时排除的路径前缀
     */
    private $backupExcludePrefixes = [
        'backup/',
        'data/cache/',
        'data/log/',
        'runtime/static_cache/',
        'runtime/html/',
        'upload/',
        'mini/',
    ];

    /**
     * 带排除规则的目录复制
     */
    private function copyDirectoryWithExclude($source, $dest, $isRoot = true){
        if(!is_dir($dest)){
            mkdir($dest, 0755, true);
        }
        
        $dh = opendir($source);
        while($file = readdir($dh)){
            if($file == '.' || $file == '..'){
                continue;
            }
            
            $sourcePath = $source . '/' . $file;
            $relativePath = substr($sourcePath, strlen(\ROOT_PATH));
            
            // 检查是否应该排除
            if($this->shouldExclude($relativePath)){
                continue;
            }
            
            $destPath = $dest . '/' . $file;
            
            if(is_dir($sourcePath)){
                $this->copyDirectoryWithExclude($sourcePath, $destPath, false);
            }else{
                copy($sourcePath, $destPath);
            }
        }
        closedir($dh);
    }

    /**
     * 检查路径是否应该排除
     */
    private function shouldExclude($path){
        foreach($this->excludeFromBackup as $pattern){
            if(strpos($pattern, '/') === false && strpos($pattern, '*') === false){
                // 精确文件名匹配
                if(basename($path) === $pattern){
                    return true;
                }
            }elseif(strpos($pattern, '*') !== false){
                // 通配符匹配
                $pattern = str_replace(['*', '/'], ['[^/]*', '\/'], $pattern);
                if(preg_match('/^' . $pattern . '/', $path)){
                    return true;
                }
            }else{
                // 目录前缀匹配
                if(strpos($path, $pattern) === 0){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 备份数据库
     */
    private function backupDatabase($backupDir){
        include \ROOT_PATH . 'data/config/db.php';
        
        $dbConfig = $db['DB']['default'];
        $backupFile = $backupDir . 'database.sql';
        
        try{
            $pdo = new PDO(
                "mysql:host={$dbConfig['DB_HOST']};port={$dbConfig['DB_PORT']};dbname={$dbConfig['DB_NAME']};charset={$dbConfig['DB_CHARSET']}",
                $dbConfig['DB_USER'],
                $dbConfig['DB_PWD']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 获取所有表
            $tables = [];
            $stmt = $pdo->query('SHOW TABLES');
            while($row = $stmt->fetch(PDO::FETCH_NUM)){
                $tables[] = $row[0];
            }
            
            $sqlContent = "-- 数据库备份\n";
            $sqlContent .= "-- 备份时间: " . date('Y-m-d H:i:s') . "\n";
            $sqlContent .= "-- 数据库: {$dbConfig['DB_NAME']}\n\n";
            
            foreach($tables as $table){
                // 创建表结构
                $createSql = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $sqlContent .= "\n-- 表结构: {$table}\n";
                $sqlContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sqlContent .= $createSql['Create Table'] . ";\n";
                
                // 导出数据
                $rows = $pdo->query("SELECT * FROM `{$table}`");
                if($rows->rowCount() > 0){
                    $sqlContent .= "\n-- 数据: {$table}\n";
                    while($row = $rows->fetch(PDO::FETCH_ASSOC)){
                        $values = [];
                        foreach($row as $value){
                            $values[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }
                        $sqlContent .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                    }
                }
            }
            
            file_put_contents($backupFile, $sqlContent);
            return 'database.sql';
            
        }catch(PDOException $e){
            throw new Exception('数据库备份失败: ' . $e->getMessage());
        }
    }

    /**
     * 使用事务执行SQL
     */
    private function executeSqlWithTransaction($tempDir){
        $sqlFiles = ['update.sql', 'data/config/update.sql'];
        
        foreach($sqlFiles as $sqlFile){
            $sqlPath = $tempDir . $sqlFile;
            if(!file_exists($sqlPath)){
                continue;
            }
            
            $sqlContent = file_get_contents($sqlPath);
            $sqlStatements = $this->parseSqlStatements($sqlContent);
            
            if(empty($sqlStatements)){
                continue;
            }
            
            include \ROOT_PATH . 'data/config/db.php';
            $dbConfig = $db['DB']['default'];
            
            try{
                $pdo = new PDO(
                    "mysql:host={$dbConfig['DB_HOST']};port={$dbConfig['DB_PORT']};dbname={$dbConfig['DB_NAME']};charset={$dbConfig['DB_CHARSET']}",
                    $dbConfig['DB_USER'],
                    $dbConfig['DB_PWD']
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // 开始事务
                $pdo->beginTransaction();
                
                foreach($sqlStatements as $sql){
                    $sql = trim($sql);
                    if(empty($sql) || $this->isForbiddenSql($sql)){
                        continue;
                    }
                    $pdo->exec($sql);
                }
                
                // 提交事务
                $pdo->commit();
                
            }catch(PDOException $e){
                if(isset($pdo) && $pdo->inTransaction()){
                    $pdo->rollBack();
                }
                throw new Exception('SQL执行失败: ' . $e->getMessage());
            }
            
            // 删除已执行的SQL文件
            unlink($sqlPath);
        }
    }

    /**
     * 解析SQL语句（支持注释 + 字符串上下文跟踪，避免字符串内的分号被误切）
     */
    private function parseSqlStatements($content){
        $statements = [];
        $len = strlen($content);
        $i = 0;
        $current = '';
        $inString = false;     // 是否在字符串内
        $stringChar = '';      // 当前字符串的引号类型：' " `
        $escaped = false;      // 前一个字符是否为反斜杠转义

        while($i < $len){
            $char = $content[$i];

            // 在字符串内：只关注转义和闭合引号
            if($inString){
                $current .= $char;
                if($escaped){
                    $escaped = false;
                } elseif($char === '\\'){
                    $escaped = true;
                } elseif($char === $stringChar){
                    $inString = false;
                    $stringChar = '';
                }
                $i++;
                continue;
            }

            // 不在字符串内：处理注释
            if($char == '#'){
                while($i < $len && $content[$i] != "\n"){
                    $i++;
                }
                $i++;
                continue;
            }
            if($char == '-' && $i + 1 < $len && $content[$i+1] == '-'){
                $i += 2;
                while($i < $len && $content[$i] != "\n"){
                    $i++;
                }
                $i++;
                continue;
            }
            if($char == '/' && $i + 1 < $len && $content[$i+1] == '*'){
                $i += 2;
                while($i + 1 < $len){
                    if($content[$i] == '*' && $content[$i+1] == '/'){
                        $i += 2;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            // 检测字符串开始
            if($char === "'" || $char === '"' || $char === '`'){
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                $i++;
                continue;
            }

            $current .= $char;

            // 分号结束语句（仅在非字符串内）
            if($char == ';'){
                $stmt = trim(substr($current, 0, -1));
                if(!empty($stmt)){
                    $statements[] = $stmt;
                }
                $current = '';
            }
            $i++;
        }

        // 处理最后一条语句
        $stmt = trim($current);
        if(!empty($stmt)){
            $statements[] = $stmt;
        }

        return $statements;
    }

    /**
     * 检查SQL是否在黑名单中
     */
    private function isForbiddenSql($sql){
        foreach($this->forbiddenSqlPatterns as $pattern){
            if(preg_match('/' . $pattern . '/i', $sql)){
                return true;
            }
        }
        return false;
    }

    /**
     * 全量更新文件
     */
    private function updateAllFiles($sourceDir, $destDir){
        // 查找实际的代码根目录（跳过单文件或空目录，找到包含 app/index.php 等的目录）
        $actualSourceDir = $this->findActualSourceDir($sourceDir);
        
        if($actualSourceDir === null){
            throw new Exception('无法找到更新包中的代码目录');
        }
        
        $this->copyUpdateFilesRecursive($actualSourceDir, $destDir);
    }
    
    /**
     * 查找实际的源代码根目录
     * 处理 zip 包可能有多层嵌套的情况
     */
    private function findActualSourceDir($dir){
        // 如果直接包含 app 目录，这就是根目录
        if(is_dir($dir . '/app')){
            return $dir;
        }

        // 否则遍历找第一层包含 app 的子目录
        $dh = opendir($dir);
        while($file = readdir($dh)){
            if($file == '.' || $file == '..') continue;

            $subDir = $dir . '/' . $file;
            if(is_dir($subDir) && is_dir($subDir . '/app')){
                closedir($dh);
                return $subDir;
            }
        }
        closedir($dh);
        
        return null;
    }

    /**
     * 递归复制更新文件
     */
    private function copyUpdateFilesRecursive($source, $dest){
        if(!is_dir($dest)){
            mkdir($dest, 0755, true);
        }
        
        $dh = opendir($source);
        while($file = readdir($dh)){
            if($file == '.' || $file == '..'){
                continue;
            }
            
            $sourcePath = $source . '/' . $file;
            $relativePath = substr($sourcePath, strlen($source) + 1);
            $destPath = $dest . '/' . $file;
            
            // 跳过db.php
            if($relativePath === 'data/config/db.php'){
                continue;
            }
            
            if(is_dir($sourcePath)){
                $this->copyUpdateFilesRecursive($sourcePath, $destPath);
            }else{
                $destDirPath = dirname($destPath);
                if(!is_dir($destDirPath)){
                    mkdir($destDirPath, 0755, true);
                }
                
                // 配置文件需要智能合并
                if($this->isConfigFile($relativePath)){
                    $this->mergeConfigFile($sourcePath, $destPath);
                }else{
                    copy($sourcePath, $destPath);
                }
            }
        }
        closedir($dh);
    }

    /**
     * 检查是否是配置文件
     */
    private function isConfigFile($relativePath){
        $configFiles = [
            'data/config/siteconfig.php',
            'data/config/seo.php',
            'data/config/sms.php',
            'data/config/apiset.php',
            'data/config/rule.php',
            'data/config/global.php',
        ];
        return in_array($relativePath, $configFiles);
    }

    /**
     * 智能合并配置文件
     */
    private function mergeConfigFile($source, $dest){
        if(!file_exists($dest)){
            $destDir = dirname($dest);
            if(!is_dir($destDir)){
                mkdir($destDir, 0755, true);
            }
            copy($source, $dest);
            return;
        }

        include $dest;
        $oldConfig = $this->getConfigVariable($dest);
        
        include $source;
        $newConfig = $this->getConfigVariable($source);

        if($oldConfig && $newConfig){
            // 旧值覆盖新值（用户设置优先）
            $mergedConfig = array_merge($newConfig, $oldConfig);
            $varName = $this->getConfigVarName($dest);
            $content = "<?php\n\${$varName}=" . var_export($mergedConfig, true) . ";\n";
            file_put_contents($dest, $content);
        }else{
            copy($source, $dest);
        }
    }

    /**
     * 获取配置文件变量
     */
    private function getConfigVariable($filePath){
        $content = file_get_contents($filePath);
        if(preg_match('/\$(\w+)\s*=\s*array\(/', $content, $matches)){
            $varName = $matches[1];
            include $filePath;
            return $$varName;
        }
        return null;
    }

    /**
     * 获取配置文件变量名
     */
    private function getConfigVarName($filePath){
        $content = file_get_contents($filePath);
        if(preg_match('/\$(\w+)\s*=\s*array\(/', $content, $matches)){
            return $matches[1];
        }
        return 'config';
    }

    /**
     * 回滚操作
     */
    private function rollback($backupDir, $tempDir, $manifestPath){
        $manifest = [];
        if(file_exists($manifestPath)){
            $manifest = json_decode(file_get_contents($manifestPath), true);
        }
        
        try{
            // 1. 还原文件
            $backupFilesDir = $backupDir . 'files/';
            if(is_dir($backupFilesDir)){
                $this->restoreFilesRecursive($backupFilesDir, \ROOT_PATH);
            }
            
            // 2. 还原数据库
            if(!empty($manifest['database_backup']) && file_exists($backupDir . $manifest['database_backup'])){
                $this->restoreDatabase($backupDir . $manifest['database_backup']);
            }
            
            // 3. 更新manifest状态
            if(file_exists($manifestPath)){
                $manifest['status'] = 'rollback';
                $manifest['rollback_at'] = date('Y-m-d H:i:s');
                file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            
            // 4. 清理临时文件
            if(is_dir($tempDir)){
                $this->delDir($tempDir);
            }
            if(file_exists(\ROOT_PATH . 'data/update.zip')){
                unlink(\ROOT_PATH . 'data/update.zip');
            }
            
        }catch(Exception $e){
            error_log('Rollback failed: ' . $e->getMessage());
        }
    }

    /**
     * 还原文件
     */
    private function restoreFilesRecursive($source, $dest){
        if(!is_dir($dest)){
            mkdir($dest, 0755, true);
        }
        
        $dh = opendir($source);
        while($file = readdir($dh)){
            if($file == '.' || $file == '..'){
                continue;
            }
            
            $sourcePath = $source . '/' . $file;
            $destPath = $dest . '/' . $file;
            
            if(is_dir($sourcePath)){
                $this->restoreFilesRecursive($sourcePath, $destPath);
            }else{
                copy($sourcePath, $destPath);
            }
        }
        closedir($dh);
    }

    /**
     * 还原数据库
     */
    private function restoreDatabase($backupFile){
        include \ROOT_PATH . 'data/config/db.php';
        $dbConfig = $db['DB']['default'];
        
        try{
            $pdo = new PDO(
                "mysql:host={$dbConfig['DB_HOST']};port={$dbConfig['DB_PORT']};dbname={$dbConfig['DB_NAME']};charset={$dbConfig['DB_CHARSET']}",
                $dbConfig['DB_USER'],
                $dbConfig['DB_PWD']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sqlContent = file_get_contents($backupFile);
            $statements = $this->parseSqlStatements($sqlContent);
            
            foreach($statements as $sql){
                $sql = trim($sql);
                if(!empty($sql)){
                    $pdo->exec($sql);
                }
            }
            
        }catch(PDOException $e){
            throw new Exception('数据库还原失败: ' . $e->getMessage());
        }
    }

}
