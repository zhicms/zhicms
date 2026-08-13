<?php
namespace ZhiCms\base;
class Route {			
	static protected $rewriteRule = array();
	static protected $rewriteOn = false;
	
	static public function parseUrl( $rewriteRule, $rewriteOn=false){
		self::$rewriteRule = $rewriteRule;
		self::$rewriteOn = $rewriteOn;
		
		static $routeCache = array();
		$cacheKey = md5($_SERVER['REQUEST_URI'] . (isset($_REQUEST['r']) ? $_REQUEST['r'] : ''));
		
		if (isset($routeCache[$cacheKey])) {
			$cached = $routeCache[$cacheKey];
			if( !defined('APP_NAME') ) define('APP_NAME', $cached['app']);
			if( !defined('CONTROLLER_NAME') ) define('CONTROLLER_NAME', $cached['controller']);
			if( !defined('ACTION_NAME') ) define('ACTION_NAME', $cached['action']);
			return;
		}
		
		$matched = false;
		$pathInfoR = false;
		$zhicms_404 = '';
		
		$pathInfo = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
		if ($pathInfo && !isset($_REQUEST['r'])) {
			$_REQUEST['r'] = $pathInfo;
			$pathInfoR = true;
		}
		
		// 无论后台是否开启伪静态，只要配置了伪静态规则就尝试匹配。
		// 这样「伪静态地址」与「动态地址」都能被解析，后台开关只影响 url() 生成哪种格式，
		// 不影响两种地址的访问——实现全站伪静态/动态同时可用。
		if( !empty(self::$rewriteRule ) ) {
			if( ($pos = strpos( $_SERVER['REQUEST_URI'], '?' )) !== false ){
				parse_str( substr( $_SERVER['REQUEST_URI'], $pos + 1 ), $_GET );
			}
			$scriptDir = rtrim(dirname($_SERVER["SCRIPT_NAME"]), '/\\');
			if ($scriptDir == '/' || $scriptDir == '') {
				$scriptDir = '';
			} else {
				$scriptDir = '/' . $scriptDir;
			}
			// 归一化 REQUEST_URI：去掉入口脚本前缀（如 /index.php/brand.html -> /brand.html），
			// 兼容 nginx 的 “rewrite ^(.*)$ /index.php/$1” 配置，使伪静态规则仍可匹配
			$scriptName = $_SERVER["SCRIPT_NAME"];
			$normUri = $_SERVER['REQUEST_URI'];
			if ($scriptName && $scriptName !== '/') {
				if (stripos($normUri, $scriptName . '/') === 0) {
					$normUri = substr($normUri, strlen($scriptName));
				} elseif ($normUri === $scriptName) {
					$normUri = '/';
				}
			}
			if ($normUri === '') $normUri = '/';
			foreach(self::$rewriteRule as $rule => $mapper){
				$rule = ltrim($rule, "./\\");
				if( false === stripos($rule, 'http://')){
					$rule = $_SERVER['HTTP_HOST'] . $scriptDir . '/' . $rule;
				}
				// 将 <key> 占位符转为命名捕获组：商品/文章等 id 可能是好单库编码（字母数字混合，如 POG60...），
				// 故统一允许字母/数字/%-等，避免 tb-POG60mWHOPp9x2OmFb.html 这类链接因 id 非纯数字而无法匹配、回退首页。
				$rule = preg_replace_callback('/<([a-zA-Z0-9_]+)>/', function ($m) {
					$name = $m[1];
					// 用 \w 避免被下方 str_ireplace 把 '-' 转义而破坏字母/数字范围
					$pat  = '[\w%-]+';
					// alias 占位符（插件路由标识）严格限定为字母/数字/下划线，不含连字符，
					// 否则「plug-<alias>.html」的贪婪占位符会把「plug-kiees-cheaps.html」
					// 中的 -cheaps 也吞进 alias，导致带 -mod 的插件子页被通用规则误匹配、回退首页。
					// 注意：下方 str_ireplace 会把 '-' 全部转义成 '\-'，因此【不能用字符范围 a-z】，
					// 否则 [a-z0-9_] 会变成 [a\-z0\-9_]（连字符不再表示范围，只匹配 a/-/z/0/9/_），
					// 导致 plug-guangdiu.html 等所有插件伪静态匹配失败、全部回退首页。
					// \w 恰好等价于 [A-Za-z0-9_] 且不含 '-'，不会被转义破坏。
					if ($name === 'alias') {
						$pat = '\w+';
					}
					// platform 占位符内容只能是平台标识（tb/jd/pdd/vip）。
					// 注意：本条 str_ireplace 会把 '-' 全部转义成 '\-'，因此【不能用字符范围 a-z】，
					// 否则 [a-z] 会变成 [a\-z]（连字符不再表示范围，只匹配 a/-/z），导致所有 buy 链接匹配失败。
					// 这里用 \w（不含 '-'，不被转义破坏），platform 合法性由 RedirectController::jump 的白名单二次校验。
					if ($name === 'platform') {
						$pat = '\w+';
					}
					return '(?<' . $name . '>' . $pat . ')';
				}, $rule);
				$rule = '/'.str_ireplace(array('\\\\', 'http://', '-', '/', '.'), array('', '', '\-', '\/', '\.'), $rule).'/i';
				if( preg_match($rule, $_SERVER['HTTP_HOST'] . $normUri, $matches) ){
				foreach($matches as $matchkey => $matchval){
					if(('app' === $matchkey)){
						$mapper = str_ireplace('<app>', $matchval, $mapper);
					}else if('c' === $matchkey){
						$mapper = str_ireplace('<c>', $matchval, $mapper);
					}else if('a' === $matchkey){
						$mapper = str_ireplace('<a>', $matchval, $mapper);
					} else {
						if( !is_int($matchkey) ){
							$_GET[$matchkey] = $matchval;
							// 替换 mapper 中对应的 <key> 占位符（如 alias=<alias>），得到真实路由
							$mapper = str_ireplace('<'.$matchkey.'>', $matchval, $mapper);
						}
					}
				}
					$_REQUEST['r'] = $mapper;
					$matched = true;
					break;
				}
			}
		}
		
		if (!$matched && $pathInfoR && isset($_REQUEST['r'])) {
			unset($_REQUEST['r']);
		}

    $rawRoute = isset($_REQUEST['r']) ? $_REQUEST['r'] : '';
    // 1) 先剥离伪静态规则 mapper 中未替换的占位符（如 alias=<alias>），
    //    其参数已由 rewriteOn 块的命名捕获组写入 $_GET，此处不可覆盖。
    $pureRoute = preg_replace('/\/[a-zA-Z_][a-zA-Z0-9_]*=\<[a-zA-Z0-9_]+\>/i', '', $rawRoute);
    // 2) 提取真实动态路由参数（如 ?r=index/page/index/id=1）写入 $_GET，与伪静态行为一致。
    $pureRoute = preg_replace_callback('/\/([a-zA-Z_][a-zA-Z0-9_]*)=([^\/]+)/i', function ($m) {
        if (!isset($_GET[$m[1]])) {
            $_GET[$m[1]] = urldecode($m[2]);
        }
        return '';
    }, $pureRoute);
	    $routeArr = !empty($pureRoute) ? explode("/", $pureRoute) : array();
	    if(empty($routeArr)){
	    	$zhicms_404=Config::get('DEFAULT_CONTROLLER');
	    }
	
		if(isset($_GET['spm'])){
			$zhicms_404=Config::get('DEFAULT_CONTROLLER');
		}

	    if($_SERVER['REQUEST_URI']=='/' ||  $_SERVER['REQUEST_URI']=='/index.php'){
	    	$zhicms_404=Config::get('DEFAULT_CONTROLLER');
	    }


	    if($zhicms_404==""){
	    	$zhicms_404=Config::get('DEFAULT_CONTROLLER');
	    }

		$app_name = empty($routeArr[0]) ? Config::get('DEFAULT_APP') : $routeArr[0];
		if (count($routeArr) == 1) {
			// 仅 app 段（如 r=install）：controller/action 回退默认值，避免解析成 DefaultController
			$controller_name = Config::get('DEFAULT_CONTROLLER');
			$action_name = Config::get('DEFAULT_ACTION');
		} else {
			$controller_name = empty($routeArr[1]) ? $zhicms_404 : $routeArr[1];
			$action_name = empty($routeArr[2]) ? Config::get('DEFAULT_ACTION') : $routeArr[2];
		}
		$_REQUEST['r'] = $app_name .'/'. $controller_name .'/'. $action_name;
		
		// 将 action 名称转为驼峰命名
		$action_name = strtolower($action_name);
		if (strpos($action_name, '_') !== false) {
			// 有下划线：add_link -> addLink
			$action_name = preg_replace_callback('/_([a-z])/', function($m) {
				return strtoupper($m[1]);
			}, $action_name);
		}
		// 无下划线：addlink -> Addlink (首字母大写)
		$action_name = ucfirst($action_name);
		// 路由段安全校验：app/controller/action 仅允许字母数字下划线，拒绝 .. \ 空字节等，防止穿越/异常类加载
		foreach (array($app_name, $controller_name, $action_name) as $seg) {
			if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$seg)) {
				$app_name = Config::get('DEFAULT_APP');
				$controller_name = Config::get('DEFAULT_CONTROLLER');
				$action_name = Config::get('DEFAULT_ACTION');
				break;
			}
		}
		if( !defined('APP_NAME') ) define('APP_NAME', strtolower($app_name));
		// CONTROLLER_NAME / ACTION_NAME 统一为小写，便于模板中大小写无关地判断当前页（与 routeCache 缓存路径一致）
		if( !defined('CONTROLLER_NAME') ) define('CONTROLLER_NAME', strtolower($controller_name));
		if( !defined('ACTION_NAME') ) define('ACTION_NAME', strtolower($action_name));
		
		$routeCache[$cacheKey] = array(
			'app' => strtolower($app_name),
			'controller' => ucfirst($controller_name),
			'action' => $action_name
		);

	}

	static public function url($route=null, $params=array()){
		$app = \APP_NAME;
		$controller = \CONTROLLER_NAME;
		$action = \ACTION_NAME;
		$fullRoute = $route;
		if($route){
			$route = explode('/', $route, 3);
			$routeNum = count($route);
			switch ($routeNum) {
				case 1:
					$action = $route[0];
					break;
				case 2:
					$controller = $route[0];
					$action = $route[1];
					break;
				case 3:
					$app = $route[0];
					$controller = $route[1];
					$action = $route[2];
					break;
			}
		}
		$baseRoute = $app.'/'.$controller.'/'.$action;

		// 兜底：若 Route::parseUrl 因常量已定义而被跳过，self::$rewriteOn 会停留在类初始值 false，
		// 导致 URL 退化成动态地址且占位符（<platform>/<id>）原样残留。此处从 Config 重新同步，
		// 保证伪静态开关真正生效。
		$rewriteOn = self::$rewriteOn;
		$rewriteRule = self::$rewriteRule;
		if (!$rewriteOn) {
			if (class_exists('\\ZhiCms\\base\\Config')) {
				$cfgOn = \ZhiCms\base\Config::get('REWRITE_ON');
				$cfgRule = \ZhiCms\base\Config::get('REWRITE_RULE');
				if ($cfgOn) {
					$rewriteOn = $cfgOn;
					if (!empty($cfgRule)) $rewriteRule = $cfgRule;
				}
			}
		}

		$paramStr = empty($params) ? '' : '&' . http_build_query($params);
		// 动态 URL 兜底：把路由中的 <占位符> 用参数值替换，避免伪静态未生效时
		// 生成 index.php?r=.../platform=%3Cplatform%3E 这类畸形链接。
		$cleanRoute = $baseRoute;
		if (!empty($params)) {
			foreach ($params as $k => $v) {
				$cleanRoute = str_ireplace('<'.$k.'>', $v, $cleanRoute);
			}
		}
		$url = ($_SERVER["SCRIPT_NAME"] ?? '/index.php') . '?r=' . $cleanRoute . $paramStr;
			
		if( $rewriteOn && !empty($rewriteRule ) ) {
			static $urlArray = array();
			if( !isset($urlArray[$url]) ){
				$routeBase = preg_replace('/\/[a-zA-Z_][a-zA-Z0-9_]*=\<[a-zA-Z0-9_]+\>/i', '', $baseRoute);
				foreach($rewriteRule as $rule => $mapper){
					$mapperBase = preg_replace('/\/[a-zA-Z_][a-zA-Z0-9_]*=\<[a-zA-Z0-9_]+\>/i', '', $mapper);
					
					if( $mapperBase == $routeBase ){
						list($app, $controller, $action) = explode('/', $baseRoute, 3);
						$action = preg_replace('/\/[a-zA-Z_][a-zA-Z0-9_]*=.*$/i', '', $action);
						$urlArray[$url] = $rule;
						$urlArray[$url] = str_ireplace('<app>', $app, $urlArray[$url]);
						$urlArray[$url] = str_ireplace('<c>', $controller, $urlArray[$url]);
						$urlArray[$url] = str_ireplace('<a>', $action, $urlArray[$url]);
						if( !empty($params) ){
							$_args = array();
							foreach($params as $argkey => $arg){
								$count = 0;
								$urlArray[$url] = str_ireplace('<'.$argkey.'>', $arg, $urlArray[$url], $count);
								if(!$count) $_args[$argkey] = $arg;
							}

							if( !empty($_args) ){
								$urlArray[$url] = preg_replace('/<\w+>/', '', $urlArray[$url]). '?' . http_build_query($_args);
							}	
						} else {
							$urlArray[$url] = preg_replace('/<\w+>/', '', $urlArray[$url]);
						}

						if(false === stripos($urlArray[$url], 'http://') && false === stripos($urlArray[$url], 'https://')){
							$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
							$urlArray[$url] = $scheme.($_SERVER['HTTP_HOST'] ?? '').rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? '/index.php'), "./\\") .'/'.ltrim($urlArray[$url], "./\\");
						}
						
						return $urlArray[$url];
					}
				}
				return isset($urlArray[$url]) ? $urlArray[$url] : $url;
			}
			return $urlArray[$url];
		}
		return $url;
	}
}
