<?php
namespace ZhiCms\base;

/**
 * 插件管理器（静态工具类）
 * 负责：
 *   - 应用启动时加载已启用插件并触发 register()
 *   - 插件的安装 / 卸载 / 启用 / 停用
 *   - 插件元信息读取、配置读写、后台菜单收集
 *   - 插件表（yun_plug）结构自检与升级
 *
 * 插件统一存放于 网站根目录/plugins/{alias}/
 * 入口类：plugins\{alias}\Plugin  （继承 ZhiCms\base\plugin\BasePlugin）
 */
class PluginManager {

	const PLUGINS_DIR = 'plugins';

	protected static $booted = false;

	/**
	 * 应用启动钩子：加载所有“已启用”插件并注册其钩子
	 * 在 App::run() 中 Hook::init() 之后调用
	 */
	public static function boot(){
		if (self::$booted) return;
		self::$booted = true;

		if (!self::tableReady()) return;

		try {
			$rows = self::db()->query("SELECT * FROM {pre}plug WHERE `status` = 1");
		} catch (\Throwable $e) {
			return;
		}
		foreach ((array)$rows as $row) {
			self::registerPlugin($row);
		}
	}

	/**
	 * 实例化插件入口类并调用 register()
	 */
	protected static function registerPlugin($row){
		$alias = $row['alias'];
		$meta = self::readMeta($alias);
		if ($meta === null) return;
		$class = '\\plugins\\' . $alias . '\\Plugin';
		$file  = \BASE_PATH . self::PLUGINS_DIR . '/' . $alias . '/Plugin.php';
		if (!class_exists($class)) {
			if (!is_file($file)) return;
			require $file;
		}
		if (!class_exists($class)) return;
		try {
			$plugin = new $class($meta);
			if (method_exists($plugin, 'register')) {
				$plugin->register();
			}
		} catch (\Throwable $e) {
			// 单个插件注册失败不应影响整站
		}
	}

	/**
	 * 扫描插件目录，返回“有文件但未安装”的插件元信息列表
	 */
	public static function scanAvailable(){
		$available = array();
		$base = \BASE_PATH . self::PLUGINS_DIR . '/';
		if (!is_dir($base)) return $available;
		foreach (glob($base . '*', GLOB_ONLYDIR) as $dir) {
			$alias = basename($dir);
			if (!is_file($dir . '/plugin.json')) continue;
			$meta = self::readMeta($alias);
			if ($meta === null) continue;
			if (!self::hasRecord($alias)) {
				$available[] = $meta;
			}
		}
		// 合并 emlog / zblog 格式插件
		if (class_exists('\\ZhiCms\\base\\compat\\Compat')) {
			$available = array_merge($available, \ZhiCms\base\compat\Compat::scanAvailable());
		}
		return $available;
	}

	/** 读取已安装插件列表（按 id 倒序） */
	public static function getInstalled(){
		if (!self::tableReady()) return array();
		try {
			return self::db()->query("SELECT * FROM {pre}plug ORDER BY `id` DESC");
		} catch (\Throwable $e) {
			return array();
		}
	}

	/** 收集已启用插件的后台菜单（供 nav.html 动态注入） */
	public static function getAdminMenus(){
		$menus = array();
		if (!self::tableReady()) return $menus;
		try {
			$rows = self::db()->query("SELECT `alias` FROM {pre}plug WHERE `status` = 1");
		} catch (\Throwable $e) {
			return $menus;
		}
		foreach ($rows as $row) {
			$alias = $row['alias'];
			$meta  = self::readMeta($alias);

			// 兼容格式插件：用 Compat 读取（含有 hasSetting / menu）
			if ($meta === null && class_exists('\\ZhiCms\\base\\compat\\Compat')) {
				$type = \ZhiCms\base\compat\Compat::detectType($alias);
				if ($type && $type !== 'native') {
					$meta = \ZhiCms\base\compat\Compat::readCompatMeta($alias, $type);
				}
			}
			if (empty($meta['menu']) || !is_array($meta['menu'])) continue;
			foreach ($meta['menu'] as $m) {
				$menus[] = array(
					'title' => $m['title'] ?? ($meta['name'] ?? $alias),
					'url'   => $m['url']   ?? ('index.php?r=manage/plugin/setting&alias=' . $alias),
				);
			}
		}
		return $menus;
	}

