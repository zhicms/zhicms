<?php
namespace ZhiCms\base\compat;

/**
 * 兼容层总装：
 *  - 检测插件包类型（native / emlog / zblog / wordpress）
 *  - 应用启动时加载并已启用插件注册钩子
 *  - 为后台"插件市场"提供兼容插件的扫描与元信息
 *
 * 统一目录约定：网站根/plugins/{alias}/，与原生插件一致。
 */
class Compat
{
    const DIR = 'plugins';

    /**
     * 🔒 全局预置所有平台的防护常量（安全气囊机制）
     *
     * 在 App::run() 中最早期调用，确保无论后续 detectType() / Bridge::load()
     * 如何执行，插件文件中的 defined('XXX') || exit('access denied!') 都不会触发。
     * 
     * 每个 Bridge 的 predefineConstants() 仍然会再次调用（使用 if(!defined(...)) 兜底），
     * 但本方法保证最坏情况下也不会出现 exit 导致整站中断。
     */
    public static function predefineAll()
    {
        // WordPress 防护常量
        if (!defined('ABSPATH'))         define('ABSPATH', BASE_PATH);
        if (!defined('WPINC'))           define('WPINC', 'wp-includes');
        if (!defined('WP_CONTENT_DIR'))  define('WP_CONTENT_DIR', BASE_PATH);
        if (!defined('WP_PLUGIN_DIR'))   define('WP_PLUGIN_DIR', BASE_PATH . 'plugins/');
        if (!defined('WP_PLUGIN_URL'))   define('WP_PLUGIN_URL', defined('ROOT_URL') ? ROOT_URL . 'plugins/' : '/plugins/');
        if (!defined('WP_CONTENT_URL'))  define('WP_CONTENT_URL', defined('ROOT_URL') ? ROOT_URL : '/');
        if (!defined('WP_DEBUG'))        define('WP_DEBUG', false);
        if (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', false);

        // Emlog 防护常量
        if (!defined('EMLOG_ROOT'))    define('EMLOG_ROOT', BASE_PATH);
        if (!defined('TEMPLATE_PATH')) define('TEMPLATE_PATH', BASE_PATH . 'public/');
        if (!defined('TPLS_URL'))      define('TPLS_URL', defined('ROOT_URL') ? ROOT_URL . 'public/' : '/public/');

        // Z-BlogPHP 防护常量
        if (!defined('ZBP_PATH'))       define('ZBP_PATH', BASE_PATH);
        if (!defined('ZBP_HOOKERROR'))  define('ZBP_HOOKERROR', true);
        if (!defined('ZBP_SAFEMODE'))   define('ZBP_SAFEMODE', false);
        if (!defined('ZBP_VERSION'))    define('ZBP_VERSION', '1.7');
        if (!defined('ZBP_PLUGIN_DIR')) define('ZBP_PLUGIN_DIR', BASE_PATH . 'zb_users/plugin/');
    }

    /**
     * 应用启动钩子：加载所有"已启用"的 emlog / zblog / wordpress 插件并注册其钩子。
     * 在 PluginManager::boot() 之后由 App::run() 调用。
     */
    public static function boot()
    {
        if (!\ZhiCms\base\PluginManager::tableReady()) return;
        try {
            $rows = \ZhiCms\base\PluginManager::db()->query("SELECT `alias` FROM {pre}plug WHERE `status` = 1");
        } catch (\Throwable $e) {
            return;
        }
        foreach ((array)$rows as $row) {
            $alias = $row['alias'];
            $type = self::detectType($alias);
            if ($type === false || $type === 'native') continue;
            self::load($type, $alias);
        }
    }

    /**
     * 判定插件包类型
     *
     * 检测优先级（高→低）：
     *   1. plugin.json  → native
     *   2. plugin.xml   → zblog
     *   3. 主文件含 Plugin Name 头 → 进一步区分 wordpress / emlog
     *   4. 回退 emlog 检测
     *
     * @return string|false  'native' | 'emlog' | 'zblog' | 'wordpress' | false
     */
    public static function detectType($alias)
    {
        $dir = BASE_PATH . self::DIR . '/' . $alias;
        if (!is_dir($dir)) return false;
        if (is_file($dir . '/plugin.json')) return 'native';
        if (is_file($dir . '/plugin.xml')) return 'zblog';

        // WordPress / Emlog 都使用 Plugin Name 头注释，需要区分
        $wpMain = WordPressBridge::findMainFile($alias, $dir);
        if ($wpMain) {
            $c = @file_get_contents($wpMain, false, null, 0, 8192);
            if ($c === false) return false;
            if (!preg_match('/Plugin\s+Name\s*:\s*.+/i', $c)) return false;

            // 🔑 优先检查 Emlog 特征（EMLOG_ROOT 引用是 Emlog 插件的铁证）
            if (stripos($c, 'EMLOG_ROOT') !== false) {
                return 'emlog';
            }

            // 🔑 其次检查 WordPress 特有特征（Text Domain / Requires PHP 等）
            if (preg_match('/\*\s*(Text\s+Domain|Domain\s+Path|Requires\s+PHP|Requires\s+at\s+least)\s*:/i', $c)) {
                return 'wordpress';
            }

            // 没有明显区分特征时：如果有 emlog 的 addAction 调用，判为 emlog
            if (stripos($c, 'addAction') !== false || stripos($c, 'doAction') !== false) {
                return 'emlog';
            }

            // 其他情况：有 Plugin Name 但无 Emlog/WordPress 特定特征 → 默认为 wordpress
            return 'wordpress';
        }

        // 回退：检查是否是纯 Emlog 格式（主文件没有通过 findMainFile 找到）
        $main = $dir . '/' . $alias . '.php';
        if (is_file($main) && self::hasEmlogHeader($main)) return 'emlog';
        return false;
    }

    public static function hasEmlogHeader($file)
    {
        $c = @file_get_contents($file, false, null, 0, 4096);
        if ($c === false) return false;
        // emlog 特有：引用 EMLOG_ROOT 或使用 addAction/doAction
        $hasPluginName = (bool)preg_match('/Plugin\s+Name\s*:/i', $c);
        $isEmlog = stripos($c, 'EMLOG_ROOT') !== false
                || stripos($c, 'addAction') !== false
                || stripos($c, 'doAction') !== false;
        return $hasPluginName && $isEmlog;
    }

    /** 按类型加载插件入口（仅注册钩子，不触发生命周期副作用） */
    public static function load($type, $alias)
    {
        $dir = BASE_PATH . self::DIR . '/' . $alias;
        try {
            if ($type === 'emlog') EmlogBridge::load($alias, $dir);
            elseif ($type === 'zblog') ZblogBridge::load($alias, $dir);
            elseif ($type === 'wordpress') WordPressBridge::load($alias, $dir);
        } catch (\Throwable $e) {
        }
    }

    /** 安装时触发的兼容层生命周期 */
    public static function install($alias)
    {
        $type = self::detectType($alias);
        if ($type === 'emlog') EmlogBridge::install($alias, BASE_PATH . self::DIR . '/' . $alias);
        elseif ($type === 'zblog') ZblogBridge::install($alias, BASE_PATH . self::DIR . '/' . $alias);
        elseif ($type === 'wordpress') WordPressBridge::install($alias, BASE_PATH . self::DIR . '/' . $alias);
    }

    /** 卸载时触发的兼容层生命周期 */
    public static function uninstall($alias)
    {
        $type = self::detectType($alias);
        if ($type === 'emlog') EmlogBridge::uninstall($alias, BASE_PATH . self::DIR . '/' . $alias);
        elseif ($type === 'zblog') ZblogBridge::uninstall($alias, BASE_PATH . self::DIR . '/' . $alias);
        elseif ($type === 'wordpress') WordPressBridge::uninstall($alias, BASE_PATH . self::DIR . '/' . $alias);
    }

    /**
     * 扫描"有文件且未安装"的兼容插件（供后台市场展示）
     */
    public static function scanAvailable()
    {
        $out = array();
        $base = BASE_PATH . self::DIR . '/';
        if (!is_dir($base)) return $out;
        foreach (glob($base . '*', GLOB_ONLYDIR) as $dir) {
            $alias = basename($dir);
            $type = self::detectType($alias);
            if ($type === false || $type === 'native') continue;
            if (\ZhiCms\base\PluginManager::hasRecord($alias)) continue;
            $out[] = self::readCompatMeta($alias, $type);
        }
        return $out;
    }

    /** 读取兼容插件的元信息（zblog 读 plugin.xml，emlog/wordpress 解析头注释） */
    public static function readCompatMeta($alias, $type)
    {
        $dir = BASE_PATH . self::DIR . '/' . $alias;
        if ($type === 'zblog') {
            $xmlFile = $dir . '/plugin.xml';
            $hasSetting = false;
            $menu = array();
            if (is_file($xmlFile)) {
                $xml = @simplexml_load_file($xmlFile);
                if ($xml) {
                    // Z-Blog 约定：<path> 不为空表示有后台管理页
                    $path = (string)($xml->path ?? '');
                    $hasSetting = ($path !== '' && is_file($dir . '/' . $path));
                    if (!$hasSetting) {
                        $hasSetting = is_file($dir . '/main.php');
                    }
                    // 如果有设置页，自动生成菜单项
                    if ($hasSetting) {
                        $name = (string)($xml->name ?? $alias);
                        $menu[] = array(
                            'title' => $name . ' 设置',
                            'url'   => 'index.php?r=manage/plugin/setting&alias=' . urlencode($alias),
                        );
                    }
                    return array(
                        'alias'       => $alias,
                        'type'        => 'zblog',
                        'name'        => (string)($xml->name ?? $alias),
                        'version'     => (string)($xml->version ?? '1.0'),
                        'author'      => (string)($xml->author->name ?? ''),
                        'description' => (string)($xml->description ?? ''),
                        'hasSetting'  => $hasSetting,
                        'menu'        => $menu,
                        '_compat'     => true,
                    );
                }
            }
            return array(
                'alias' => $alias, 'type' => 'zblog', 'name' => $alias,
                'version' => '1.0', 'author' => '', 'description' => '',
                'hasSetting' => false, 'menu' => array(), '_compat' => true,
            );
        }
        // wordpress 类型：解析头注释
        if ($type === 'wordpress') {
            $main = WordPressBridge::findMainFile($alias, $dir);
            $hdr = WordPressBridge::readHeader($main ?: ($dir . '/' . $alias . '.php'));
            return array(
                'alias'       => $alias,
                'type'        => 'wordpress',
                'name'        => $hdr['name'] ?: $alias,
                'version'     => $hdr['version'] ?: '1.0',
                'author'      => $hdr['author'] ?: '',
                'description' => $hdr['description'] ?: '',
                'hasSetting'  => false,
                'menu'        => array(),
                '_compat'     => true,
            );
        }
        // emlog
        $main = $dir . '/' . $alias . '.php';
        $c = is_file($main) ? @file_get_contents($main) : '';
        $meta = array(
            'alias' => $alias, 'type' => 'emlog', 'name' => $alias,
            'version' => '1.0', 'author' => '', 'description' => '',
            'hasSetting' => false, 'menu' => array(), '_compat' => true,
        );
        $fields = array('Plugin Name' => 'name', 'Version' => 'version', 'Author' => 'author', 'Description' => 'description');
        foreach ($fields as $k => $kk) {
            if (preg_match('/\*\s*' . preg_quote($k, '/') . '\s*:\s*(.+)/i', $c, $m)) {
                $meta[$kk] = trim($m[1]);
            }
        }
        return $meta;
    }
}
