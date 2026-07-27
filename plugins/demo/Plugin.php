<?php
namespace plugins\demo;

use ZhiCms\base\plugin\BasePlugin;

/**
 * 插件入口类（必须继承 ZhiCms\base\plugin\BasePlugin）
 * 命名空间固定为 plugins\demo（demo = 插件别名/目录名）
 */
class Plugin extends BasePlugin
{
	/**
	 * 钩子注册：插件"已启用"时每次请求都会调用
	 * 通过 Hook::add() 挂载钩子，可被系统任意位置 Hook::listen() 触发
	 */
	public function register(){
		// 注册一个示例钩子，由 Show 组件在前台页脚触发
		\ZhiCms\base\Hook::add('demoFooter', array($this, 'footerNote'), 10);
	}

	/** 钩子回调：前台页脚输出标记 */
	public function footerNote(){
		echo '<div style="text-align:center;color:#999;padding:20px 0;font-size:12px;">— Powered by ZhiCms Demo 插件 v2.0（支持 原生 / Z-Blog / WordPress / Emlog 四种格式） —</div>';
	}

	/** 首次安装：可在此执行建表逻辑（本示例无需建表，留空即可） */
	public function install(){}

	/** 卸载：可在此清理数据 */
	public function uninstall(){}
}
