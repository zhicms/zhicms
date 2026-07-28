<?php
namespace ZhiCms\base;
class App{
	
	static protected function init(){
		define('NOW_TIME',      $_SERVER['REQUEST_TIME']);
	           define('REQUEST_METHOD',$_SERVER['REQUEST_METHOD']);
	           define('IS_GET',        REQUEST_METHOD =='GET' ? true : false);
	           define('IS_POST',       REQUEST_METHOD =='POST' ? true : false);
	           define('IS_PUT',        REQUEST_METHOD =='PUT' ? true : false);
	           define('IS_DELETE',     REQUEST_METHOD =='DELETE' ? true : false);
    
		Config::init( BASE_PATH );
		Config::loadConfig( CONFIG_PATH . 'global.php' );
		Config::loadConfig( CONFIG_PATH . Config::get('ENV') . '.php' );
		
		date_default_timezone_set( Config::get('TIMEZONE') );


		//error display
		if ( Config::get('DEBUG') ) {
			ini_set("display_errors", 1);
			error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE );
		} else {
			ini_set("display_errors", 0);
			error_reporting(0);
		}	
	}
	
	static public function run(){
		try{			
			if (!file_exists(CONFIG_PATH . 'install.lock')) {
				if (!isset($_REQUEST['r']) || strpos($_REQUEST['r'], 'install') !== 0) {
					header('Location: ' . ROOT_URL . 'index.php?r=install');
					exit;
				}
			}
			
			self::init();
			
			// ⚠️ 全局预置所有平台的防护常量（必须在任何插件加载前执行）。
			// 防止 Compat::detectType() 误判导致用错误 Bridge 加载插件时，插件顶部的
			// defined('XXX') || exit('access denied!') 直接杀死进程。
			\ZhiCms\base\compat\Compat::predefineAll();

			Hook::init(BASE_PATH);
			// 加载并注册已启用插件（标准化插件系统）
			try {
				\ZhiCms\base\PluginManager::boot();
			} catch (\Throwable $e) {}
			// 加载并注册已启用的 emlog / zblog 兼容插件
			try {
				\ZhiCms\base\compat\Compat::boot();
			} catch (\Throwable $e) {}
			Hook::listen('appBegin');

			Hook::listen('routeParseUrl', array( Config::get('REWRITE_RULE'), Config::get('REWRITE_ON')));
			
			//default route
			if( !defined('APP_NAME') || !defined('CONTROLLER_NAME') || !defined('ACTION_NAME')){
				Route::parseUrl( Config::get('REWRITE_RULE'), Config::get('REWRITE_ON') );
			}
			
			//execute action
			$controller = '\app\\'. APP_NAME .'\controller\\'. CONTROLLER_NAME .'Controller';
			$action = ACTION_NAME;

			if( !class_exists($controller) ) {
				$classes = get_declared_classes();
				$lowerController = strtolower($controller);
				foreach ($classes as $declaredClass) {
					if (strtolower($declaredClass) === $lowerController) {
						class_alias($declaredClass, $controller);
						break;
					}
				}
				if (!class_exists($controller)) {
					throw new \Exception("Controller '{$controller}' not found", 404);
				}
			}
			$obj = new $controller();
			if( !method_exists($obj, $action) ){
				throw new \Exception("Action '{$controller}::{$action}()' not found", 404);
			}
			
			Hook::listen('actionBefore', array($obj, $action));
			$obj ->$action();
			Hook::listen('actionAfter', array($obj, $action));
			
		} catch( \Exception $e ){
			Hook::listen('appError', array($e));
		}
		
		Hook::listen('appEnd');
	}
}
