<?php
namespace app\common;

/**
 * 缓存服务层 — 对标 emlog 的缓存设计
 * 
 * emlog 将频繁访问的数据（导航、分类、统计）缓存为 PHP 数组文件，
 * 避免每次请求都查询数据库。本服务提供统一的 TTL 缓存机制。
 * 
 * 关键数据缓存：
 *   - 侧边栏统计（今日/昨日/7天/本月/总数）→ 5 分钟 TTL
 *   - 热门文章 → 10 分钟 TTL
 *   - 分类目录 → 永久（静态数据）
 *   - 日历文章日期列表 → 30 分钟 TTL
 */
class CacheService
{
    /** 缓存目录 */
    private $dir;

    /** 缓存 TTL（秒） */
    private $ttl;

    public function __construct($ttl = 300)
    {
        $this->dir = \ROOT_PATH . 'data/cache/';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
        $this->ttl = $ttl;
    }

    /**
     * 读取缓存（带 TTL 过期校验）
     */
    public function get($key)
    {
        $file = $this->dir . $key . '.php';
        if (!file_exists($file)) {
            return null;
        }
        $data = include $file;
        if (!is_array($data) || !isset($data['_expire'])) {
            return null;
        }
        if ($data['_expire'] > 0 && $data['_expire'] < time()) {
            @unlink($file);
            return null;
        }
        return isset($data['_data']) ? $data['_data'] : null;
    }

    /**
     * 写入缓存
     */
    public function set($key, $value, $ttl = null)
    {
        $ttl = $ttl ?? $this->ttl;
        $data = [
            '_data'   => $value,
            '_expire' => $ttl > 0 ? time() + $ttl : 0,
        ];
        $file = $this->dir . $key . '.php';
        $content = '<?php return ' . var_export($data, true) . ';';
        // 原子写入：先写临时文件再 rename，避免并发读半截；
        // rename 后 OPcache 会重新编译该路径，规避“旧字节码缓存不刷新”问题。
        $tmp = $file . '.' . getmypid() . '.' . mt_rand(0, 999999) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        if (DIRECTORY_SEPARATOR === '\\' && file_exists($file)) {
            @unlink($file);
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            if (file_put_contents($file, $content, LOCK_EX) === false) {
                return false;
            }
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        @clearstatcache(true, $file);
        return true;
    }

    /**
     * 获取或写入（缓存穿透保护）
     */
    public function remember($key, callable $callback, $ttl = null)
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * 清空指定 key
     */
    public function forget($key)
    {
        $file = $this->dir . $key . '.php';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * 清空所有缓存
     */
    public function flush()
    {
        $files = glob($this->dir . '*.php');
        foreach ($files as $f) {
            @unlink($f);
        }
    }

    /**
     * 静态单例获取（替代函数，确保类自动加载可用）
     */
    public static function instance($ttl = 300)
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new self($ttl);
        }
        return $inst;
    }
}
