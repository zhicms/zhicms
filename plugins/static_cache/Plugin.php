<?php
namespace plugins\static_cache;

use ZhiCms\base\plugin\BasePlugin;
use ZhiCms\base\Hook;
use ZhiCms\base\Config;

class Plugin extends BasePlugin
{
    protected $cacheDir = '';
    
    public function __construct($meta = array())
    {
        parent::__construct($meta);
        $this->cacheDir = \BASE_PATH . 'runtime/static_cache/';
    }
    
    public function register()
    {
        Hook::add('appBegin', array($this, 'checkCache'));
        Hook::add('appEnd', array($this, 'writeCache'));
    }
    
    public function install()
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function uninstall()
    {
        $this->clearAllCache();
        @rmdir($this->cacheDir);
    }
    
    public function enable()
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function checkCache()
    {
        $config = $this->getConfig();
        
        if (empty($config['enabled'])) {
            return;
        }
        
        if (!\IS_GET) {
            return;
        }
        
        if (\REQUEST_METHOD !== 'GET') {
            return;
        }
        
        if ($this->isExcluded()) {
            return;
        }
        
        $cacheFile = $this->getCacheFilePath();
        
        if (file_exists($cacheFile)) {
            $expire = intval($config['expire'] ?? 3600);
            
            if ($expire > 0 && (time() - filemtime($cacheFile)) > $expire) {
                @unlink($cacheFile);
            } else {
                $content = file_get_contents($cacheFile);
                if ($content !== false) {
                    header('Content-Type: text/html; charset=utf-8');
                    header('X-Static-Cache: HIT');
                    echo $content;
                    exit;
                }
            }
        }
        
        ob_start();
    }
    
    public function writeCache()
    {
        $config = $this->getConfig();
        
        if (empty($config['enabled'])) {
            return;
        }
        
        if (!\IS_GET) {
            return;
        }
        
        if ($this->isExcluded()) {
            return;
        }
        
        if (ob_get_level() < 1) {
            return;
        }
        
        $content = ob_get_contents();
        
        if ($content !== false && !empty($content)) {
            $cacheFile = $this->getCacheFilePath();
            
            $dir = dirname($cacheFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            
            $content .= "\n<!-- Static Cache: " . date('Y-m-d H:i:s') . " -->";
            @file_put_contents($cacheFile, $content, LOCK_EX);
        }
    }
    
    protected function isExcluded()
    {
        $config = $this->getConfig();
        
        // 后台(manage)页面永远不缓存：实时数据且修改后需立即生效，
        // 避免因配置缺失(exclude_admin 未设置)导致后台页面被缓存成旧版本。
        if (defined('APP_NAME') && \APP_NAME === 'manage') {
            return true;
        }
        
        // 插件展示页(index/plug/* 或伪静态 plug-*.html)永远不缓存：插件页由插件控制器
        // 动态渲染，且伪静态 URI（如 plug-kiees.html）与主站首页 URI 的缓存路径
        // 哈希可能冲突，导致插件页被错误地返回主站首页缓存。内置排除可彻底规避，
        // 同时兼容动态(?r=index/plug/...)与伪静态(plug-xxx.html)两种访问方式。
        $currentRoute = isset($_GET['r']) ? $_GET['r'] : '';
        if (strpos($currentRoute, 'index/plug') === 0) {
            return true;
        }
        $reqUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (preg_match('#/plug-[\w-]*\.html#i', $reqUri)) {
            return true;
        }
        
        $excludedPaths = array();
        if (!empty($config['exclude_paths'])) {
            $excludedPaths = array_filter(array_map('trim', explode("\n", $config['exclude_paths'])));
        }
        
        $currentPath = isset($_GET['r']) ? $_GET['r'] : '';
        
        foreach ($excludedPaths as $path) {
            if (!empty($path) && strpos($currentPath, $path) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function getCacheFilePath()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        $urlPath = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = parse_url($uri, PHP_URL_QUERY);
        
        $pathHash = md5($urlPath);
        $queryHash = $query ? md5($query) : 'index';
        
        $firstChar = substr($pathHash, 0, 2);
        $secondChar = substr($pathHash, 2, 2);
        
        return $this->cacheDir . $firstChar . '/' . $secondChar . '/' . $queryHash . '.html';
    }
    
    public function clearAllCache()
    {
        if (!is_dir($this->cacheDir)) {
            return true;
        }
        
        $this->removeDirectory($this->cacheDir);
        @mkdir($this->cacheDir, 0755, true);
        return true;
    }
    
    public function getCacheStats()
    {
        $stats = array(
            'file_count' => 0,
            'total_size' => 0,
            'dir_count' => 0,
        );
        
        if (!is_dir($this->cacheDir)) {
            return $stats;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $stats['file_count']++;
                $stats['total_size'] += $file->getSize();
            } elseif ($file->isDir()) {
                $stats['dir_count']++;
            }
        }
        
        return $stats;
    }
    
    protected function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $items = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        
        @rmdir($dir);
    }
}
