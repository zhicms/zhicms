<?php
namespace ZhiCms\base\compat;

use ZhiCms\base\Config as ZhiConfig;

class TpCompat {

    private static $dbConfig = [];
    private static $cacheConfig = [];
    private static $dbInitialized = false;
    private static $cacheInitialized = false;

    public static function init() {
        self::loadConfigs();
        self::initDb();
        self::initCache();
    }

    private static function loadConfigs() {
        $db = ZhiConfig::get('DB');
        if (!empty($db) && isset($db['default'])) {
            $defaultDb = $db['default'];
            self::$dbConfig = [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'type' => 'mysql',
                        'hostname' => $defaultDb['DB_HOST'] ?? '127.0.0.1',
                        'database' => $defaultDb['DB_NAME'] ?? '',
                        'username' => $defaultDb['DB_USER'] ?? '',
                        'password' => $defaultDb['DB_PWD'] ?? '',
                        'hostport' => $defaultDb['DB_PORT'] ?? '3306',
                        'charset' => $defaultDb['DB_CHARSET'] ?? 'utf8mb4',
                        'prefix' => $defaultDb['DB_PREFIX'] ?? '',
                    ],
                ],
            ];
        }

        self::$cacheConfig = [
            'default' => 'file',
            'stores' => [
                'file' => [
                    'type' => 'File',
                    'path' => \BASE_PATH . 'data/cache/',
                ],
            ],
        ];
    }

    private static function initDb() {
        if (!class_exists('\\think\\Db') || self::$dbInitialized) {
            return;
        }

        try {
            if (!empty(self::$dbConfig['connections']['mysql']['database'])) {
                \think\Db::setConfig(self::$dbConfig);
                self::$dbInitialized = true;
            }
        } catch (\Exception $e) {
        }
    }

    private static function initCache() {
        if (!class_exists('\\think\\Cache') || self::$cacheInitialized) {
            return;
        }

        try {
            \think\Cache::init(self::$cacheConfig);
            self::$cacheInitialized = true;
        } catch (\Exception $e) {
        }
    }

    public static function getDbConfig() {
        return self::$dbConfig;
    }

    public static function getCacheConfig() {
        return self::$cacheConfig;
    }

    public static function isDbInitialized() {
        return self::$dbInitialized;
    }

    public static function isCacheInitialized() {
        return self::$cacheInitialized;
    }
}