	/** 读取插件元信息 plugin.json */
	public static function readMeta($alias){
		$file = \BASE_PATH . self::PLUGINS_DIR . '/' . $alias . '/plugin.json';
		if (!is_file($file)) return null;
		$json = json_decode(@file_get_contents($file), true);
		if (!is_array($json)) return null;
		$json['alias'] = $alias;
		return $json;
	}

	/** 安装插件：执行 install.sql + 生命周期 install() + 写入注册表 */
	public static function install($alias){
		// 兼容插件（emlog / zblog 格式）：走兼容层安装流程
		$type = \ZhiCms\base\compat\Compat::detectType($alias);
		if ($type && $type !== 'native') {
			$meta = \ZhiCms\base\compat\Compat::readCompatMeta($alias, $type);
			$exists = self::db()->query("SELECT `id` FROM {pre}plug WHERE `alias` = ?", array($alias));
			if (empty($exists)) {
				self::db()->execute(
					"INSERT INTO {pre}plug (`alias`,`name`,`version`,`author`,`status`,`installed`,`config`,`addtime`) "
					. "VALUES (?,?,?,?,1,1,'',?)",
					array($alias, $meta['name'] ?? $alias, $meta['version'] ?? '1.0.0', $meta['author'] ?? '', time())
				);
			} else {
				self::db()->execute("UPDATE {pre}plug SET `status`=1,`installed`=1 WHERE `alias`=?", array($alias));
			}
			\ZhiCms\base\compat\Compat::install($alias);
			self::clearBoot();
			return true;
		}

		$meta = self::readMeta($alias);
		if ($meta === null) throw new \Exception('插件不存在或缺少 plugin.json');

		self::runInstallSql($alias);

		$plugin = self::instance($alias, $meta);
		if ($plugin && method_exists($plugin, 'install')) {
			$plugin->install();
		}

		$now = time();
		$exists = self::db()->query("SELECT `id` FROM {pre}plug WHERE `alias` = ?", array($alias));
		if (empty($exists)) {
			self::db()->execute(
				"INSERT INTO {pre}plug (`alias`,`name`,`version`,`author`,`status`,`installed`,`config`,`addtime`) "
				. "VALUES (?,?,?,?,1,1,'',?)",
				array($alias, $meta['name'] ?? $alias, $meta['version'] ?? '1.0.0', $meta['author'] ?? '', $now)
			);
		} else {
			self::db()->execute("UPDATE {pre}plug SET `status`=1,`installed`=1 WHERE `alias`=?", array($alias));
		}
		self::clearBoot();
		return true;
	}

	/** 启用插件 */
	public static function enable($alias){
		$type = \ZhiCms\base\compat\Compat::detectType($alias);
		if (!$type || $type === 'native') {
			$plugin = self::instance($alias);
			if ($plugin && method_exists($plugin, 'enable')) {
				$plugin->enable();
			}
		}
		self::db()->execute("UPDATE {pre}plug SET `status`=1 WHERE `alias`=?", array($alias));
		self::clearBoot();
	}

	/** 停用插件 */
	public static function disable($alias){
		$type = \ZhiCms\base\compat\Compat::detectType($alias);
		if (!$type || $type === 'native') {
			$plugin = self::instance($alias);
			if ($plugin && method_exists($plugin, 'disable')) {
				$plugin->disable();
			}
		}
		self::db()->execute("UPDATE {pre}plug SET `status`=0 WHERE `alias`=?", array($alias));
		self::clearBoot();
	}

	/** 卸载插件：生命周期 uninstall() + 删除注册记录，可选删除文件 */
	public static function uninstall($alias, $deleteFiles = false){
		$type = \ZhiCms\base\compat\Compat::detectType($alias);
		if ($type && $type !== 'native') {
			\ZhiCms\base\compat\Compat::uninstall($alias);
		} else {
			$plugin = self::instance($alias);
			if ($plugin && method_exists($plugin, 'uninstall')) {
				$plugin->uninstall();
			}
		}
		self::db()->execute("DELETE FROM {pre}plug WHERE `alias`=?", array($alias));
		if ($deleteFiles) {
			self::rrmdir(\BASE_PATH . self::PLUGINS_DIR . '/' . $alias);
		}
		self::clearBoot();
	}

