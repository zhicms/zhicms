<?php
namespace ZhiCms\base;
class Hook{
	static public $tags = array();
	// 动态注册的钩子（插件通过 Hook::add 注册），[priority][] => callable
	static public $dynTags = array();
	
	static public function init($basePath=''){		
		$dir = str_replace('/', DIRECTORY_SEPARATOR, $basePath.'app/base/hook/');
		foreach(glob($dir . '*.php') as $file){
			$pos = strrpos($file, DIRECTORY_SEPARATOR);
			if( false === $pos ) continue;
			
			$class = substr($file, $pos + 1, -4);		
			$class = "\\app\\base\\hook\\{$class}";
			
			if (!class_exists($class)) continue;
			$methods = get_class_methods($class);
			foreach((array)$methods as $method){
				self::$tags[$method][] = $class;
			}		
		}
	}

	/**
	 * 动态注册钩子（插件系统使用）
	 * 支持多种回调形式：
	 *   - [对象, '方法']        数组
	 *   - 'Class::method'       静态方法
	 *   - 'Class@method'        动态实例化调用
	 *   - 'function_name'       普通函数
	 *   - 闭包 Closure
	 * @param string         $tag      钩子名称
	 * @param callable|array $callable 回调
	 * @param int            $priority 优先级，数值越小越先执行
	 */
	static public function add($tag, $callable, $priority = 10){
		self::$dynTags[$tag][(int)$priority][] = $callable;
	}

	/**
	 * 触发钩子：同时执行类监听器（app/base/hook）与动态注册的回调
	 * @param string $tag    钩子名称
	 * @param array  $params 参数
	 * @param mixed  $result 引用返回最后一个监听器结果
	 */
	static public function listen($tag, $params=array(), &$result=null){
		$listeners = array();
		if (isset(self::$tags[$tag])) {
			foreach (self::$tags[$tag] as $class) {
				$listeners[10][] = $class;
			}
		}
		if (isset(self::$dynTags[$tag])) {
			foreach (self::$dynTags[$tag] as $priority => $callables) {
				foreach ($callables as $cb) {
					$listeners[(int)$priority][] = $cb;
				}
			}
		}
		if (empty($listeners)) return false;
		ksort($listeners);
		foreach ($listeners as $items) {
			foreach ($items as $cb) {
				$result = self::exec($cb, $tag, $params);
				if (false === $result) {
					break 2;
				}
			}
		}
		return true;
	}

	/**
	 * 过滤器式触发：按顺序执行所有监听器，前一个返回值作为后一个首个参数。
	 * 适用于“改写变量”场景（如文章内容、上传结果），对应 emlog 的 doMultiAction。
	 * @param string $tag    钩子名称
	 * @param mixed  $value  被过滤的值（传入引用，最终被改写）
	 * @param array  $params 附加参数
	 * @return mixed 过滤后的结果
	 */
	static public function filter($tag, $value = null, $params = array()){
		$listeners = self::gather($tag);
		if (empty($listeners)) return $value;
		ksort($listeners);
		foreach ($listeners as $items) {
			foreach ($items as $cb) {
				$value = call_user_func(self::normalize($cb), array_merge(array($value), (array)$params));
			}
		}
		return $value;
	}

	/** 收集某钩子的全部监听器，按优先级分组（兼容类监听器与动态回调） */
	static protected function gather($tag){
		$listeners = array();
		if (isset(self::$tags[$tag])) {
			foreach (self::$tags[$tag] as $class) {
				$listeners[10][] = $class;
			}
		}
		if (isset(self::$dynTags[$tag])) {
			foreach (self::$dynTags[$tag] as $priority => $callables) {
				foreach ($callables as $cb) {
					$listeners[(int)$priority][] = $cb;
				}
			}
		}
		return $listeners;
	}

	static protected function exec($callback, $method, $params){
		// 类监听器（旧机制）：字符串类名，调用与钩子同名的方法
		if (is_string($callback) && class_exists($callback)) {
			static $objArr = array();
			if (!isset($objArr[$callback])) {
				$objArr[$callback] = new $callback();
			}
			return call_user_func_array(array($objArr[$callback], $method), (array)$params);
		}
		// 动态回调
		return call_user_func_array(self::normalize($callback), (array)$params);
	}

	/**
	 * 收集某钩子全部监听器（扁平数组，已合并类监听器与动态回调，不分优先级）
	 * 供兼容层 doOnceAction 等使用。
	 */
	static public function listeners($tag){
		$out = array();
		foreach (self::gather($tag) as $items) {
			foreach ($items as $cb) $out[] = $cb;
		}
		return $out;
	}

	/** 取某钩子首个监听器（doOnceAction 语义） */
	static public function firstListener($tag){
		$ls = self::listeners($tag);
		return $ls ? $ls[0] : null;
	}

	/**
	 * 将各类回调形式统一为 call_user_func_array 可执行的 callable
	 */
	static public function normalize($callback){
		if (is_string($callback)) {
			if (strpos($callback, '::') !== false) {
				return explode('::', $callback, 2);
			}
			if (strpos($callback, '@') !== false) {
				list($class, $method) = explode('@', $callback, 2);
				return array(new $class(), $method);
			}
			return $callback; // 普通函数名
		}
		return $callback; // [对象,方法] 或闭包
	}
}
