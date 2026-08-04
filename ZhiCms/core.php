<?php
/**
 * 框架核心
 */
// 最前引入入口引导（Session / 编码 / 错误处理 / 响应头 / gzip）
require __DIR__ . '/bootstrap.php';

// 后台操作日志扩展（提供全局函数 admin_log()）
require_once __DIR__ . '/ext/AdminLog.php';

// 蜘蛛访问限制（提供全局函数 zhi_spider_guard()，前台引导阶段调用）
require_once __DIR__ . '/ext/SpiderGuard.php';

// 违规词检测 / 计划任务运行器
require_once __DIR__ . '/ext/WordCheck.php';
require_once __DIR__ . '/ext/CronRunner.php';

if (version_compare(PHP_VERSION, '7.0.0','<')) {
	header("Content-Type: text/html; charset=UTF-8");
    echo 'PHP环境不能低于7.0.0';
    exit;
}
define('ROOT_PATH', realpath('./').DIRECTORY_SEPARATOR);
define('BASE_PATH', realpath('./').DIRECTORY_SEPARATOR);
define('CONFIG_PATH', BASE_PATH.'data/config/');

// 安装引导阶段强制显示错误：PHP 8.x 下部分致命错误（如 array_merge 参数为 null）
// 会导致整页空白，开启 display_errors 后可直观看到报错而非白屏，便于排查。
if (!file_exists(CONFIG_PATH . 'install.lock')
    || (isset($_REQUEST['r']) && strpos($_REQUEST['r'], 'install') === 0)) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
// Z-Blog 兼容桩根目录（原根目录 zb_system 已迁移至此，统一由该常量映射）
define('ZBP_SYSTEM_DIR', BASE_PATH . 'ZhiCms/base/compat/zb_system/');
define('ROOT_URL',  rtrim(dirname($_SERVER["SCRIPT_NAME"]), '\\/').'/');
define('PUBLIC_URL', ROOT_URL . 'public/');
define('PUBLIC_PATH', BASE_PATH.'public/');
define('__PUBLIC__', ROOT_URL . 'public/');

use ZhiCms\base\Config;
use ZhiCms\base\Route;
use ZhiCms\base\App;

/**
 * 获取设置配置
 * @param  string $key   配置项
 * @param  mixed  $value 配置值
 * @return array
 */
function config($key = NULL, $value = NULL){
	if( func_num_args() <= 1 ){
		return Config::get($key);
	}else{
		return Config::set($key, $value);
	}
}

/**
 * URL生成
 * @param  string $route  地址
 * @param  array  $params 参数
 * @return string
 */
function url($route = null, $params = array()){
	return Route::url($route, $params);
}

/**
 * 导航高亮判断（前台导航用）：根据导航 URL 与当前路由判断是否 active
 * @param array $nav yun_navmenu 的一行（含 url / type）
 * @return string 'active' 或 ''
 */
function is_active_nav($nav = array()){
	if (empty($nav) || empty($nav['url'])) return '';
	$url = (string)$nav['url'];
	$active = '';
	if (defined('\CONTROLLER_NAME') && defined('\ACTION_NAME')) {
		$ctrl = strtolower(CONTROLLER_NAME);
		$act  = strtolower(ACTION_NAME);
		// 固定栏目：命中控制器即高亮
		foreach (array('cheaps', 'brand', 'rank', 'hot', 'forum') as $seg) {
			if (strpos($url, '/' . $seg . '/') !== false || strpos($url, $seg . '/') === 0 || $url === 'index/' . $seg . '/index') {
				if ($ctrl === $seg) { $active = 'active'; break; }
			}
		}
		// 首页
		if (!$active && (strpos($url, 'index/index/index') !== false || $url === '/' )) {
			if ($ctrl === 'index' && $act === 'index') { $active = 'active'; }
		}
		// 单页
		if (!$active && stripos($url, 'page') !== false) {
			$reqPage = intval(isset($_GET['id']) ? $_GET['id'] : 0);
			$reqAlias = isset($_GET['alias']) ? trim($_GET['alias']) : '';
			if (preg_match('/page(?:-|=|&)(\d+)/i', $url, $m) && $reqPage == $m[1]) $active = 'active';
			elseif (preg_match('/page-(.+?)(?:\.html|$)/i', $url, $m2) && $reqAlias !== '' && strpos($url, $reqAlias) !== false) $active = 'active';
		}
	}
	return $active;
}

/**
 * 模板原样输出过滤：配合 think-template 使用，
 * 将 default_filter 设为该函数可保持与旧引擎一致的不转义输出。
 */
function tpl_raw($value){
	return $value;
}

