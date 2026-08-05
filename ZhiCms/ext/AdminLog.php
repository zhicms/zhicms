<?php

namespace ZhiCms\ext;

/**
 * 后台操作日志
 * 记录后台关键操作（登录、设置保存、缓存清理、插件启停、内容增删改等），
 * 方便排查问题与审计。日志写入 yun_admin_log 表，表不存在时自动创建。
 */
class AdminLog {

    /** @var string 表名（不带前缀的基准名，运行期通过 realTable 解析为真实表名） */
    private static $baseTable = 'yun_admin_log';

    /** @var array 内存缓存，避免同请求重复建表检测 */
    private static $ensured = false;

    /**
     * 取得真实表名（兼容自定义表前缀）
     * @return string
     */
    private static function table() {
        static $resolved = null;
        if ($resolved === null) {
            try {
                $resolved = obj('api/ApiData')->realTable(self::$baseTable);
            } catch (\Throwable $e) {
                $resolved = self::$baseTable;
            }
        }
        return $resolved;
    }

    /**
     * 写入一条操作日志
     * @param string $type    操作类型，如 login/setting/cache/plugin/user/...
     * @param string $content 操作描述
     * @param string $operator 操作人（不传则尝试从 session 读取）
     * @return bool
     */
    public static function write($type, $content, $operator = null) {
        self::ensureTable();
        try {
            $data = array(
                'type'        => substr($type, 0, 50),
                'content'     => substr($content, 0, 500),
                'operator'    => substr(self::operator($operator), 0, 100),
                'ip'          => self::ip(),
                'url'         => isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], 0, 500) : '',
                'create_time' => time(),
            );
            obj('api/ApiData')->insertData(self::table(), $data);
            return true;
        } catch (\Throwable $e) {
            // 日志写入失败不应影响业务
            return false;
        }
    }

    /**
     * 自动建表（幂等）
     */
    public static function ensureTable() {
        if (self::$ensured) return;
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `" . self::table() . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `type` varchar(50) NOT NULL DEFAULT '',
                `content` varchar(500) NOT NULL DEFAULT '',
                `operator` varchar(100) NOT NULL DEFAULT '',
                `ip` varchar(45) NOT NULL DEFAULT '',
                `url` varchar(500) NOT NULL DEFAULT '',
                `create_time` int(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `type` (`type`),
                KEY `create_time` (`create_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台操作日志'";
            obj('api/ApiData')->executeQuery($sql);
            self::$ensured = true;
        } catch (\Throwable $e) {
            // 建表失败（如数据库不可用）静默跳过
        }
    }

    private static function operator($operator) {
        if ($operator !== null) return $operator;
        if (isset($_SESSION['manage_system'])) return $_SESSION['manage_system'];
        return '';
    }

    private static function ip() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '0.0.0.0';
        }
        return trim($ip);
    }
}

/**
 * 全局便捷函数：记录后台操作日志
 * @param string $type
 * @param string $content
 * @param string|null $operator
 * @return bool
 */
if (!function_exists('admin_log')) {
    function admin_log($type, $content, $operator = null) {
        return \ZhiCms\ext\AdminLog::write($type, $content, $operator);
    }
}
