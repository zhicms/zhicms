<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class DatabaseController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /** 备份目录（相对站点根） */
    private $bakDir = 'data/dbbak/';

    /**
     * 读取数据库连接配置
     */
    private function dbConfig(){
        include \ROOT_PATH . 'data/config/db.php';
        return $db['DB']['default'];
    }

    /**
     * 建立 PDO 连接
     */
    private function pdo(){
        $c = $this->dbConfig();
        $pdo = new \PDO(
            "mysql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname={$c['DB_NAME']};charset={$c['DB_CHARSET']}",
            $c['DB_USER'],
            $c['DB_PWD'],
            array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
        );
        // 注意：MYSQL_ATTR_USE_BUFFERED_QUERY 必须在连接建立后用 setAttribute 设置，
        // 放在构造器 options 里部分 PHP 版本会抛 “driver does not support setting attributes”。
        if (defined('\\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            @$pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
        return $pdo;
    }

    /**
     * 执行并返回结果（OPTIMIZE/REPAIR 会返回结果集，必须取消费用游标，否则触发 2014 未缓冲查询错误）
     */
    private function runTableSql($pdo, $sql){
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();
        }
        return true;
    }

    public function index(){
        $this->checkManageSession();
        $this->pageText = array('数据库管理');
        $this->toolTitle = '数据库管理';

        $tables = array();
        $totalSize = 0;
        $totalRows = 0;
        try {
            $pdo = $this->pdo();
            $prefix = $this->dbConfig()['DB_PREFIX'];
            $dbName = $this->dbConfig()['DB_NAME'];
            // 仅列出本程序前缀的表（兼容 yun_ 默认前缀）
            $stmt = $pdo->query("SHOW TABLE STATUS FROM `{$dbName}`");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $t = $row['Name'];
                if ($prefix && strpos($t, $prefix) !== 0) continue;
                $dataSize = (int)$row['Data_length'] + (int)$row['Index_length'];
                $totalSize += $dataSize;
                $totalRows += (int)$row['Rows'];
                $tables[] = array(
                    'name'    => $t,
                    'rows'    => (int)$row['Rows'],
                    'engine'  => isset($row['Engine']) ? $row['Engine'] : '',
                    'collation' => isset($row['Collation']) ? $row['Collation'] : '',
                    'size'    => $dataSize,
                    'data_free' => (int)$row['Data_free'],
                    'comment' => isset($row['Comment']) ? $row['Comment'] : '',
                    'status'  => (isset($row['Data_free']) && $row['Data_free'] > 0) ? 'need_optimize' : 'ok',
                );
            }
        } catch (\Throwable $e) {
            $this->dbError = $e->getMessage();
        }
        $this->tables = $tables;
        $this->totalSize = $totalSize;
        $this->totalRows = $totalRows;
        $this->bakList = $this->backupList();
        $this->display();
    }

    /**
     * 备份数据库（全库，输出单一 .sql 文件）
     */
    public function backup(){
        $this->checkManageSession();
        try {
            $pdo = $this->pdo();
            $dbName = $this->dbConfig()['DB_NAME'];
            $dir = \ROOT_PATH . $this->bakDir;
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $tables = array();
            $stmt = $pdo->query('SHOW TABLES');
            while ($row = $stmt->fetch(\PDO::FETCH_NUM)) { $tables[] = $row[0]; }

            $sql = "-- 数据库备份 (ZhiCms)\n";
            $sql .= "-- 数据库: {$dbName}\n";
            $sql .= "-- 生成时间: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- 兼容前缀: " . $this->dbConfig()['DB_PREFIX'] . "\n\n";
            $sql .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql .= $create['Create Table'] . ";\n\n";
                $rows = $pdo->query("SELECT * FROM `{$table}`");
                $cols = $rows->columnCount();
                if ($cols > 0 && $rows->rowCount() > 0) {
                    $sql .= "INSERT INTO `{$table}` VALUES\n";
                    $lines = array();
                    while ($r = $rows->fetch(\PDO::FETCH_NUM)) {
                        $vals = array();
                        foreach ($r as $v) {
                            $vals[] = ($v === null) ? 'NULL' : "'" . str_replace(array("\\", "'", "\n", "\r"), array("\\\\", "\\'", "\\n", "\\r"), $v) . "'";
                        }
                        $lines[] = "(" . implode(',', $vals) . ")";
                    }
                    $sql .= implode(",\n", $lines) . ";\n\n";
                }
            }

            $fileName = 'db_' . date('Ymd_His') . '.sql';
            file_put_contents($dir . $fileName, $sql);
            \ZhiCms\ext\AdminLog::write('database', '备份了数据库（' . count($tables) . ' 张表）');
            exit(json_encode(array('info' => '备份成功：' . $fileName, 'status' => 'y')));
        } catch (\Throwable $e) {
            exit(json_encode(array('info' => '备份失败：' . $e->getMessage(), 'status' => 'n')));
        }
    }

    /**
     * 优化表
     */
    public function optimize(){
        $this->jsonSafe();
        $this->checkManageSession();
        $tables = $this->arg('tables');
        if (empty($tables)) $this->jsonExit('请选择要优化的表', 'n');
        $list = is_array($tables) ? $tables : explode(',', $tables);
        try {
            $pdo = $this->pdo();
            $ok = 0;
            $errs = array();
            foreach ($list as $t) {
                $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
                if (!$t) continue;
                try {
                    $this->runTableSql($pdo, "OPTIMIZE TABLE `{$t}`");
                    $ok++;
                } catch (\Throwable $e) {
                    $errs[] = $t . ':' . $e->getMessage();
                }
            }
            \ZhiCms\ext\AdminLog::write('database', '优化了 ' . $ok . ' 张表');
            if ($ok > 0 && empty($errs)) {
                $this->jsonExit("已优化 {$ok} 张表", 'y');
            }
            $this->jsonExit("已优化 {$ok} 张表" . (empty($errs) ? '' : '；失败：' . implode('，', $errs)), empty($errs) ? 'y' : 'n');
        } catch (\Throwable $e) {
            $this->jsonExit('优化失败：' . $e->getMessage(), 'n');
        }
    }

    /**
     * 修复表
     */
    public function repair(){
        $this->jsonSafe();
        $this->checkManageSession();
        $tables = $this->arg('tables');
        if (empty($tables)) $this->jsonExit('请选择要修复的表', 'n');
        $list = is_array($tables) ? $tables : explode(',', $tables);
        try {
            $pdo = $this->pdo();
            $ok = 0;
            $errs = array();
            foreach ($list as $t) {
                $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
                if (!$t) continue;
                try {
                    $this->runTableSql($pdo, "REPAIR TABLE `{$t}`");
                    $ok++;
                } catch (\Throwable $e) {
                    $errs[] = $t . ':' . $e->getMessage();
                }
            }
            \ZhiCms\ext\AdminLog::write('database', '修复了 ' . $ok . ' 张表');
            if ($ok > 0 && empty($errs)) {
                $this->jsonExit("已修复 {$ok} 张表", 'y');
            }
            $this->jsonExit("已修复 {$ok} 张表" . (empty($errs) ? '' : '；失败：' . implode('，', $errs)), empty($errs) ? 'y' : 'n');
        } catch (\Throwable $e) {
            $this->jsonExit('修复失败：' . $e->getMessage(), 'n');
        }
    }

    /**
     * 注册致命错误兜底：确保任何意外的 fatal 都返回 JSON 而非空白/HTML，
     * 避免前端出现“请求失败，请重试”。
     */
    private function jsonSafe(){
        register_shutdown_function(function(){
            $e = error_get_last();
            if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR), true)) {
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode(array('info' => '服务器错误：' . $e['message'], 'status' => 'n'));
            }
        });
    }

    private function jsonExit($info, $status){
        exit(json_encode(array('info' => $info, 'status' => $status)));
    }

    /**
     * 恢复备份
     */
    public function restore(){
        $this->checkManageSession();
        $file = $this->arg('file', '');
        if (empty($file)) exit(json_encode(array('info' => '请选择备份文件', 'status' => 'n')));
        $file = preg_replace('/[^a-zA-Z0-9_\.\-]/', '', basename($file));
        $path = \ROOT_PATH . $this->bakDir . $file;
        if (!file_exists($path)) exit(json_encode(array('info' => '备份文件不存在', 'status' => 'n')));
        try {
            $pdo = $this->pdo();
            $content = file_get_contents($path);
            // 去除可能的 PHP 防护头
            $content = preg_replace('/^<\?php.*?\?>\s*/', '', $content);
            $statements = $this->parseSql($content);
            $pdo->beginTransaction();
            foreach ($statements as $s) {
                $s = trim($s);
                if ($s === '') continue;
                $pdo->exec($s);
            }
            $pdo->commit();
            \ZhiCms\ext\AdminLog::write('database', '从备份恢复：' . $file);
            exit(json_encode(array('info' => '恢复成功：' . $file, 'status' => 'y')));
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            exit(json_encode(array('info' => '恢复失败：' . $e->getMessage(), 'status' => 'n')));
        }
    }

    /**
     * 删除备份文件
     */
    public function delBak(){
        $this->checkManageSession();
        $file = $this->arg('file', '');
        if (empty($file)) exit(json_encode(array('info' => '请选择备份文件', 'status' => 'n')));
        $file = preg_replace('/[^a-zA-Z0-9_\.\-]/', '', basename($file));
        $path = \ROOT_PATH . $this->bakDir . $file;
        if (!file_exists($path)) exit(json_encode(array('info' => '文件不存在', 'status' => 'n')));
        @unlink($path);
        \ZhiCms\ext\AdminLog::write('database', '删除了备份：' . $file);
        exit(json_encode(array('info' => '已删除', 'status' => 'y')));
    }

    /**
     * 解析 SQL（忽略注释、字符串内分号）
     */
    private function parseSql($content){
        $out = array();
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $sql = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '--') === 0 || strpos($line, '#') === 0) continue;
            $sql .= $line . "\n";
            if (substr($line, -1) === ';') {
                $out[] = trim($sql);
                $sql = '';
            }
        }
        if (trim($sql) !== '') $out[] = trim($sql);
        return $out;
    }

    /**
     * 备份文件列表
     */
    private function backupList(){
        $dir = \ROOT_PATH . $this->bakDir;
        if (!is_dir($dir)) return array();
        $list = array();
        foreach (glob($dir . '*.sql') as $f) {
            $list[] = array(
                'name' => basename($f),
                'size' => filesize($f),
                'time' => filemtime($f),
            );
        }
        usort($list, function($a, $b){ return $b['time'] - $a['time']; });
        return $list;
    }
}
