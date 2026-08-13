<?php
namespace app\manage\controller;
error_reporting(0);
use \app\base\controller\ManageControllerTrait;
class PluginController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

	/** 插件市场：已安装列表 + 未安装（目录存在）列表 */
	public function index(){
		$this->checkManageSession();

		$this->pageText = array("云控中心", "插件管理");

		// 表结构未就绪时给出升级提示
		$this->needUpgrade = \ZhiCms\base\PluginManager::needUpgrade();

		$list = array();
		foreach (\ZhiCms\base\PluginManager::getInstalled() as $row) {
			$alias = $row['alias'];
			$meta = \ZhiCms\base\PluginManager::readMeta($alias);

			// 先判断插件实际类型（即使没有 plugin.json，也可能是兼容格式）
			$type = class_exists('\\ZhiCms\\base\\compat\\Compat')
				? \ZhiCms\base\compat\Compat::detectType($alias) : false;

			if ($meta === null) {
				// 兼容格式插件（emlog / zblog / wordpress）：用 Compat 读取元信息
				if ($type && $type !== 'native') {
					$compatMeta = \ZhiCms\base\compat\Compat::readCompatMeta($alias, $type);
					$row = array_merge($row, $compatMeta);
					$row['_valid'] = 1;
					$row['_type']  = $type;
					$list[] = $row;
					continue;
				}
				// 真·文件缺失的残留记录（目录已不存在）
				$row['_valid'] = 0;
				$list[] = $row;
				continue;
			}
			$row = array_merge($row, $meta);
			$row['_valid'] = 1;
			// 标注插件类型（native 返回 false，兼容返回 emlog/zblog/wordpress）
			$row['_type'] = $type ?: 'native';
			$list[] = $row;
		}
		$this->list = $list;

		$available = array();
		foreach (\ZhiCms\base\PluginManager::scanAvailable() as $meta) {
			$available[] = $meta;
		}
		$this->available = $available;

		$this->display();
	}

	/** 升级插件表结构（首次使用一键执行） */
	public function upgradeTable(){
		$this->checkManageSession();
		try {
			\ZhiCms\base\PluginManager::upgradeTable();
			$this->alert('插件表结构已升级完成', 'index.php?r=manage/plugin/index');
		} catch (\Throwable $e) {
			$this->alert('升级失败：' . $e->getMessage());
		}
	}

	/** 上传插件压缩包并解压到 plugins/（支持 .zip 和 .zba 格式） */
	public function upload(){
		$this->checkManageSession();
		if (!$this->isPost()) {
			$this->redirect('index.php?r=manage/plugin/index');
		}
		if (empty($_FILES['file']) || $_FILES['file']['error'] != 0) {
			$this->alert('请选择插件文件（.zip / .zba）');
		}
		$name = $_FILES['file']['name'];
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (!in_array($ext, array('zip', 'zba'))) {
			$this->alert('仅支持 .zip 或 .zba 格式的插件包');
		}
		$tmp = $_FILES['file']['tmp_name'];

		// 根据扩展名选择解压方式
		if ($ext === 'zba') {
			$alias = $this->handleZba($tmp);
		} else {
			$alias = $this->handleZip($tmp);
		}

		if (!$alias) {
			$this->alert('插件包结构不正确：无法识别插件格式（支持：原生/Z-Blog/WordPress/Emlog）');
		}
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $alias)) {
			$this->alert('插件别名只能包含字母、数字、下划线、连字符');
		}
		$destDir = \BASE_PATH . 'plugins/' . $alias;
		if (is_dir($destDir)) {
			$this->alert('该插件已存在，请先卸载后再上传');
		}

		// 解压
		if ($ext === 'zba') {
			// .zba 已在上面的 handleZba 中解压
		} else {
			if (!$this->unzip($tmp, \BASE_PATH . 'plugins/')) {
				$this->alert('解压失败，请检查目录写入权限');
			}
		}

		// 校验插件格式有效
		$type = \ZhiCms\base\compat\Compat::detectType($alias);
		if ($type === false) {
			self::rrmdir($destDir);
			$this->alert('插件包缺少有效的标识文件（plugin.json / plugin.xml / Plugin Name 头注释）');
		}

		$this->alert('上传成功，请点击"安装"完成启用', 'index.php?r=manage/plugin/index');
	}

	/** 安装插件（建表 + 启用） */
	public function install(){
		$this->checkManageSession();
		$alias = $this->arg('alias');
		if (empty($alias)) $this->alert('参数错误');
		try {
			\ZhiCms\base\PluginManager::install($alias);
		} catch (\Throwable $e) {
			$this->alert('安装失败：' . $e->getMessage());
		}
		$this->alert('插件安装并启用成功', 'index.php?r=manage/plugin/index');
	}

	/** 启用 */
	public function enable(){
		$this->checkManageSession();
		$alias = $this->arg('alias');
		if (empty($alias)) $this->alert('参数错误');
		\ZhiCms\base\PluginManager::enable($alias);
		\ZhiCms\ext\AdminLog::write('plugin', '启用了插件：' . $alias);
		$this->redirect('index.php?r=manage/plugin/index');
	}

	/** 停用 */
	public function disable(){
		$this->checkManageSession();
		$alias = $this->arg('alias');
		if (empty($alias)) $this->alert('参数错误');
		\ZhiCms\base\PluginManager::disable($alias);
		\ZhiCms\ext\AdminLog::write('plugin', '停用了插件：' . $alias);
		$this->redirect('index.php?r=manage/plugin/index');
	}

	/** 卸载插件 */
	public function uninstall(){
		$this->checkManageSession();
		$alias = $this->arg('alias');
		if (empty($alias)) $this->alert('参数错误');
		$deleteFiles = $this->arg('del') == '1';
		\ZhiCms\base\PluginManager::uninstall($alias, $deleteFiles);
		\ZhiCms\ext\AdminLog::write('plugin', '卸载了插件：' . $alias);
		$this->redirect('index.php?r=manage/plugin/index');
	}

	/**
	 * 兼容插件 AJAX 代理
	 * Z-Blog 兼容插件的 JavaScript 发起 AJAX 请求到 main.php?action=xxx，
	 * 这些请求在 ZhiCms 路由下无法直接访问。此方法作为代理：
	 * 1. 设置 Z-Blog 兼容环境
	 * 2. include 插件的 main.php，让其内置的 AJAX 处理逻辑接管请求
	 */
	public function ajax(){
		$this->checkManageSession();
		$plugin = $this->arg('plugin');
		if (empty($plugin)) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(array('status' => 'error', 'message' => '缺少插件参数'), JSON_UNESCAPED_UNICODE);
			exit;
		}
		\ZhiCms\base\compat\ZblogBridge::handlePluginAjax($plugin);
	}

	/** 插件设置 */
	public function setting(){
		$this->checkManageSession();
		$alias = $this->arg('alias');
		if (empty($alias)) $this->alert('参数错误');

		// 先尝试原生格式元信息（供 Setting 构造器/页面展示使用）
		$meta = \ZhiCms\base\PluginManager::readMeta($alias);

		// 兼容格式插件：尝试用 Compat 读取
		$compatType = null;
		if ($meta === null && class_exists('\\ZhiCms\\base\\compat\\Compat')) {
			$compatType = \ZhiCms\base\compat\Compat::detectType($alias);
			if ($compatType && $compatType !== 'native') {
				$meta = \ZhiCms\base\compat\Compat::readCompatMeta($alias, $compatType);
			}
		}
		if ($meta === null) $this->alert('插件不存在');
		if (empty($meta['hasSetting'])) $this->alert('该插件无需设置');

		// 兼容格式插件（zblog 等）：通过 Bridge 渲染插件的管理页
		if ($compatType && $compatType === 'zblog') {
			$this->pageText = array("插件管理", ($meta['name'] ?? $alias) . " 设置");

			// 处理 POST 保存（模拟 save_setting.php 的逻辑）
			if ($this->isPost()) {
				$this->saveCompatSetting($alias);
				// 保存后刷新页面
				$this->redirect('index.php?r=manage/plugin/setting&alias=' . urlencode($alias));
			}

			$this->settingContent = \ZhiCms\base\compat\ZblogBridge::renderAdmin($alias);
			$this->display();
			return;
		}

		// 原生插件：加载 Setting.php
		$settingClass = '\\plugins\\' . $alias . '\\Setting';
		$file = \BASE_PATH . 'plugins/' . $alias . '/Setting.php';
		if (!class_exists($settingClass)) {
			if (!is_file($file)) $this->alert('设置组件缺失');
			require $file;
		}
		if (!class_exists($settingClass)) $this->alert('设置组件缺失');

		$setting = new $settingClass($meta);

		if ($this->isPost()) {
			$this->checkCsrfToken();
			$clearCache = !empty($_POST['clear_cache']);
			try {
				$cfg = $setting->save($_POST);

				// 特殊处理：wxapp_packer / miniapp 插件的打包下载
				if (in_array($alias, array('wxapp_packer', 'miniapp'), true) && !empty($_POST['action']) && $_POST['action'] === 'build') {
					// 如果指定了 return_json，说明前端希望 AJAX 返回 JSON，然后再 window.open 下载
					if (!empty($_POST['return_json'])) {
						// 不在此处 exit，继续走后续 JSON 处理逻辑
					} else {
						// 直接文件流输出（iframe 表单提交会走这里）
						if (!empty($cfg['_download_path'])) {
							$filePath = $cfg['_download_path'];
							$fileName = $cfg['_download_file'] ?? basename($filePath);
							
							if (file_exists($filePath)) {
								$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
								$mimeTypes = array('zip' => 'application/zip');
								$contentType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';
								
								if (ob_get_level()) ob_end_clean();
								header('Content-Description: File Transfer');
								header('Content-Type: ' . $contentType);
								header('Content-Disposition: attachment; filename="' . $fileName . '"');
								header('Expires: 0');
								header('Cache-Control: must-revalidate');
								header('Pragma: public');
								header('Content-Length: ' . filesize($filePath));
								
								set_time_limit(0);
								$handle = fopen($filePath, 'rb');
								if ($handle === false) {
									header('HTTP/1.1 500 Internal Server Error');
									echo '无法读取文件';
									exit;
								}
								while (!feof($handle)) {
									echo fread($handle, 1024 * 1024);
									flush();
									if (connection_status() != 0) {
										fclose($handle);
										exit;
									}
								}
								fclose($handle);
								exit;
							}
						}

						header('Content-Type: text/html; charset=utf-8');
						echo '<!DOCTYPE html><html><head><title>打包失败</title><meta charset="utf-8"></head><body>';
						echo '<h2>打包失败</h2><p>请重试</p><p><a href="javascript:history.go(-1)">返回</a></p>';
						echo '</body></html>';
						exit;
					}
				}
				
				// 剥离 Setting.save() 附加的「仅用于返回前端的临时字段」，不写入数据库
				$tempKeys = array();
				foreach (array('_download_url', '_download_file', '_download_path', '_extra') as $k) {
					if (array_key_exists($k, $cfg)) {
						$tempKeys[$k] = $cfg[$k];
						unset($cfg[$k]);
					}
				}

				// 打包操作：Setting::save() 内部已保存配置（pack 分支 setConfig），此处不再覆盖，
				// 否则会把 save() 返回的临时字段剥离后的数组写回，可能清空其他配置。
				$isBuild = in_array($alias, array('wxapp_packer', 'miniapp'), true)
					&& !empty($_POST['action']) && $_POST['action'] === 'build';
				if (!$isBuild) {
					\ZhiCms\base\PluginManager::setConfig($alias, $cfg);
				}
			
				// 返回JSON给前端AJAX
				$response = array('info' => '保存成功', 'status' => 'y');
			
				// 如果是 wxapp_packer / miniapp 打包操作，返回下载信息供前端触发下载
				if ($isBuild) {
					if (!empty($tempKeys['_download_url'])) {
						$response['download_url'] = $tempKeys['_download_url'];
					}
					if (!empty($tempKeys['_download_file'])) {
						$response['download_file'] = $tempKeys['_download_file'];
					}
				}
				
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode($response, JSON_UNESCAPED_UNICODE);
				exit;
			} catch (\Throwable $e) {
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(array('info' => '保存失败：' . $e->getMessage(), 'status' => 'n'), JSON_UNESCAPED_UNICODE);
				exit;
			}
		}

		$this->settingContent = $setting->view();
		$this->pageText = array("插件管理", ($meta['name'] ?? $alias) . " 设置");
		$this->display();
	}

	/**
	 * 插件文件下载（供 wxapp_packer 等插件输出打包文件）
	 * 路由：index.php?r=manage/plugin/download&alias=xxx&file=xxx.zip
	 */
	public function download(){
		$this->checkManageSession();
		$alias = $this->arg('alias');
		if (empty($alias)) {
			header('HTTP/1.1 400 Bad Request');
			echo '缺少 alias 参数';
			exit;
		}
		// 只允许白名单中的插件使用该接口
		$allowed = array('wxapp_packer', 'miniapp');
		if (!in_array($alias, $allowed, true)) {
			header('HTTP/1.1 403 Forbidden');
			echo '该插件不允许下载文件';
			exit;
		}

		$plugin = \ZhiCms\base\PluginManager::instance($alias);
		if (!$plugin || !method_exists($plugin, 'serveDownload')) {
			header('HTTP/1.1 404 Not Found');
			echo '插件未安装或不支持下载';
			exit;
		}
		$fileName = $this->arg('file');
		$plugin->serveDownload($fileName);
	}

	/**
	 * 保存兼容插件（Z-Blog）的配置
	 * 模拟 $zbp->Config()->xxx = yyy + $zbp->SaveConfig() 的保存逻辑
	 */
	protected function saveCompatSetting($alias){
		$dir = \BASE_PATH . 'plugins/' . $alias;

		// 预置 Z-Blog 运行环境
		if (!class_exists('\\ZhiCms\\base\\compat\\ZbpShim')) {
			require_once \BASE_PATH . 'ZhiCms/base/compat/ZbpShim.php';
		}
		global $zbp;
		if (!isset($zbp) || !($zbp instanceof \ZhiCms\base\compat\ZbpShim)) {
			$zbp = new \ZhiCms\base\compat\ZbpShim();
		}
		require_once \BASE_PATH . 'ZhiCms/base/compat/zblog_api.php';

		// 加载插件 include.php
		$inc = $dir . '/include.php';
		if (is_file($inc)) {
			try { require_once $inc; } catch (\Throwable $e) {}
		}

		// 初始化 Totoro 实例（或其他插件的 init 函数）
		$initFn = $alias . '_init';
		if (function_exists($initFn)) {
			try { call_user_func($initFn); } catch (\Throwable $e) {}
		}

		// 获取 POST 中的配置键值对并写入 ZbpConfig
		$saved = false;
		// 跳过的非配置字段
		$skipKeys = array('csrf_token', 'submit', 'action', 'r', 'import_text', 'import_file');
		foreach ($_POST as $key => $val) {
			if (in_array($key, $skipKeys)) continue;
			// 去除插件名前缀（如 TOTORO_SV_RULE_xxx → SV_RULE_xxx → ZbpConfig('Totoro')->SV_RULE_xxx）
			// 也兼容不带宽前缀的字段（如 AiBase 的 ActivePlatform）
			$configKey = preg_replace('/^' . preg_quote($alias, '/') . '_/i', '', $key);
			if ($configKey !== '') {
				$zbp->Config($alias)->$configKey = $val;
				$saved = true;
			}
		}
		if ($saved) {
			$zbp->SaveConfig($alias);

			// 刷新模板缓存使配置生效
			\ZhiCms\base\PluginManager::clearBoot();
		}
	}

	/* ===================== 上传辅助 ===================== */

	/** 从 ZIP 中探测插件别名：支持 Native / Z-Blog / WordPress / Emlog 格式 */
	protected function detectAlias($zipPath){
		if (!class_exists('\\ZipArchive')) {
			return false;
		}
		$zip = new \ZipArchive();
		if ($zip->open($zipPath) !== TRUE) {
			return false;
		}

		$alias = false;

		// 1. 检测 Native 格式：{alias}/plugin.json
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = $zip->getNameIndex($i);
			if (preg_match('#^([^/]+)/plugin\.json$#', $entry, $m)) {
				$alias = $m[1];
				break;
			}
		}
		if ($alias) { $zip->close(); return $alias; }

		// 2. 检测 Z-Blog 格式：{alias}/plugin.xml
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = $zip->getNameIndex($i);
			if (preg_match('#^([^/]+)/plugin\.xml$#', $entry, $m)) {
				$alias = $m[1];
				break;
			}
		}
		if ($alias) { $zip->close(); return $alias; }

		// 3. 检测 WordPress / Emlog 格式：{alias}/{alias}.php 含 Plugin Name 头
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = $zip->getNameIndex($i);
			if (preg_match('#^([^/]+)/([^/]+\.php)$#', $entry, $m)) {
				$dirName = $m[1];
				$content = $zip->getFromIndex($i);
				if ($content !== false && preg_match('/\*\s*Plugin\s+Name\s*:\s*.+/i', $content)) {
					// 排除 Mac 元文件 __MACOSX
					if (stripos($dirName, '__MACOSX') !== false) continue;
					$alias = $dirName;
					break;
				}
			}
		}

		$zip->close();
		return $alias;
	}

	/** 处理 .zba 格式（Z-BlogPHP App 包）：解压并返回别名 */
	protected function handleZba($filePath){
		$content = @file_get_contents($filePath);
		if ($content === false) return false;

		// 检测是否是 gzip 压缩的 .zba
		$charset1 = ord(substr($content, 0, 1));
		$charset2 = ord(substr($content, 1, 1));
		if ($charset1 === 31 && $charset2 === 139) {
			// gzip 压缩
			$content = @gzdecode($content);
			if ($content === false) return false;
		}

		// 解析 XML
		$xml = @simplexml_load_string($content, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_PARSEHUGE);
		if (!$xml) return false;

		$version = (string)$xml['version'];
		if ($version !== 'php') return false;

		$id   = (string)$xml->id;
		if (empty($id)) return false;

		$destBase = \BASE_PATH . 'plugins/';
		$destDir  = $destBase . $id;

		if (is_dir($destDir)) return false;

		// 创建目录
		if (!is_dir($destDir)) {
			@mkdir($destDir, 0755, true);
		}

		// 创建子目录
		foreach ($xml->folder as $folder) {
			$f = $destBase . (string)$folder->path;
			if (!is_dir($f)) {
				@mkdir($f, 0755, true);
			}
		}

		// 提取文件
		foreach ($xml->file as $file) {
			$stream = base64_decode((string)$file->stream);
			$path   = $destBase . (string)$file->path;
			$dir    = dirname($path);
			if (!is_dir($dir)) {
				@mkdir($dir, 0755, true);
			}
			@file_put_contents($path, $stream);
		}

		// 如果是 theme 类型但安装到 plugins，则尝试兼容（某些插件可能 type=theme）
		// 确保 plugin.xml 存在于正确位置
		if (!is_file($destDir . '/plugin.xml')) {
			// 尝试从子目录中查找
			foreach (glob($destDir . '/*/plugin.xml') as $xmlFile) {
				// 将子目录内容移动到上层
				$subDir = dirname($xmlFile);
				if ($subDir !== $destDir) {
					self::moveDirContents($subDir, $destDir);
					self::rrmdir($subDir);
				}
				break;
			}
		}

		return $id;
	}

	/** 处理 ZIP 上传：解压并尝试规范目录结构 */
	protected function handleZip($tmp){
		$alias = $this->detectAlias($tmp);
		if (!$alias) return false;
		return $alias;
	}

	/** 将源目录内容移动到目标目录 */
	protected static function moveDirContents($src, $dst){
		if (!is_dir($src) || !is_dir($dst)) return;
		$items = array_diff(scandir($src), array('.', '..'));
		foreach ($items as $item) {
			$s = $src . '/' . $item;
			$d = $dst . '/' . $item;
			if (is_dir($s)) {
				if (!is_dir($d)) @mkdir($d, 0755, true);
				self::moveDirContents($s, $d);
				@rmdir($s);
			} else {
				@rename($s, $d);
			}
		}
	}

	/** 解压 zip 到目标目录（保留内部目录结构） */
	protected function unzip($zipPath, $destDir){
		if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
		if (class_exists('\\ZipArchive')) {
			$zip = new \ZipArchive();
			if ($zip->open($zipPath) === TRUE) {
				$ok = $zip->extractTo($destDir);
				$zip->close();
				return $ok;
			}
			return false;
		}
		// 回退到框架自带解压
		$zipEx = new \ZhiCms\ext\Zip();
		$stat = $zipEx->decompress($zipPath, rtrim($destDir, '/') . '/');
		return !empty($stat);
	}

	protected static function rrmdir($dir){
		if (!is_dir($dir)) return;
		$items = array_diff(scandir($dir), array('.', '..'));
		foreach ($items as $item) {
			$path = $dir . '/' . $item;
			is_dir($path) ? self::rrmdir($path) : @unlink($path);
		}
		@rmdir($dir);
	}
}
