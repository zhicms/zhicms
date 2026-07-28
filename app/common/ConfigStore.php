<?php

namespace app\common;

/**
 * 配置存储服务
 * 
 * 将所有非框架级网站配置统一存入 {pre}config 表（JSON 格式），
 * 替代原先的 data/config/*.php 文件读写方式。
 * 
 * 表结构（复用已有）：
 *   `key`   VARCHAR(100)  配置组名：cfg_site / cfg_seo / cfg_api / cfg_sms / ...
 *   `value` TEXT          整组配置的 JSON
 *   `desc`  VARCHAR(255)  描述
 * 
 * 安全收益：API 密钥不再以明文 PHP 文件暴露在 storage/config 目录下。
 * 
 * 使用方式：
 *   ConfigStore::load('site')              → 返回整个 site 配置数组
 *   ConfigStore::load('site', 'hosturl')   → 返回 hosturl 的单一值
 *   ConfigStore::save('site', $data)       → 保存整组配置
 *   ConfigStore::getAll()                  → 返回所有配置（{group => [...], ...}）
 */
class ConfigStore {

    /** @var array 运行时内存缓存（避免同请求重复查 DB） */
    private static $cache = array();

    /** @var array 配置组映射：前缀 → 描述 */
    private static $groups = array(
        'cfg_site'    => '网站基础配置',
        'cfg_seo'     => 'SEO 配置',
        'cfg_api'     => 'API 密钥配置',
        'cfg_sms'     => '短信配置',
        'cfg_aichat'  => 'AI 对话配置',
        'cfg_seopush' => 'SEO 推送配置',
        'cfg_weixin'  => '微信配置',
        'cfg_ai'      => 'AI 开放平台配置',
        'cfg_version' => '版本号',
    );

