<?php
namespace plugins\kiees\controller;

use ZhiCms\base\ThinkTemplate;

/**
 * 插件控制器基类
 *
 * 提供与框架 BaseController 类似的 assign() / display() 能力，
 * 但模板目录限定在插件自身的 view/ 目录，避免与系统模板冲突。
 *
 * 读库统一使用 obj('api/ApiData')（app\api\model\ApiDataModel），
 * 其常用方法：
 *   - dataSelect($table, $where, $order)   单条(无order)/列表(有order)
 *   - thisQuery($sql, $params)             原生 SQL（{pre} 占位符自动替换表前缀）
 *   - dataUpdate / insertData / dataCount / page(...)
 */
class KieesController
{
    /** @var array 模板变量 */
    protected $vars = array();

    /** @var string 插件别名（用于静态资源路径等） */
    protected $alias = 'kiees';

    /** @var bool|null 移动端判定结果（null=未计算） */
    protected $isMobile = null;

    /**
     * 移动端访问判断（与主站 bootstrap::_isMobileUA 正则保持一致）
     * 优先级：?pc=1 / ?m=0 强制桌面（同时写 cookie 记忆） > ?m=1 强制移动 > UA > cookie
     * @return bool true=移动端模板
     */
    protected function isMobile()
    {
        if ($this->isMobile !== null) return $this->isMobile;

        $forcePc  = (isset($_GET['pc']) && $_GET['pc'] !== '0') || (isset($_GET['m']) && $_GET['m'] === '0');
        $forceMob = (isset($_GET['m']) && $_GET['m'] === '1');

        if ($forcePc) {
            if (PHP_SAPI !== 'cli') setcookie('kc_pc', '1', time() + 86400 * 30, '/');
            $this->isMobile = false;
            return $this->isMobile;
        }
        if ($forceMob) {
            if (PHP_SAPI !== 'cli') setcookie('kc_pc', '', time() - 3600, '/');
            $this->isMobile = true;
            return $this->isMobile;
        }
        // cookie 记忆：用户曾手动切回桌面版
        if (!empty($_COOKIE['kc_pc'])) {
            $this->isMobile = false;
            return $this->isMobile;
        }

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        $this->isMobile = (bool) preg_match(
            '/ipad|iphone os|ipod|midp|rv:1\.2\.3\.4|ucweb|android|windows ce|windows mobile|blackberry|webos|micromessenger|mobile/i',
            $ua
        );
        return $this->isMobile;
    }

