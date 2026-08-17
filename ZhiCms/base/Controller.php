<?php
namespace ZhiCms\base;
class Controller{
	public $layout = NULL; //layout view
	protected $engineType = ''; //视图引擎：'' 用 TPL.ENGINE 配置 | 'think' 强制真引擎 | 'legacy' 强制旧引擎
	
	public function assign($name, $value=NULL){
		return $this->_getView()->assign( $name, $value);
	}
	
	public function display($tpl = '', $return = false, $isTpl = true ){
		if( $isTpl ){
			if( empty($tpl) ){
				$tpl = 'app/'.\APP_NAME . '/view/' . strtolower(\CONTROLLER_NAME) . config('TPL.TPL_DEPR') . strtolower(\ACTION_NAME);
			}
			if( $this->layout ){
				$this->__template_file = $tpl;
				$tpl = $this->layout;
			}
		}	
		$this->_getView()->assign( get_object_vars($this));
		return $this->_getView()->display($tpl, $return, $isTpl);
	}
	
	public function isPost(){
		// 修复：$_SERVER 键名无反斜杠，'\REQUEST_METHOD' 会取到 null 导致恒为 false
		return ($_SERVER['REQUEST_METHOD'] ?? '') == 'POST';
	}
	
	public function redirect( $url, $code=302) {
		header('location:' . $url, true, $code);
		exit;
	}
	
	public function alert($msg, $url = NULL, $charset='utf-8'){
		header("Content-type: text/html; charset={$charset}"); 
		$alert_msg="alert('$msg');";
		if( empty($url) ) {
			$go_url = 'history.go(-1);';
		}else{
			$go_url = "window.location.href = '{$url}'";
		}
		echo "<script>$alert_msg $go_url</script>";
		exit;
	}
	

	public function Postarg($name=null, $default = null ){
		static $args;
		if( !$args ){
			$args = array_merge((array)$_POST);
		}
		$args= preg_replace( "@<script(.*?)</script>@is", "", $args); 
		$args= preg_replace( "@<iframe(.*?)</iframe>@is", "", $args ); 
		$args= preg_replace( "@<style(.*?)</style>@is", "", $args);
		$args= preg_replace( "@<(.*?)>@is", "", $args); 
		if( null==$name ) return $args;
		if( !isset($args[$name]) ) return $default;
		$arg = $args[$name];
		if( is_array($arg) ){
			array_walk($arg, function(&$v, $k){$v = trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));} );
		}else{
			$arg = trim(htmlspecialchars($arg, ENT_QUOTES, 'UTF-8'));
		}
		return $arg;

		

	}

	public function arg($name=null, $default = null ){
		static $args;
		if( !$args ){
			$args = array_merge((array)$_GET, (array)$_POST);
		}
		$args= preg_replace( "@<script(.*?)</script>@is", "", $args); 
		$args= preg_replace( "@<iframe(.*?)</iframe>@is", "", $args ); 
		$args= preg_replace( "@<style(.*?)</style>@is", "", $args);
		$args= preg_replace( "@<(.*?)>@is", "", $args); 
		if( null==$name ) return $args;
		if( !isset($args[$name]) ) return $default;
		$arg = $args[$name];
		if( is_array($arg) ){
			$arg = $this->escapeArray($arg);
		}else{
			$arg = trim(htmlspecialchars($arg, ENT_QUOTES, 'UTF-8'));
		}
		return $arg;
	}

	/**
	 * 递归转义数组的值与键名，防止模板输出时键名造成 XSS
	 */
	protected function escapeArray($arr){
		$out = array();
		foreach ($arr as $k => $v) {
			$key = is_string($k) ? htmlspecialchars($k, ENT_QUOTES, 'UTF-8') : $k;
			$out[$key] = is_array($v) ? $this->escapeArray($v) : (is_string($v) ? trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8')) : $v);
		}
		return $out;
	}

	
	protected function _getView(){
		static $view;		
		if( !isset($view) ){
			$engine = !empty($this->engineType) ? $this->engineType : Config::get('TPL.ENGINE');
			if( $engine === 'think' ){
				$view = new ThinkTemplate( Config::get('TPL') );
			} else {
				$view = new Template( Config::get('TPL') );
			}
		}		
		return $view;
	}

    
       
}