/**
 * 基于已初始化的 think\Cache 的“记住”助手（双框架互补）
 * 业务代码无需关心缓存驱动，仅在 think\Cache 可用时生效；
 * 任何异常都会自动降级为“直接执行回调”，保证程序不受影响。
 *
 * @param string   $key      缓存键
 * @param callable $callback 数据生产闭包
 * @param int      $expire   有效期（秒），默认 600
 * @return mixed
 */
function tcache($key, $callback, $expire = 600){
	if (!class_exists('\\think\\facade\\Cache')) {
		return $callback();
	}
	try {
		$cache = \think\facade\Cache::store('file');
		if (method_exists($cache, 'remember')) {
			return $cache->remember($key, $callback, $expire);
		}
		$val = $cache->get($key);
		if ($val !== null && $val !== false) {
			return $val;
		}
		$val = $callback();
		$cache->set($key, $val, $expire);
		return $val;
	} catch (\Throwable $e) {
		return $callback();
	}
}

/**
 * 清空指定前缀的缓存（便于后台更新配置/数据时主动失效）
 * @param string $prefix
 */
function tcache_clear($prefix = ''){
	if (!class_exists('\\think\\facade\\Cache')) {
		return false;
	}
	try {
		$cache = \think\facade\Cache::store('file');
		if (method_exists($cache, 'clear')) {
			return $prefix === '' ? $cache->clear() : $cache->clear();
		}
	} catch (\Throwable $e) {
	}
	return false;
}

/**
 * 对象调用函数
 * @param  string $class 模块名/类名
 * @param  string $layer 模块层
 * @return object
 */
/**
 * 触发一个“动作型”钩子（插件在此 echo 内容或执行副作用）。
 * 供模板/控制器调用：<?php zhi_hook('index_head'); ?>
 * @param string $name   钩子名称
 * @param array  $params 参数
 */
function zhi_hook($name, $params = array()){
	\ZhiCms\base\Hook::listen($name, $params);
}

/**
 * 触发一个“过滤型”钩子（改写某个值，对应 emlog 的 doMultiAction）。
 * @param string $name   钩子名称
 * @param mixed  $value  被过滤的值
 * @param array  $params 附加参数
 * @return mixed
 */
function zhi_apply($name, $value = null, $params = array()){
	return \ZhiCms\base\Hook::filter($name, $value, $params);
}

function obj($class, $layer = 'model'){
	static $objArr = array();
	$param = explode('/', $class, 2);
	$paramCount = count($param);
	switch ($paramCount) {
		case 1:
			$app = APP_NAME;
			$module = $param[0];
			break;
		case 2:
			$app = $param[0];
			$module = $param[1];
			break;
	}
	$app = strtolower($app);
	$className = "\\app\\{$app}\\{$layer}\\{$module}".ucfirst($layer);
	
	if (isset($objArr[$className])) {
		// 若正处于"构造中"（占位标记），说明构造期重入 obj() 自身，
		// 直接返回 false，避免 Container::make() 无限递归；由调用方兜底。
		if ($objArr[$className] === true) {
			return false;
		}
        return $objArr[$className];
	}
	
	if (class_exists('\\think\\Container')) {
		try {
			// 构造前先写入占位标记，防止构造期重入同一类导致无限递归
			$objArr[$className] = true;
			// 使用单例容器，避免每次 obj() 都重建容器（双框架互补点）
			$container = method_exists('\\think\\Container', 'getInstance')
				? \think\Container::getInstance()
				: new \think\Container();
			$obj = $container->make($className);
			$objArr[$className] = $obj;
			return $obj;
		} catch (\Exception $e) {
			unset($objArr[$className]);
		}
	}
	
	if(!class_exists($className)){
		$upperModule = ucfirst($module);
		$upperClass = "\\app\\{$app}\\{$layer}\\{$upperModule}".ucfirst($layer);
		if (class_exists($upperClass)) {
			$className = $upperClass;
		} else {
			$lowerModule = strtolower($module);
			$lowerClass = "\\app\\{$app}\\{$layer}\\{$lowerModule}".ucfirst($layer);
			if (class_exists($lowerClass)) {
				$className = $lowerClass;
			} else {
				$classes = get_declared_classes();
				$targetClassPattern = '/^\\\\app\\\\' . preg_quote($app) . '\\\\' . preg_quote($layer) . '\\\\(\w+)' . preg_quote(ucfirst($layer)) . '$/';
				foreach ($classes as $declaredClass) {
					if (preg_match($targetClassPattern, $declaredClass, $matches)) {
						if (strtolower($matches[1]) === strtolower($module)) {
							class_alias($declaredClass, $className);
							break;
						}
					}
				}
				if (!class_exists($className)) {
					throw new \Exception("Class '{$className}' not found'", 500);
				}
			}
		}
	}
	$obj = new $className();
	$objArr[$className] = $obj;
	return $obj;
}


