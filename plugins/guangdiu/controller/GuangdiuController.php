<?php
namespace plugins\guangdiu\controller;

use ZhiCms\base\ThinkTemplate;

/**
 * 插件控制器基类（照搬 kiees 的 KieesController，改名 GuangdiuController）
 * 提供 assign() / display() / modUrl() / plugUrl() 等能力，模板目录限定在插件自身 view/。
 * 业务方法（index/detail/cheaps/brand/rank/search/parseZhiCmsUrl 等）全部在 SiteController 内，与 kiees 一致。
 */
class GuangdiuController
{
    protected $vars = array();
    protected $alias = 'guangdiu';
    protected $isMobile = null;

    protected function isMobile()
    {
        if ($this->isMobile !== null) return $this->isMobile;

        $forcePc  = (isset($_GET['pc']) && $_GET['pc'] !== '0') || (isset($_GET['m']) && $_GET['m'] === '0');
        $forceMob = (isset($_GET['m']) && $_GET['m'] === '1');

        if ($forcePc) {
            if (PHP_SAPI !== 'cli') setcookie('gd_pc', '1', time() + 86400 * 30, '/');
            $this->isMobile = false;
            return $this->isMobile;
        }
        if ($forceMob) {
            if (PHP_SAPI !== 'cli') setcookie('gd_pc', '', time() - 3600, '/');
            $this->isMobile = true;
            return $this->isMobile;
        }
        if (!empty($_COOKIE['gd_pc'])) {
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

    protected function assign($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) { $this->vars[$k] = $v; }
        } else {
            $this->vars[$name] = $value;
        }
        return $this;
    }

    protected function display($tpl = 'index', $extra = array())
    {
        $config = \ZhiCms\base\Config::get('TPL');
        $viewPath = \BASE_PATH . 'plugins/' . $this->alias . '/view/';

        $isM = $this->isMobile();

        $tplConfig = array_merge((array) $config, array(
            'TPL_PATH'     => $viewPath,
            'view_path'    => $viewPath,
            'TPL_SUFFIX'  => '.html',
            'view_suffix' => 'html',
        ));

        $engine = new ThinkTemplate($tplConfig);

        $objVars = get_object_vars($this);
        foreach ($objVars as $k => $v) {
            if ($k === 'vars') continue;
            $engine->assign($k, $v);
        }
        foreach ($this->vars as $k => $v) {
            $engine->assign($k, $v);
        }
        foreach ((array) $extra as $k => $v) {
            $engine->assign($k, $v);
        }

        $base = obj('base/Base');
        $siteName  = $base->SiteConfig('sitename') ?: 'ZhiCms';
        $logo      = $base->SiteConfig('logo');
        $copyright = $base->SiteConfig('banquan');
        $beian     = $base->SiteConfig('beian');
        $hostUrl   = $base->SiteConfig('hosturl');
        $appIphone = $base->SiteConfig('app_iphone');
        $appAndroid= $base->SiteConfig('app_android');

        $engine->assign('site_name', $siteName);
        $engine->assign('site_logo', $logo);
        $engine->assign('copyright', $copyright ?: ('© ' . date('Y') . ' ' . $siteName . ' 版权所有'));
        $engine->assign('beian', $beian);
        $engine->assign('host_url', $hostUrl);
        $engine->assign('app_iphone', $appIphone);
        $engine->assign('app_android', $appAndroid);
        $engine->assign('nav_index', $this->modUrl(''));
        $engine->assign('nav_cheaps', $this->modUrl('cheaps'));
        $engine->assign('nav_brand', $this->modUrl('brand'));
        $engine->assign('nav_rank', $this->modUrl('rank'));

        try {
            $hot = obj('api/ApiData')->dataSelect('yun_article', array("`status` = 1"), '`view` DESC LIMIT 0, 10');
        } catch (\Throwable $e) {
            $hot = array();
        }
        $engine->assign('hot', $hot ?: array());

        $public = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '\\/') . '/public/';
        $engine->assign('public', $public);
        $engine->assign('plug_static', '/plugins/' . $this->alias . '/static');
        $engine->assign('plug_url', \ZhiCms\base\PluginManager::url($this->alias));
        $engine->assign('plug_base', 'plug-' . $this->alias);
        $engine->assign('is_mobile', $isM ? 1 : 0);
        $engine->assign('show_sidebar', $isM ? 0 : 1);
        $engine->assign('pc_url', $this->currentUrlWith('pc=1'));

