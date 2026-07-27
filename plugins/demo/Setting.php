<?php
namespace plugins\demo;

use ZhiCms\base\plugin\BasePlugin;

/**
 * 插件后台设置组件
 * 后台“插件管理 → 设置”会实例化此类并调用 view()/save()
 */
class Setting extends BasePlugin
{
	/** 渲染设置表单（返回 HTML 字符串） */
	public function view(){
		return $this->includeView('setting', array(
			'cfg' => $this->getConfig(),
		));
	}

	/** 保存设置：接收 POST 数据，返回要持久化的配置数组 */
	public function save($post){
		return array(
			'site_name'      => isset($post['site_name']) ? trim(strip_tags($post['site_name'])) : '',
			'enable_banner'  => isset($post['enable_banner']) ? 1 : 0,
		);
	}
}