    /**
     * 确保数据表存在（如不存在则自动建表）
     */
    private static function ensureTable() {
        try {
            obj("api/ApiData")->executeQuery(
                "CREATE TABLE IF NOT EXISTS `{pre}config` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `key` varchar(100) NOT NULL DEFAULT '',
                  `value` text,
                  `desc` varchar(255) NOT NULL DEFAULT '',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `key` (`key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\Exception $e) {
            // 数据库不可用时静默跳过（安装/迁移阶段）
        }
    }

    /**
     * 加载一组配置
     * @param  string      $group 配置组名（不带 cfg_ 前缀也可，自动补全）
     * @param  string|null $key   可选：只取某个字段
     * @return mixed              整组数组 / 单一值 / null
     */
    public static function load($group, $key = null) {
        $group = self::normalizeKey($group);

        // 内存缓存命中
        if (isset(self::$cache[$group])) {
            return $key !== null
                ? (isset(self::$cache[$group][$key]) ? self::$cache[$group][$key] : null)
                : self::$cache[$group];
        }

        self::ensureTable();

        try {
            $rows = obj("api/ApiData")->thisQuery(
                "SELECT `value` FROM `{pre}config` WHERE `key` = ?",
                array($group)
            );
        } catch (\Exception $e) {
            $rows = array();
        }

        if (!empty($rows) && !empty($rows[0]['value'])) {
            $data = json_decode($rows[0]['value'], true);
            if (is_array($data)) {
                self::$cache[$group] = $data;
                return $key !== null
                    ? (isset($data[$key]) ? $data[$key] : null)
                    : $data;
            }
        }

        // DB 中不存在 → 尝试从旧文件读取并自动迁移
        $fileData = self::loadFromFile($group);
        if ($fileData !== null) {
            self::$cache[$group] = $fileData;
            // 静默写入 DB（自动迁移）
            self::save($group, $fileData, true);
            return $key !== null
                ? (isset($fileData[$key]) ? $fileData[$key] : null)
                : $fileData;
        }

        self::$cache[$group] = array();
        return $key !== null ? null : array();
    }

    /**
     * 保存整组配置到 DB（upsert）
     * @param string $group   配置组名
     * @param array  $data    配置数组
     * @param bool   $silent  是否静默（避免递归触发文件回退）
     */
    public static function save($group, array $data, $silent = false) {
        $group = self::normalizeKey($group);
        $json  = json_encode($data, JSON_UNESCAPED_UNICODE);
        $desc  = self::$groups[$group] ?? '';

        // 更新内存缓存
        self::$cache[$group] = $data;

        self::ensureTable();

        try {
            obj("api/ApiData")->executeQuery(
                "INSERT INTO `{pre}config` (`key`, `value`, `desc`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `desc` = VALUES(`desc`)",
                array($group, $json, $desc)
            );
        } catch (\Exception $e) {
            // 保存失败时回退到文件写入
            if (!$silent) {
                self::saveToFile($group, $data);
            }
        }
    }

    /**
     * 加载所有已存储的配置组
     * @return array ['site' => [...], 'seo' => [...], ...]
     */
    public static function getAll() {
        $result = array();
        foreach (self::$groups as $cfgKey => $desc) {
            $plainGroup = str_replace('cfg_', '', $cfgKey);
            $data = self::load($plainGroup);
            if (!empty($data)) {
                $result[$plainGroup] = $data;
            }
        }
        return $result;
    }

    /**
     * 清空内存缓存（用于设置保存后强制重载）
     * @param string|null $group 指定组，null 则清空全部
     */
    public static function clearCache($group = null) {
        if ($group !== null) {
            $group = self::normalizeKey($group);
            unset(self::$cache[$group]);
        } else {
            self::$cache = array();
        }
    }

    // ======================= 私有方法 =======================

    /**
     * 标准化 key：不带前缀时自动补 cfg_
     */
    private static function normalizeKey($group) {
        if (strpos($group, 'cfg_') !== 0) {
            return 'cfg_' . $group;
        }
        return $group;
    }

    /**
     * 从旧 PHP 配置文件读取（兜底）
     */
    private static function loadFromFile($group) {
        $fileMap = array(
            'cfg_site'    => 'siteconfig.php',
            'cfg_seo'     => 'seo.php',
            'cfg_api'     => 'apiset.php',
            'cfg_sms'     => 'sms.php',
            'cfg_aichat'  => 'aichat.php',
            'cfg_seopush' => 'seopush.php',
            'cfg_weixin'  => 'apicache/weixin.php',
            'cfg_ai'      => 'ai.php',
            'cfg_version' => 'version.php',
        );

        $file = $fileMap[$group] ?? '';
        if (!$file || !is_file(CONFIG_PATH . $file)) {
            return null;
        }

        // 每个文件定义不同的全局变量，需要单独处理
        switch ($group) {
            case 'cfg_site':
                include CONFIG_PATH . $file;
                return isset($Siteinfo) ? $Siteinfo : null;
            case 'cfg_seo':
                include CONFIG_PATH . $file;
                return isset($SEO) ? $SEO : null;
            case 'cfg_api':
                include CONFIG_PATH . $file;
                return isset($api) ? $api : null;
            case 'cfg_sms':
                include CONFIG_PATH . $file;
                return isset($sms) ? $sms : null;
            case 'cfg_aichat':
                $cfg = include CONFIG_PATH . $file;
                return is_array($cfg) ? $cfg : null;
            case 'cfg_seopush':
                include CONFIG_PATH . $file;
                return isset($pu) ? $pu : null;
            case 'cfg_weixin':
                include CONFIG_PATH . $file;
                return isset($weixin) ? $weixin : null;
            case 'cfg_ai':
                include CONFIG_PATH . $file;
                return isset($AI) ? $AI : null;
            case 'cfg_version':
                include CONFIG_PATH . $file;
                return isset($v) ? array('version' => $v) : null;
            default:
                return null;
        }
    }

    /**
     * 保存到旧 PHP 配置文件（DB 不可用时的兜底）
     */
    private static function saveToFile($group, array $data) {
        $fileMap = array(
            'cfg_site'    => ['file' => 'siteconfig.php', 'var' => '$Siteinfo'],
            'cfg_seo'     => ['file' => 'seo.php',       'var' => '$SEO'],
            'cfg_api'     => ['file' => 'apiset.php',    'var' => '$api'],
            'cfg_sms'     => ['file' => 'sms.php',       'var' => '$sms'],
            'cfg_aichat'  => ['file' => 'aichat.php',    'var' => 'return'],
            'cfg_seopush' => ['file' => 'seopush.php',   'var' => '$pu'],
            'cfg_weixin'  => ['file' => 'apicache/weixin.php', 'var' => '$weixin'],
            'cfg_ai'      => ['file' => 'ai.php',        'var' => '$AI'],
            'cfg_version' => ['file' => 'version.php',   'var' => '$v', 'scalar' => 'version'],
        );

        $info = $fileMap[$group] ?? null;
        if (!$info) return;

        // 纯量值配置项（如 version）
        if (isset($info['scalar']) && isset($data[$info['scalar']])) {
            $value = $data[$info['scalar']];
            $content = "<?php\r\n{$info['var']}=" . var_export($value, true) . ";\n";
        } elseif ($info['var'] === 'return') {
            $content = "<?php\r\nreturn " . var_export($data, true) . ";\n";
        } else {
            $content = "<?php\r\n{$info['var']}=" . var_export($data, true) . ";\n";
        }

        $of = fopen(CONFIG_PATH . $info['file'], 'w');
        if ($of) {
            fwrite($of, $content);
            fclose($of);
        }
    }
}
