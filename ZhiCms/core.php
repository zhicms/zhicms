<?php
/**
 * 框架核心
 */
if (version_compare(PHP_VERSION, '5.3.0','<')) {
	header("Content-Type: text/html; charset=UTF-8");
    echo 'PHP环境不能低于5.3.0';
    exit;
}
define('ROOT_PATH', realpath('./').DIRECTORY_SEPARATOR);
define('BASE_PATH', realpath('./').DIRECTORY_SEPARATOR);
define('CONFIG_PATH', BASE_PATH.'data/config/');
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
 * 对象调用函数
 * @param  string $class 模块名/类名
 * @param  string $layer 模块层
 * @return object
 */
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
	$class = "\\app\\{$app}\\{$layer}\\{$module}".ucfirst($layer);
	if(isset($objArr[$class])){
        return $objArr[$class];
	}
	if(!class_exists($class)){
		throw new \Exception("Class '{$class}' not found'", 500);
	}
	$obj = new $class();
	$objArr[$class] = $obj;
	return $obj;
}


/**
 * 自动注册类
 */
spl_autoload_register(function($class){
	static $fileList = array();
	$prefixes =array(
		'ZhiCms' => realpath(__DIR__.'/../').DIRECTORY_SEPARATOR,
		'app' => BASE_PATH,
		'*'=>BASE_PATH,
	);

	$class = ltrim($class, '\\');
	if (false !== ($pos = strrpos($class, '\\')) ){
		$namespace = substr($class, 0, $pos);
		$className = substr($class, $pos + 1);
		
		foreach ($prefixes as $prefix => $baseDir){
			if ( '*'!==$prefix && 0!==strpos($namespace, $prefix) ) continue;
			
			//file path case-insensitive
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
					return true;				
				}
			}							
		}           
	}
	return false;
});

App::run();