/**
 * 自动注册类
 */
spl_autoload_register(function($class){
	static $classMap = null;
	
	if ($classMap === null) {
		$classMapFile = BASE_PATH . 'classmap.php';
		if (file_exists($classMapFile)) {
			$classMap = require $classMapFile;
		} else {
			require __DIR__ . '/base/ClassMapGenerator.php';
			\ZhiCms\base\ClassMapGenerator::generate(BASE_PATH);
			$classMap = require $classMapFile;
		}
	}
	
	if (isset($classMap[$class])) {
		$filePath = $classMap[$class];
		if (!file_exists($filePath)) {
			$filePath = BASE_PATH . $filePath;
		}
		require $filePath;
		return true;
	}
	
	static $fileList = array();
	$prefixes =array(
		'ZhiCms' => BASE_PATH,
		'app' => BASE_PATH,
		'*'=>BASE_PATH,
	);

	$class = ltrim($class, '\\');
	if (false !== ($pos = strrpos($class, '\\')) ){
		$namespace = substr($class, 0, $pos);
		$className = substr($class, $pos + 1);
		
		foreach ($prefixes as $prefix => $baseDir){
			if ( '*'!==$prefix && 0!==strpos($namespace, $prefix) ) continue;
			
			$fileDIR = $baseDir.str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
			if( !isset($fileList[$fileDIR]) ){
				$fileList[$fileDIR] = array();
				foreach(glob($fileDIR.'*.php') as $file){
					$fileList[$fileDIR][] = $file;
				}
			}
			
			$fileBase = $baseDir.str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR.$className;
			foreach($fileList[$fileDIR] as $file){
				if( false!==stripos($file, $fileBase) ){
					require $file;
					if (!class_exists($class)) {
						$classes = get_declared_classes();
						foreach ($classes as $declaredClass) {
							if (strtolower($declaredClass) === strtolower($class)) {
								class_alias($declaredClass, $class);
								return true;
							}
						}
					}
					return true;				
				}
			}							
		}           
	}
	return false;
});

if (class_exists('\\ZhiCms\\base\\compat\\TpCompat')) {
    \ZhiCms\base\compat\TpCompat::init();
}

function cdn_url($path) {
    static $cdnUrl = null;
    static $hostUrl = null;
    
    if ($cdnUrl === null) {
        // 优先从 DB 读取（通过 ConfigStore），自动兼容旧文件兜底
        if (class_exists('\\app\\common\\ConfigStore')) {
            $siteConfig = \app\common\ConfigStore::load('site');
            $cdnUrl  = !empty($siteConfig['cdnurl']) ? rtrim($siteConfig['cdnurl'], '/') : '';
            // hosturl 为 localhost / 127.0.0.1 / 空 时视为未配置，回退到当前访问域名
            $rawHost = !empty($siteConfig['hosturl']) ? rtrim($siteConfig['hosturl'], '/') : '';
            $hostUrl = (preg_match('#^https?://(localhost|127\.0\.0\.1)([:/]|$)#i', $rawHost) || $rawHost === '') ? '' : $rawHost;
        } elseif (file_exists(CONFIG_PATH . 'siteconfig.php')) {
            include CONFIG_PATH . 'siteconfig.php';
            $cdnUrl  = !empty($Siteinfo['cdnurl']) ? rtrim($Siteinfo['cdnurl'], '/') : '';
            $rawHost = !empty($Siteinfo['hosturl']) ? rtrim($Siteinfo['hosturl'], '/') : '';
            $hostUrl = (preg_match('#^https?://(localhost|127\.0\.0\.1)([:/]|$)#i', $rawHost) || $rawHost === '') ? '' : $rawHost;
        } else {
            $cdnUrl  = '';
            $hostUrl = '';
        }
    }
    
    if (empty($path)) {
        return '';
    }
    
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    
    $baseUrl = !empty($cdnUrl) ? $cdnUrl : $hostUrl;

    // 如果 CDN 和 hosturl 都未配置，使用当前请求的域名作为兜底
    if (empty($baseUrl) && isset($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    $path = ltrim($path, '/');

    return $baseUrl . '/' . $path;
}

// 蜘蛛访问限制：仅前台生效（后台/安装不拦截，避免管理员被误伤）
$__r = isset($_GET['r']) ? $_GET['r'] : '';
$__uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
if (strpos($__r, 'manage') !== 0 && strpos($__r, 'install') !== 0
    && strpos($__uri, '/manage') !== 0 && strpos($__uri, '/install') !== 0) {
    if (function_exists('zhi_spider_guard')) {
        zhi_spider_guard();
    }
}

App::run();