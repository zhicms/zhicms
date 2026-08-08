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
		// 合并已启用插件的伪静态规则到框架 REWRITE_RULE（内存态，不写 rule.php），
		// 使插件展示页既能动态访问（index.php?r=...）也能伪静态访问（plug-xxx.html）。
		$pluginRules = array();
		foreach ((array)$rows as $row) {
			$meta = self::readMeta($row['alias']);
			if ($meta && !empty($meta['rewrite']) && is_array($meta['rewrite'])) {
				foreach ($meta['rewrite'] as $rule => $mapper) {
					if (!isset($pluginRules[$rule])) $pluginRules[$rule] = $mapper;
				}
			}
		}
		if ($pluginRules) {
			// 规则排序：占位符越少（即字面量越具体）越靠前，保证
			// 「plug-<alias>-cheaps.html」优先于通配的「plug-<alias>-<id>.html」匹配。
			// 否则多插件共存时，先合并的插件的 <id> 通配规则会把其它插件的具名子页
			// （cheaps/brand/rank）吞成 id=cheaps，导致子页统一回退首页。
			$ruleKeys = array_keys($pluginRules);
			usort($ruleKeys, function ($a, $b) {
				$ca = preg_match_all('/<[a-zA-Z0-9_]+>/', $a);
				$cb = preg_match_all('/<[a-zA-Z0-9_]+>/', $b);
				if ($ca !== $cb) return $ca - $cb;            // 占位符少的优先
				return strlen($b) - strlen($a);               // 同数量时更长（更具体）的优先
			});
			$sorted = array();
			foreach ($ruleKeys as $k) { $sorted[$k] = $pluginRules[$k]; }
			$pluginRules = $sorted;

			$cur = \ZhiCms\base\Config::get('REWRITE_RULE');
			$cur = is_array($cur) ? $cur : array();
			// 插件规则追加在框架规则之后，避免覆盖系统自带伪静态
			\ZhiCms\base\Config::set('REWRITE_RULE', array_merge($cur, $pluginRules));
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
			// 插件显式声明的菜单（menu）
			if (!empty($meta['menu']) && is_array($meta['menu'])) {
				foreach ($meta['menu'] as $m) {
					$menus[] = array(
						'title' => $m['title'] ?? ($meta['name'] ?? $alias),
						'url'   => $m['url']   ?? ('index.php?r=manage/plugin/setting&alias=' . $alias),
					);
				}
			}
			// 插件有设置项（hasSetting）但未声明 menu 时，自动追加「设置」入口，方便用户配置
			if (!empty($meta['hasSetting']) && empty($meta['menu'])) {
				$menus[] = array(
					'title' => ($meta['name'] ?? $alias) . '设置',
					'url'   => 'index.php?r=manage/plugin/setting&alias=' . $alias,
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

	/**
	 * 是否为「模板化插件」（可在后台被设为主页展示）。
	 * 判定依据：plugin.json 显式声明 type=template，且提供了展示页入口（displayPage）+ 首页伪静态规则（plug-<alias>.html）。
	 * 仅模板类插件可作为主页，普通功能插件一律排除。
	 */
	public static function isTemplate($alias){
		$meta = self::readMeta($alias);
		if ($meta === null) return false;
		if (($meta['type'] ?? '') !== 'template') return false;
		// 兜底：插件入口类必须实现 displayPage，且 plugin.json 配置了 plug-<alias>.html 首页规则
		if (empty($meta['rewrite']) || !isset($meta['rewrite']['plug-<alias>.html'])) return false;
		$plugin = self::instance($alias, $meta);
		return ($plugin !== null && method_exists($plugin, 'displayPage'));
	}

	/**
	 * 获取所有可被设为主页的「模板化插件」列表（仅已启用）。
	 * @return array [ ['alias'=>..., 'name'=>...], ... ]
	 */
	public static function getTemplatePlugins(){
		$list = array();
		if (!self::tableReady()) return $list;
		try {
			$rows = self::db()->query("SELECT `alias` FROM {pre}plug WHERE `status` = 1");
		} catch (\Throwable $e) {
			return $list;
		}
		foreach ($rows as $row) {
			$alias = $row['alias'];
			if (!self::isTemplate($alias)) continue;
			$meta = self::readMeta($alias);
			$list[] = array(
				'alias' => $alias,
				'name'  => ($meta['name'] ?? $alias),
			);
		}
		return $list;
	}

	/**
	 * 生成插件展示页链接：伪静态开启且有对应 rewrite 规则则返回伪静态 URL，
	 * 否则回退到动态地址 index.php?r=index/plug/view/alias=<alias>/...
	 * @param string $alias  插件别名
	 * @param array  $params 附加参数（键值对，会替换 rewrite 规则中的 <key> 占位符）
	 * @return string
	 */
	public static function url($alias, $params = array()){
		$meta = self::readMeta($alias);
		$rewrite = ($meta && !empty($meta['rewrite'])) ? $meta['rewrite'] : array();

		// 动态地址（始终可用）
		$dyn = 'index.php?r=index/plug/view/alias=' . $alias;
		if ($params) {
			foreach ($params as $k => $v) {
				$dyn .= '&' . $k . '=' . urlencode($v);
			}
		}

		// 伪静态未开启或无规则 → 动态
		if (!\ZhiCms\base\Config::get('REWRITE_ON') || empty($rewrite)) {
			return $dyn;
		}

		// 仅传 alias（无额外参数）时，直接返回插件首页伪静态地址，
		// 避免「优先占位符最多」的匹配逻辑在多条单占位符规则间产生歧义
		//（例如 plug-<alias>-cheaps.html 排在前面时被误选为首页链接）。
		if (empty($params)) {
			$homeKey   = 'plug-<alias>.html';   // plugin.json 中的占位符形式
			$homePlain = 'plug-' . $alias . '.html'; // 合并进 REWRITE_RULE 后的字面量形式
			if (isset($rewrite[$homeKey]) || isset($rewrite[$homePlain])) {
				return $homePlain;
			}
		}

		// alias 是方法的独立参数，但 rewrite 规则中通常以 <alias> 占位符出现，
		// 需把它纳入“可用参数集合”用于规则匹配与占位符替换。
		$avail = $params;
		$avail['alias'] = $alias;

		// 选择一个能容纳所有 params 的规则：优先占位符最多的匹配
		$best = null; $bestScore = -1;
		foreach ($rewrite as $rule => $mapper) {
			preg_match_all('/<([a-zA-Z0-9_]+)>/', $rule, $m);
			$holders = $m[1];
			$ok = true;
			foreach ($holders as $h) {
				if (!array_key_exists($h, $avail)) { $ok = false; break; }
			}
			if (!$ok) continue;
			$score = count($holders);
			if ($score > $bestScore) { $best = $rule; $bestScore = $score; }
		}
		if ($best === null) return $dyn;

		$url = $best;
		$left = array();
		foreach ($params as $k => $v) {
			$count = 0;
			$url = str_ireplace('<' . $k . '>', $v, $url, $count);
			if (!$count) $left[$k] = $v;
		}
		// 替换规则中剩余的 <alias> 占位符（alias 来自方法参数，不进入 query string）
		$url = str_ireplace('<alias>', $alias, $url);
		$url = preg_replace('/<\w+>/', '', $url);
		if ($left) $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($left);
		// 去掉入口脚本前缀（rule.php 里的规则均不含 index.php，因此生成的静态 URL 也需一致）
		$url = ltrim($url, './\\');
		return $url;
	}

	/**
	 * 从插件规则生成到控制器路由的映射（供 routeParseUrl 钩子展示用）
	 * @return array
	 */
	public static function getPluginRewriteRules(){
		$rules = array();
		if (!self::tableReady()) return $rules;
		try {
			$rows = self::db()->query("SELECT `alias` FROM {pre}plug WHERE `status` = 1");
		} catch (\Throwable $e) {
			return $rules;
		}
		foreach ($rows as $row) {
			$meta = self::readMeta($row['alias']);
			if ($meta && !empty($meta['rewrite']) && is_array($meta['rewrite'])) {
				foreach ($meta['rewrite'] as $rule => $mapper) {
					$rules[$rule] = $mapper;
				}
			}
		}
		return $rules;
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
