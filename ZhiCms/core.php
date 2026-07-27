<?php
/**
 * 框架核心
 */
// 最前引入入口引导（Session / 编码 / 错误处理 / 响应头 / gzip）
require __DIR__ . '/bootstrap.php';

if (version_compare(PHP_VERSION, '7.0.0','<')) {
	header("Content-Type: text/html; charset=UTF-8");
    echo 'PHP环境不能低于7.0.0';
    exit;
}
define('ROOT_PATH', realpath('./').DIRECTORY_SEPARATOR);
define('BASE_PATH', realpath('./').DIRECTORY_SEPARATOR);
define('CONFIG_PATH', BASE_PATH.'data/config/');
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
        return $objArr[$className];
	}
	
	if (class_exists('\\think\\Container')) {
		try {
			// 使用单例容器，避免每次 obj() 都重建容器（双框架互补点）
			$container = method_exists('\\think\\Container', 'getInstance')
				? \think\Container::getInstance()
				: new \think\Container();
			$obj = $container->make($className);
			$objArr[$className] = $obj;
			return $obj;
		} catch (\Exception $e) {
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
        if (file_exists(CONFIG_PATH . 'siteconfig.php')) {
            include CONFIG_PATH . 'siteconfig.php';
            $cdnUrl = !empty($Siteinfo['cdnurl']) ? rtrim($Siteinfo['cdnurl'], '/') : '';
            $hostUrl = !empty($Siteinfo['hosturl']) ? rtrim($Siteinfo['hosturl'], '/') : '';
        } else {
            $cdnUrl = '';
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
    $path = ltrim($path, '/');
    
    return $baseUrl . '/' . $path;
}

App::run();