	/** 实例化插件入口类 */
	public static function instance($alias, $meta = null){
		$meta = $meta ?: self::readMeta($alias);
		if ($meta === null) return null;
		$class = '\\plugins\\' . $alias . '\\Plugin';
		$file  = \BASE_PATH . self::PLUGINS_DIR . '/' . $alias . '/Plugin.php';
		if (!class_exists($class)) {
			if (!is_file($file)) return null;
			require $file;
		}
		if (!class_exists($class)) return null;
		return new $class($meta);
	}

	/** 读取插件配置（JSON 反序列化） */
	public static function getConfig($alias){
		try {
			$row = self::db()->query("SELECT `config` FROM {pre}plug WHERE `alias` = ?", array($alias));
		} catch (\Throwable $e) {
			return array();
		}
		if (empty($row[0]['config'])) return array();
		$cfg = json_decode($row[0]['config'], true);
		return is_array($cfg) ? $cfg : array();
	}

	/** 保存插件配置 */
	public static function setConfig($alias, $data){
		$json = json_encode($data, JSON_UNESCAPED_UNICODE);
		self::db()->execute("UPDATE {pre}plug SET `config` = ? WHERE `alias` = ?", array($json, $alias));
	}

	/** 插件是否处于启用状态 */
	public static function isEnabled($alias){
		if (!self::tableReady()) return false;
		try {
			$row = self::db()->query("SELECT `status` FROM {pre}plug WHERE `alias` = ? AND `status` = 1", array($alias));
		} catch (\Throwable $e) {
			return false;
		}
		return !empty($row);
	}

	/* ===================== 表结构自检 / 升级 ===================== */

	/** 插件表是否已就绪（存在且含 alias 列） */
	public static function tableReady(){
		try {
			$cols = self::db()->query("SHOW COLUMNS FROM {pre}plug");
		} catch (\Throwable $e) {
			return false;
		}
		foreach ($cols as $c) {
			if (($c['Field'] ?? $c['field'] ?? '') === 'alias') {
				return true;
			}
		}
		return false;
	}

	/** 是否需要升级表结构（旧版 plug 表无 alias 列） */
	public static function needUpgrade(){
		return !self::tableReady();
	}

	/** 执行表结构升级 SQL（data/config/upgrade_plugin.sql） */
	public static function upgradeTable(){
		$file = \CONFIG_PATH . 'upgrade_plugin.sql';
		if (!is_file($file)) {
			throw new \Exception('升级 SQL 文件缺失');
		}
		$dbConf = \ZhiCms\base\Config::get('DB.default');
		$pre = (!empty($dbConf['DB_PREFIX'])) ? $dbConf['DB_PREFIX'] : 'yun_';
		$sql = str_replace('{pre}', $pre, file_get_contents($file));
		foreach (explode(';', $sql) as $s) {
			$s = trim($s);
			if ($s !== '') {
				try { self::db()->execute($s); } catch (\Throwable $e) {}
			}
		}
		self::clearBoot();
	}

	/* ===================== 内部工具 ===================== */

	/** 执行插件 install.sql（若存在） */
	protected static function runInstallSql($alias){
		$file = \BASE_PATH . self::PLUGINS_DIR . '/' . $alias . '/install.sql';
		if (!is_file($file)) return;
		$dbConf = \ZhiCms\base\Config::get('DB.default');
		$pre = (!empty($dbConf['DB_PREFIX'])) ? $dbConf['DB_PREFIX'] : 'yun_';
		$sql = str_replace('{pre}', $pre, file_get_contents($file));
		foreach (explode(';', $sql) as $s) {
			$s = trim($s);
			if ($s !== '') {
				try { self::db()->execute($s); } catch (\Throwable $e) {}
			}
		}
	}

	public static function hasRecord($alias){
		try {
			$row = self::db()->query("SELECT `id` FROM {pre}plug WHERE `alias` = ?", array($alias));
		} catch (\Throwable $e) {
			return false;
		}
		return !empty($row);
	}

	public static function db(){
		return obj('api/ApiData');
	}

	public static function clearBoot(){
		self::$booted = false;
	}

	/** 递归删除目录 */
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