        // 用户注册/登录入口开关（后台互动设置 user_show_login，存 yun_config 表）
        try {
            $switchRow = obj('api/ApiData')->thisQuery(
                "SELECT `value` FROM `{pre}config` WHERE `key` = ? LIMIT 1",
                array('user_show_login')
            );
            $showUserEntry = (!empty($switchRow[0]['value']) && $switchRow[0]['value'] !== '0');
        } catch (\Throwable $e) {
            $showUserEntry = true;
        }
        $engine->assign('showUserEntry', $showUserEntry);
        // 注入当前登录用户（供入口显示登录态），与主站 BaseController 识别方式一致
        $loginUser = null;
        if (!empty($_COOKIE['ZhiCmsUser'])) {
            $safeUid = str_replace(['%', '_', '\\'], ['\%', '\_', '\\\\'], $_COOKIE['ZhiCmsUser']);
            try {
                $loginUser = obj('api/ApiData')->dataSelect('yun_user', array("`mobile` LIKE '{$safeUid}'"));
            } catch (\Throwable $e) {
                $loginUser = null;
            }
        }
        $engine->assign('loginUser', $loginUser);

        // ===== SEO 统一（与主站 public/header.html 规则一致）=====
        // 彻底去除 SiteController 硬编码文案：页面只传「语义片段」（seo_prefix/seo_kw），
        // 此处统一组装为「后台 SEO 配置 -> 站点配置 -> 页面语义 + 站点名兜底」。
        // 站点名/站点关键词/站点描述均来自后台网站设置，确保插件页真正引用网站配置。
        $seoIndexTitle   = $base->SEO('index_title');
        $seoIndexKw      = $base->SEO('index_keywords');
        $seoIndexDec     = $base->SEO('index_dec');
        $siteKeywords    = $base->SiteConfig('sitekeywords');
        $siteDescription = $base->SiteConfig('sitedescription');

        // 页面语义片段（由 SiteController 传入，不再写死整段文案）
        $seoPrefix = $this->vars['seo_prefix'] ?? '';   // 标题前缀：文章标题 / 搜索词 / 模块名
        $seoKw     = $this->vars['seo_kw'] ?? '';       // 额外关键词：搜索词等

        // canonical：去除分页/排序参数，避免 ?page=2 等自成规范链接稀释权重
        $canonical = $this->currentUrlWithout(array('page', 'sort', 'order'));
        $engine->assign('canonical', $canonical);
        // 搜索页 / 分页页：自动 noindex（仍 follow），与主站 SearchController 行为对齐
        $isPaginated = ($this->arg('page', 1) > 1);
        $pageRobots  = ($this->vars['is_search'] ?? false) || $isPaginated ? 'noindex,follow' : 'index,follow';
        $engine->assign('page_robots', $pageRobots);

        // 标题：后台SEO配置 -> 页面语义前缀 + 站点名 -> 仅站点名
        $title = $seoIndexTitle ?: ($seoPrefix !== '' ? ($seoPrefix . ' - ' . $siteName) : $siteName);
        $engine->assign('seo_title', $title);

        // 关键词：后台SEO配置 -> 站点配置 -> 页面语义关键词 + 站点名 -> 仅站点名
        $kw = $seoIndexKw ?: ($siteKeywords ?: ($seoKw !== '' ? ($seoKw . ',' . $siteName) : $siteName));
        $engine->assign('page_keywords', $kw);