    /**
     * 模板变量赋值（支持数组批量赋值）
     */
    protected function assign($name, $value = null)
    {
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
     * 渲染插件私有模板
     * @param string $tpl   模板文件名（不含后缀，如 'index'）
     * @param array  $merge 额外变量（会并入 $this 的属性一起注入模板）
     */
    protected function display($tpl = 'index', $extra = array())
    {
        $config = \ZhiCms\base\Config::get('TPL');

        // 关键：把视图根目录指向插件自身的 view 目录（绝对路径）
        $viewPath = \BASE_PATH . 'plugins/' . $this->alias . '/view/';

        // 移动端判定仅用于「是否显示右侧栏 / 注入 is_mobile」，
        // 模板统一用 PC 端（已做响应式自适应），不再单独分流 view/m/
        $isM = $this->isMobile();
        $tplReal = $tpl;

        $tplConfig = array_merge((array) $config, array(
            'TPL_PATH'    => $viewPath,
            'view_path'   => $viewPath,   // ThinkTemplate 内部用 view_path 解析 {include}
            'TPL_SUFFIX' => '.html',
            'view_suffix' => 'html',
            // 模板编译缓存固定写到站点根 data/cache/tpl_compile，
            // 避免被 TPL_PATH（插件 view 目录）带偏到 plugins/kiees/data/cache 导致不可写报错
            'cache_path'  => rtrim(BASE_PATH, '\\/') . '/data/cache/tpl_compile',
        ));

        $engine = new ThinkTemplate($tplConfig);

        // 注入 $this 的公开属性（与框架 BaseController::display 行为一致）
        $objVars = get_object_vars($this);
        foreach ($objVars as $k => $v) {
            if ($k === 'vars') continue;
            $engine->assign($k, $v);
        }
        // 注入显式 assign 的变量（优先级更高）
        foreach ($this->vars as $k => $v) {
            $engine->assign($k, $v);
        }
        foreach ((array) $extra as $k => $v) {
            $engine->assign($k, $v);
        }

        // 常用站点信息（直接取自本站配置，替换原 kiees 演示文案）
        $base = obj('base/Base');
        $siteName  = $base->SiteConfig('sitename') ?: 'ZhiCms';
        $logo      = $base->SiteConfig('logo');
        $copyright = $base->SiteConfig('banquan');   // 版权声明
        $beian     = $base->SiteConfig('beian');     // 备案号
        $hostUrl   = $base->SiteConfig('hosturl');
        $appIphone = $base->SiteConfig('app_iphone');
        $appAndroid= $base->SiteConfig('app_android');

        // 插件自有模块入口（优惠券/大牌/风云榜，全部用插件模板渲染，数据复用主站）
        $navCheaps = $this->modUrl('cheaps');
        $navBrand  = $this->modUrl('brand');
        $navRank   = $this->modUrl('rank');

        $engine->assign('site_name', $siteName);
        $engine->assign('site_logo', $logo);          // 空时模板回退为站名文字
        $engine->assign('copyright', $copyright ?: ('© ' . date('Y') . ' ' . $siteName . ' 版权所有'));
        $engine->assign('beian', $beian);
        $engine->assign('host_url', $hostUrl);
        $engine->assign('app_iphone', $appIphone);
        $engine->assign('app_android', $appAndroid);
        $engine->assign('nav_cheaps', $navCheaps);
        $engine->assign('nav_brand', $navBrand);
        $engine->assign('nav_rank', $navRank);

        // 公共右栏热门榜数据（所有页面复用 sidebar.html 都需要）
        try {
            $hot = obj('api/ApiData')->dataSelect('yun_article', array("`status` = 1"), '`view` DESC LIMIT 0, 10');
        } catch (\Throwable $e) {
            $hot = array();
        }
        $engine->assign('hot', $hot ?: array());

        // 主站静态资源根路径（复用主站 blog.css / common.css 实现风格兼容）
        $public = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '\\/') . '/public/';
        $engine->assign('public', $public);
        $engine->assign('plug_static', '/plugins/' . $this->alias . '/static');
        $engine->assign('plug_url', \ZhiCms\base\PluginManager::url($this->alias));
        // 不含 .html 后缀的插件基链接，供模板统一拼 “-id.html” 详情页（避免 plug_url 自带 .html 变成 .html-1.html 畸形）
        $engine->assign('plug_base', 'plug-' . $this->alias);
        $engine->assign('is_mobile', $isM ? 1 : 0);
        // 是否显示右侧栏：移动端不显示（模板用 {if $show_sidebar} 包裹侧栏）
        $engine->assign('show_sidebar', $isM ? 0 : 1);
        // 移动端切回桌面版链接（带上 ?pc=1 并记忆 cookie）
        $engine->assign('pc_url', $this->currentUrlWith('pc=1'));

        // ===== SEO 通用变量（仿主站 public/header.html 做法）=====
        $engine->assign('canonical', $this->currentUrlWith(''));               // 规范链接：当前 URL 去 m/pc 参数
        $engine->assign('page_keywords', $this->vars['page_keywords'] ?? ($siteName . ',优惠券,好物推荐,网购省钱'));
        $engine->assign('page_description', $this->vars['page_description'] ?? ($siteName . ' - 精选高性价比优惠券与好物推荐，领券更省。'));
        $engine->assign('og_image', $this->vars['og_image'] ?? '');
        $engine->assign('og_type', ($tpl === 'detail') ? 'article' : 'website');
        $engine->assign('seo_title', $this->vars['page_title'] ?? $siteName);

        $engine->display($tplReal);
        exit;
    }

    /**
     * 生成当前 URL 并追加/覆盖查询参数（用于移动端「切回桌面版」）
     * @param string $append 形如 "pc=1" 的查询串
     */
    protected function currentUrlWith($append)
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $parts = parse_url($uri);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? $parts['query'] : '';
        parse_str($query, $q);
        parse_str($append, $a);
        $q = array_merge($q, $a);
        // 去掉与移动端判断冲突的参数
        unset($q['m']);
        $qstr = http_build_query($q);
        return $path . ($qstr !== '' ? ('?' . $qstr) : '');
    }

    /**
     * 生成插件模块链接（cheaps/brand/rank），兼容伪静态与动态
     */
    protected function modUrl($mod)
    {
        $base = \ZhiCms\base\PluginManager::url($this->alias);
        if (strpos($base, '?') !== false) {
            // 动态模式：index.php?r=...&mod=cheaps
            $sep = (strpos($base, '?') !== false) ? '&' : '?';
            return $base . $sep . 'mod=' . $mod;
        }
        // 伪静态：plug-kiees-cheaps.html（base 可能已含 .html 后缀，需先去掉）
        $base = preg_replace('/\.html$/i', '', $base);
        return rtrim($base, '/') . '-' . $mod . '.html';
    }

    /**
     * 读取 GET/POST 参数（兼容框架 Controller::arg，去标签防 XSS）
     */
    protected function arg($name = null, $default = null)
    {
        static $args;
        if (!$args) {
            $args = array_merge((array) $_GET, (array) $_POST);
        }
        if ($name === null) return $args;
        if (!isset($args[$name])) return $default;
        $v = $args[$name];
        if (is_array($v)) {
            array_walk($v, function (&$x) { $x = trim(htmlspecialchars($x, ENT_QUOTES, 'UTF-8')); });
            return $v;
        }
        return trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * 读取数据库（封装 obj('api/ApiData')）
     * @return \app\api\model\ApiDataModel
     */
    protected function db()
    {
        return obj('api/ApiData');
    }
}
