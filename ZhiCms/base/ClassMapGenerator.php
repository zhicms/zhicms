<?php
namespace ZhiCms\base;

class ClassMapGenerator {
    
    public static function generate($basePath) {
        $classMap = array();
        $dirs = array(
            'ZhiCms/base',
            'ZhiCms/ext',
            'app/base',
            'app/api',
            'app/index',
            'app/manage',
            'app/go',
            'app/plug'
        );
        
        $basePath = rtrim($basePath, '/\\') . '/';
        
        foreach ($dirs as $dir) {
            $fullDir = $basePath . $dir;
            if (!is_dir($fullDir)) continue;
            
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullDir)
            );
            
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                
                $content = file_get_contents($file->getPathname());
                if (preg_match('/namespace\s+(.+?);/', $content, $nsMatch)) {
                    $namespace = trim($nsMatch[1]);
                    if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                        $className = $classMatch[1];
                        $fullClassName = $namespace . '\\' . $className;
                        
                        $filePath = str_replace('\\', '/', $file->getPathname());
                        $relativePath = substr($filePath, strlen($basePath));
                        $classMap[$fullClassName] = $relativePath;
                    }
                }
            }
        }
        
        $output = "<?php\nreturn " . var_export($classMap, true) . ";\n";
        file_put_contents($basePath . 'classmap.php', $output, LOCK_EX);
        return count($classMap);
    }
    
    public static function generateOnDemand($basePath) {
        $classMapFile = $basePath . 'classmap.php';
        if (!file_exists($classMapFile)) {
            self::generate($basePath);
        }
    }
}