<?php
namespace app\index\controller;

/**
 * 插件展示页统一入口控制器
 *
 * 插件若要提供「前台展示页」，只需：
 *   1. 在 plugin.json 中声明 rewrite 规则，例如：
 *        "rewrite": {
 *            "plug-<alias>-<id>.html": "index/plug/view/alias=<alias>/id=<id>"
 *        }
 *   2. 在 Plugin.php 中覆写 displayPage($params) 方法输出页面内容。
 *
 * 访问方式（两种等价）：
 *   动态： index.php?r=index/plug/view&alias=hello&id=123
 *   伪静态： plug-hello-123.html
 *
 * 框架会把伪静态规则在启动时合并进 REWRITE_RULE，因此两种方式都能正确路由到本控制器。
 */
class PlugController extends \app\base\controller\BaseController
{
	/**
	 * 插件展示页：把路由参数透传给插件自身的 displayPage()
	 */
	public function view(){
		$alias = $this->arg('alias', '');
		if ($alias === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $alias)) {
			$this->alert('插件不存在');
		}
		$plugin = \ZhiCms\base\PluginManager::instance($alias);
		if (!$plugin) {
			$this->alert('插件不存在或未安装');
		}
		if (!\ZhiCms\base\PluginManager::isEnabled($alias)) {
			$this->alert('插件未启用');
		}
		if (!method_exists($plugin, 'displayPage')) {
			$this->alert('该插件未提供展示页');
		}

		// 收集除 alias 外的所有请求参数，传给插件
		$params = array();
		foreach (array_merge($_GET, $_POST) as $k => $v) {
			if ($k === 'alias' || $k === 'r') continue;
			$params[$k] = $v;
		}

		$plugin->displayPage($params);
		exit;
	}
}
