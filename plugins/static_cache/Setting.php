<?php
namespace plugins\static_cache;

use ZhiCms\base\PluginManager;

class Setting
{
    protected $meta = array();
    
    public function __construct($meta = array())
    {
        $this->meta = $meta;
    }
    
    public function view()
    {
        $config = PluginManager::getConfig('static_cache');
        $plugin = PluginManager::instance('static_cache');
        $stats = $plugin ? $plugin->getCacheStats() : array('file_count' => 0, 'total_size' => 0);
        
        $defaults = array(
            'enabled' => 0,
            'expire' => 3600,
            'exclude_admin' => 1,
            'exclude_paths' => '',
        );
        $config = array_merge($defaults, $config);
        
        $statsText = $this->formatSize($stats['total_size']);
        
        ob_start();
        include __DIR__ . '/view/setting.php';
        return ob_get_clean();
    }
    
    public function save($data)
    {
        $config = array(
            'enabled' => intval($data['enabled'] ?? 0),
            'expire' => max(0, intval($data['expire'] ?? 3600)),
            'exclude_admin' => intval($data['exclude_admin'] ?? 1),
            'exclude_paths' => trim($data['exclude_paths'] ?? ''),
        );
        
        if (!empty($data['clear_cache'])) {
            $plugin = PluginManager::instance('static_cache');
            if ($plugin) {
                $plugin->clearAllCache();
            }
        }
        
        return $config;
    }
    
    protected function formatSize($bytes)
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
