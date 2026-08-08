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
    
		Config::init( \BASE_PATH );
		Config::loadConfig( \CONFIG_PATH . 'global.php' );
		Config::loadConfig( \CONFIG_PATH . Config::get('ENV') . '.php' );
		
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
			if (!file_exists(\CONFIG_PATH . 'install.lock')) {
				if (!isset($_REQUEST['r']) || strpos($_REQUEST['r'], 'install') !== 0) {
					header('Location: ' . \ROOT_URL . 'index.php?r=install');
					exit;
				}
			}
			
			self::init();
			
			// ⚠️ 全局预置所有平台的防护常量（必须在任何插件加载前执行）。
			// 防止 Compat::detectType() 误判导致用错误 Bridge 加载插件时，插件顶部的
			// defined('XXX') || exit('access denied!') 直接杀死进程。
			\ZhiCms\base\compat\Compat::predefineAll();

			Hook::init(\BASE_PATH);
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
			if( !defined('\APP_NAME') || !defined('\CONTROLLER_NAME') || !defined('\ACTION_NAME')){
				// 主页模板插件拦截：仅当访问「首页/根路径」且后台配置了已启用的模板化主页插件时，
				// 改写路由到该插件的展示控制器，使域名打开直接渲染插件模板（URL 不变，不影响其它路由）。
				self::applyHomePlug();
				Route::parseUrl( Config::get('REWRITE_RULE'), Config::get('REWRITE_ON') );
			}
			
			//execute action
			$controller = '\app\\'. \APP_NAME .'\controller\\'. \CONTROLLER_NAME .'Controller';
			$action = \ACTION_NAME;

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
			
		} catch( \Throwable $e ){
			Hook::listen('appError', array($e));
		}
		
		Hook::listen('appEnd');
	}

	/**
	 * 主页模板插件拦截（方案 B：URL 不变，直接展示插件作为首页）。
	 * 仅当访问「根路径/首页」且后台 site 配置中 home_plug 指向一个已启用的模板化插件时，
	 * 把 $_REQUEST['r'] 改写为 index/plug/view/alias=<alias>，从而把主页渲染交给插件。
	 * 若非根路径、或未配置插件主页、或插件未启用/非模板插件，一律不影响原路由。
	 */
	static protected function applyHomePlug(){
		// 1) 已显式指定路由（r 参数非默认首页）则不干预，保证 plug-xxx.html、文章详情、后台等正常
		$r = isset($_REQUEST['r']) ? trim((string)$_REQUEST['r']) : '';
		if ($r !== '') {
			$norm = strtolower(preg_replace('#/+#', '/', trim($r, '/ ')));
			$isHome = ($norm === '' || $norm === 'index' || $norm === 'index/index' || $norm === 'index/index/index');
			if (!$isHome) return; // 非首页路由，直接放行
		}
		// 2) 仅限首页访问（根路径 /、/index.php、首页伪静态 /index.html），不带其它具体路径
		$path = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
		$path = $path === false ? '/' : $path;
		$path = rtrim($path, '/');
		if ($path !== '' && $path !== '/index.php' && $path !== '/index.html') return;

		// 3) 读取主页插件配置，需已启用且为模板化插件
		$homePlug = \app\common\ConfigStore::load('site', 'home_plug');
		if (empty($homePlug)) return;
		$alias = trim((string)$homePlug);
		if ($alias === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $alias)) return;
		try {
			if (!\ZhiCms\base\PluginManager::isEnabled($alias)) return;
			if (!\ZhiCms\base\PluginManager::isTemplate($alias)) return;
		} catch (\Throwable $e) {
			return;
		}
		// 4) 改写路由到插件展示控制器（PlugController::view 会校验插件存在与启用）
		$_REQUEST['r'] = 'index/plug/view/alias=' . $alias;
	}
}
