<?php
namespace app\plug\controller;
error_reporting(0);
use \app\base\controller\BaseController;

/**
 * 插件前台展示分发器
 * 路由：index.php?r=plug/plugin/show&alias={别名}&act={方法}
 * 仅“已启用”的插件可被访问
 */
class PluginController extends BaseController
{
	public function show(){
		$alias = $this->arg('alias');
		$act   = $this->arg('act', 'index');

		if (empty($alias) || !\ZhiCms\base\PluginManager::isEnabled($alias)) {
			$this->alert('插件不存在或未启用');
		}

		$showClass = '\\plugins\\' . $alias . '\\Show';
		$file = \BASE_PATH . 'plugins/' . $alias . '/Show.php';
		if (!class_exists($showClass)) {
			if (!is_file($file)) $this->alert('插件前台组件缺失');
			require $file;
		}
		if (!class_exists($showClass)) $this->alert('插件前台组件缺失');

		$meta = \ZhiCms\base\PluginManager::readMeta($alias);
		$show = new $showClass($meta);

		if (!method_exists($show, $act)) {
			$act = 'index';
		}
		$show->$act();
	}
}
