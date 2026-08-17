<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class CacheController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /**
     * 缓存类型定义：key => ['name'=>展示名, 'path'=>相对 ROOT_PATH 的目录/文件]
     * 松散文件类（data/cache 下散落的 php）用特殊标记 'loose' 处理
     */
    private static $types = array(
        'tpl'   => array('name' => '模板缓存', 'path' => 'data/cache/tpl'),
        'db'    => array('name' => '数据库查询缓存', 'path' => 'data/cache/db'),
        'data'  => array('name' => '数据缓存', 'path' => 'data/cache', 'loose' => true),
        'api'   => array('name' => 'API接口缓存', 'path' => 'data/cache', 'prefix' => 'api_'),
        'static'=> array('name' => '全站静态缓存', 'path' => 'runtime/static_cache'),
    );

    public function index(){
        $this->checkManageSession();
        $this->pageText = array('缓存管理');

        $list = array();
        foreach (self::$types as $key => $t) {
            $size = $this->dirSize(\ROOT_PATH . $t['path']);
            $count = $this->dirCount(\ROOT_PATH . $t['path'], !empty($t['loose']));
            $list[] = array(
                'key'   => $key,
                'name'  => $t['name'],
                'size'  => $this->formatSize($size),
                'count' => $count,
            );
        }
        $this->cacheList = $list;
        $this->allSize = $this->formatSize($this->totalSize());
        $this->display();
    }

    /**
     * 清理指定类型缓存，type=all 时一键全清
     */
    public function clear(){
        $this->checkManageSession();
        $type = isset($_GET['type']) ? trim($_GET['type']) : 'all';

        if ($type === 'all') {
            self::clearAllCache();
            $msg = '已清理全站缓存';
        } elseif (isset(self::$types[$type])) {
            $t = self::$types[$type];
            $dir = \ROOT_PATH . $t['path'];
            if (!empty($t['prefix'])) {
                // API 缓存：只删 data/cache 下指定前缀（api_）的散落文件，不影响其他缓存
                $this->clearByPrefix($dir, $t['prefix']);
            } elseif (!empty($t['loose'])) {
                // 数据缓存：清理 data/cache 下松散 php 文件，但保留 tpl/db 子目录
                $this->clearLoose($dir);
            } else {
                self::delDirContents($dir);
            }
            $msg = '已清理「' . $t['name'] . '」';
        } else {
            exit(json_encode(array('info' => '未知缓存类型', 'status' => 'n')));
        }

        // 同时清掉 data/cache 整体运行缓存（兜底）
        if ($type !== 'all') {
            \app\common\ConfigStore::clearCache('site');
        }
        exit(json_encode(array('info' => $msg, 'status' => 'y')));
    }

    // ===== 私有辅助 =====

    private function clearLoose($dir){
        if (!is_dir($dir)) return;
        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if ($file == '.' || $file == '..') continue;
            $full = $dir . '/' . $file;
            if (is_dir($full)) {
                // 保留 tpl / db 子目录（由各自类型处理）
                if (in_array($file, array('tpl', 'db'))) continue;
                self::delDirContents($full);
                @rmdir($full);
            } else {
                @unlink($full);
            }
        }
        closedir($dh);
    }

    /**
     * 按文件名前缀清理 data/cache 下散落的缓存文件（如 API 缓存 api_*.php）
     */
    private function clearByPrefix($dir, $prefix){
        if (!is_dir($dir)) return;
        $files = glob($dir . '/' . $prefix . '*.php');
        if (!empty($files)) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }

    private function dirSize($dir){
        if (!is_dir($dir)) return 0;
        $size = 0;
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) {
            if ($f->isFile()) $size += $f->getSize();
        }
        return $size;
    }

    private function dirCount($dir, $loose = false){
        if (!is_dir($dir)) return 0;
        if ($loose) {
            $n = 0;
            $dh = opendir($dir);
            while ($file = readdir($dh)) {
                if ($file == '.' || $file == '..') continue;
                $full = $dir . '/' . $file;
                if (is_dir($full)) {
                    if (in_array($file, array('tpl', 'db'))) continue;
                    $n++;
                } else {
                    $n++;
                }
            }
            closedir($dh);
            return $n;
        }
        $n = 0;
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) { if ($f->isFile()) $n++; }
        return $n;
    }

    private function totalSize(){
        $s = 0;
        foreach (self::$types as $t) {
            $s += $this->dirSize(\ROOT_PATH . $t['path']);
        }
        return $s;
    }

    private function formatSize($bytes){
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    // ===== 静态清理方法（供 CacheController::clear 与 LoginController::logout 复用）=====

    /**
     * 递归删除目录内容（保留目录自身）
     */
    public static function delDirContents($dir){
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $p = $dir . '/' . $it;
            if (is_dir($p)) {
                self::delDirContents($p);
                @rmdir($p);
            } else {
                @unlink($p);
            }
        }
    }

    private static function clearTpl(){
        self::delDirContents(\ROOT_PATH . 'data/cache/tpl');
    }

    private static function clearDb(){
        self::delDirContents(\ROOT_PATH . 'data/cache/db');
    }

    private static function clearData(){
        $dir = \ROOT_PATH . 'data/cache';
        if (!is_dir($dir)) return;
        $dh = opendir($dir);
        while ($f = readdir($dh)) {
            if ($f === '.' || $f === '..') continue;
            $full = $dir . '/' . $f;
            if (is_dir($full)) {
                if (in_array($f, array('tpl', 'db'))) continue;
                self::delDirContents($full);
                @rmdir($full);
            } else {
                @unlink($full);
            }
        }
        closedir($dh);
    }

    private static function clearStatic(){
        self::delDirContents(\ROOT_PATH . 'runtime/static_cache');
    }

    /**
     * 一键全站清理（静态，不 exit，便于 logout 等场景复用）
     */
    public static function clearAllCache(){
        self::clearTpl();
        self::clearDb();
        self::clearData();
        self::clearStatic();
    }
}