        // 描述：后台SEO配置 -> 站点配置 -> 兜底（语义前缀 + 站点名）
        $desc = $seoIndexDec ?: ($siteDescription ?: ($seoPrefix !== '' ? ($seoPrefix . ' - ' . $siteName) : $siteName . ' - 精选高性价比优惠券与好物推荐，领券更省。'));
        $engine->assign('page_description', $desc);

        $engine->assign('og_image', $this->vars['og_image'] ?? '');
        $engine->assign('og_type', ($tpl === 'detail') ? 'article' : 'website');
        $engine->assign('is_search', $this->vars['is_search'] ?? false);

        // 详情页结构化数据（Article JSON-LD，与主站 ViewController 对齐）
        $articleJsonLd = '';
        if ($tpl === 'detail' && !empty($this->vars['article'])) {
            $art = $this->vars['article'];
            $artTitle   = isset($art['title']) ? strip_tags($art['title']) : $siteName;
            $artImg     = isset($art['mainPic']) ? $art['mainPic'] : (isset($art['pic']) ? $art['pic'] : '');
            $artDate    = isset($art['date']) ? date('c', strtotime($art['date'])) : date('c');
            $artDesc    = isset($art['dec']) ? mb_substr(strip_tags($art['dec']), 0, 200, 'UTF-8') : $siteDescription;
            $ld = array(
                '@context' => 'https://schema.org',
                '@type'    => 'Article',
                'headline' => $artTitle,
                'image'    => array($artImg),
                'datePublished' => $artDate,
                'dateModified'  => $artDate,
                'author'   => array('@type' => 'Organization', 'name' => $siteName),
                'publisher'=> array('@type' => 'Organization', 'name' => $siteName,
                                    'logo' => array('@type' => 'ImageObject', 'url' => $logo)),
                'description' => $artDesc,
                'mainEntityOfPage' => array('@type' => 'WebPage', '@id' => $this->currentUrlWith('')),
            );
            $articleJsonLd = json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $engine->assign('articleJsonLd', $articleJsonLd);

        $engine->display($tpl);
        exit;
    }

    protected function currentUrlWith($append)
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $parts = parse_url($uri);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? $parts['query'] : '';
        parse_str($query, $q);
        parse_str($append, $a);
        $q = array_merge($q, $a);
        unset($q['m']);
        $qstr = http_build_query($q);
        return $path . ($qstr !== '' ? ('?' . $qstr) : '');
    }

    protected function currentUrlWithout($drop = array())
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $parts = parse_url($uri);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? $parts['query'] : '';
        parse_str($query, $q);
        foreach ((array) $drop as $k) { unset($q[$k]); }
        unset($q['m']);
        $qstr = http_build_query($q);
        return $path . ($qstr !== '' ? ('?' . $qstr) : '');
    }

    protected function modUrl($mod)
    {
        $base = \ZhiCms\base\PluginManager::url($this->alias);
        if (strpos($base, '?') !== false) {
            $sep = (strpos($base, '?') !== false) ? '&' : '?';
            return $base . $sep . 'mod=' . $mod;
        }
        $base = preg_replace('/\.html$/i', '', $base);
        return rtrim($base, '/') . ($mod !== '' ? ('-' . $mod . '.html') : '.html');
    }

    protected function arg($name = null, $default = null)
    {
        static $args;
        if (!$args) { $args = array_merge((array) $_GET, (array) $_POST); }
        if ($name === null) return $args;
        if (!isset($args[$name])) return $default;
        $v = $args[$name];
        if (is_array($v)) {
            array_walk($v, function (&$x) { $x = trim(htmlspecialchars($x, ENT_QUOTES, 'UTF-8')); });
            return $v;
        }
        return trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
    }

    protected function db()
    {
        return obj('api/ApiData');
    }
}
