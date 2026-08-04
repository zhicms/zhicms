<?php
namespace ZhiCms\base\plugin;

/**
 * 插件入口类基类
 * 每个标准插件的 Plugin.php 必须继承此类（命名空间 plugins\{alias}）
 */
abstract class BasePlugin {
	/** @var string 插件别名（目录名） */
	protected $alias = '';
	/** @var array 插件元数据（来自 plugin.json） */
	protected $meta = array();

	public function __construct($meta = array()){
		$this->meta = is_array($meta) ? $meta : array();
		$this->alias = $this->meta['alias'] ?? '';
	}

	/**
	 * 钩子注册：插件处于“已启用”状态时，每次请求都会调用
	 * 在此通过 \ZhiCms\base\Hook::add() 挂载钩子
	 */
	public function register(){}

	/** 首次安装：建表 / 初始化数据 */
	public function install(){}

	/** 卸载：清理表 / 文件 */
	public function uninstall(){}

	/** 启用 */
	public function enable(){}

	/** 停用 */
	public function disable(){}

	/** 读取插件自身配置 */
	protected function getConfig(){
		return \ZhiCms\base\PluginManager::getConfig($this->alias);
	}

	/** 保存插件自身配置 */
	protected function setConfig($data){
		return \ZhiCms\base\PluginManager::setConfig($this->alias, $data);
	}

	/**
	 * 前台展示页入口：插件覆写此方法输出自己的展示页。
	 * 通过 app\index\controller\PlugController::view() 调度，
	 * 兼容动态与伪静态两种访问。
	 * @param array $params 除 alias 外的 GET/POST 参数（如 id）
	 */
	public function displayPage($params = array()){
		// 子类覆写：echo 页面内容（可自行 include 插件 view）
	}

	/**
	 * 生成本插件展示页链接（伪静态优先，动态兜底）
	 * @param array $params 参数，如 array('id' => 123)
	 * @return string
	 */
	public function pageUrl($params = array()){
		return \ZhiCms\base\PluginManager::url($this->alias, $params);
	}

	/**
	 * 渲染插件私有视图（plugins/{alias}/view/{tpl}.html）
	 * 复用框架的 think-template 引擎
	 */
	protected function render($tpl, $vars = array()){
		$config = \ZhiCms\base\Config::get('TPL');
		$engine = new \ZhiCms\base\ThinkTemplate($config);
		$file = \BASE_PATH . 'plugins/' . $this->alias . '/view/' . $tpl . '.html';
		return $engine->display($file, true, true);
	}

	/**
	 * 直接包含插件私有 PHP 视图（plugins/{alias}/view/{tpl}.php）
	 * 适合需要原生 PHP 灵活性的场景
	 */
	protected function includeView($tpl, $vars = array()){
		$file = \BASE_PATH . 'plugins/' . $this->alias . '/view/' . $tpl . '.php';
		if (!is_file($file)) return '';
		extract($vars);
		ob_start();
		include $file;
		return ob_get_clean();
	}
}
