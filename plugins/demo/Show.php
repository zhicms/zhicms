<?php
namespace plugins\demo;

use ZhiCms\base\plugin\BasePlugin;

/**
 * 插件前台展示组件
 * 访问：index.php?r=plug/plugin/show&alias=demo&act=index
 */
class Show extends BasePlugin
{
	public function index(){
		$cfg     = $this->getConfig();
		$siteName = $cfg['site_name'] ?? 'ZhiCms Demo';

		echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>' . htmlspecialchars($siteName) . '</title>';
		echo '<style>
			body{font-family:-apple-system,BlinkMacSystemFont,"Microsoft YaHei",sans-serif;background:#f5f6fa;margin:0;padding:40px;color:#303133}
			.card{background:#fff;max-width:640px;margin:0 auto;padding:30px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.06)}
			h1{margin:0 0 10px;font-size:22px}
			.badge{display:inline-block;background:#409EFF;color:#fff;padding:3px 10px;border-radius:10px;font-size:12px;margin-bottom:14px}
			p{line-height:1.8;color:#606266}
			.banner{background:#ECF5FF;color:#409EFF;padding:12px 16px;border-radius:8px;margin:16px 0}
		</style>';
		echo '</head><body><div class="card">';
		echo '<span class="badge">Demo 插件前台</span>';
		echo '<h1>' . htmlspecialchars($siteName) . '</h1>';
		echo '<p>这是标准化插件的“前台展示”组件（<code>plugins/demo/Show.php</code>）。</p>';
		if (!empty($cfg['enable_banner'])) {
			echo '<div class="banner">欢迎语横幅已开启 —— 该配置来自插件后台设置。</div>';
		}
		echo '<p style="font-size:13px;color:#909399;">前往后台“插件管理 → 设置”可修改上方配置；页脚由插件注册的 Hook 输出。</p>';
		echo '</div>';

		// 触发本插件在 register() 中注册的钩子（演示 Hook 机制）
		\ZhiCms\base\Hook::listen('demoFooter');

		echo '</body></html>';
	}
}
