<?php
namespace ZhiCms\base;

use think\Template as ThinkTpl;

/**
 * 基于 think-template（真·ThinkPHP 模板引擎）的视图封装
 *
 * 与旧版 ZhiCms\base\Template 保持一致的对外接口：
 *   assign($name, $value)
 *   display($tpl = '', $return = false, $isTpl = true)
 *
 * 关键兼容点：
 *   - default_filter 设为 tpl_raw()，保持与旧引擎一致的原样输出（不转义）
 *   - tpl_var_identify = 'array'，使 {$user.name} 解析为 $user['name']
 *   - 原生支持 {foreach}/{if}/{elseif}/{else}/{for}/{include file=...}
 *   - 函数/常量输出请用原生语法 {:func()} / {:CONST}
 *
 * @package ZhiCms\base
 */
class ThinkTemplate {

	protected $config = array();
	protected $vars   = array();
	protected $engine = null;

	public function __construct($config) {
		$this->config = $config;

		$tplPath = rtrim($config['TPL_PATH'] ?? '', '/\\') . DIRECTORY_SEPARATOR;
		$suffix  = ltrim($config['TPL_SUFFIX'] ?? '.html', '.');

		$tplConfig = array(
			'view_path'         => $tplPath,
			'view_suffix'       => $suffix,
			'cache_path'        => $this->compilePath(),
			'cache_suffix'      => 'php',
			'tpl_deny_php'      => false,            // 允许原生 PHP
			'default_filter'    => 'tpl_raw',         // 原样输出，与旧引擎一致
			'tpl_var_identify'  => 'array',           // . 语法按数组解析
			'tpl_begin'         => '{',
			'tpl_end'           => '}',
			'taglib_begin'      => '{',
			'taglib_end'        => '}',
			'layout_on'         => false,
			'strip_space'       => false,
			'tpl_cache'         => true,
		);

		$this->engine = new ThinkTpl($tplConfig);

		// 兼容极少数旧模板里 {$__Template->display(...)} 的写法
		$this->assign('__Template', $this);
	}

	/**
	 * 编译缓存目录（可写）
	 * 统一落在站点根 runtime/cache/tpl_compile，与框架级缓存同目录便于统一清理。
	 * 不依赖 TPL_PATH（模板源目录）：旧逻辑用 TPL_PATH 拼接，当插件把 TPL_PATH 设为
	 * plugins/xxx/view/ 时，编译目录会被错误推到 plugins/xxx/view/data/cache 导致
	 * 不可写（cache write error），且散落各处难以清理。
	 */
	protected function compilePath() {
		$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/\\') : rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../..', '/\\');
		$dir = $base . DIRECTORY_SEPARATOR
			. 'runtime' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'tpl_compile' . DIRECTORY_SEPARATOR;
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		return $dir;
	}

	/**
	 * 模板变量赋值
	 */
	public function assign($name, $value = '') {
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				$this->vars[$k] = $v;
			}
		} else {
			$this->vars[$name] = $value;
		}
		return $this;
	}

	/**
	 * 渲染模板
	 * @param string $tpl    模板名（isTpl=true）或模板内容（isTpl=false）
	 * @param bool   $return 是否返回字符串而非直接输出
	 * @param bool   $isTpl  是否为模板文件（false 时 $tpl 为模板字符串）
	 */
	public function display($tpl = '', $return = false, $isTpl = true) {
		if ($return) {
			if (ob_get_level()) {
				ob_end_flush();
				flush();
			}
			ob_start();
		}

		if ($isTpl) {
			$this->engine->fetch($tpl, $this->vars);
		} else {
			$this->engine->display($tpl, $this->vars);
		}

		if ($return) {
			$content = ob_get_clean();
			return $content;
		}
	}
}
