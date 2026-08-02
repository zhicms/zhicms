<?php
namespace ZhiCms\base\compat;

/**
 * Z-BlogPHP 插件桥接器
 * - 把 zblog 的 Filter_Plugin_* 接口映射为 ZhiCms 原生钩子
 * - 加载 zblog 格式插件（plugin.xml + include.php）并触发生命周期
 * - ⚠️ 安全机制：加载时通过常量预定义防止 exit('Access denied') + 输出缓冲 + 关闭函数隔离
 */
class ZblogBridge
{
    /** Filter_Plugin_* 接口 => ZhiCms 原生钩子 */
    public static $map = array(
        'Filter_Plugin_Admin_Header'               => 'admin_head',
        'Filter_Plugin_Admin_Menu'                 => 'admin_menu',
        'Filter_Plugin_Admin_Footer'               => 'admin_footer',
        'Filter_Plugin_Admin_Index'                => 'admin_dashboard',
        'Filter_Plugin_Admin_CommentMng_SubMenu'   => 'admin_menu',
        'Filter_Plugin_Admin_CategoryMng_SubMenu'  => 'admin_menu',
        'Filter_Plugin_Admin_PageMng_SubMenu'      => 'admin_menu',
        'Filter_Plugin_Admin_ModuleMng_SubMenu'    => 'admin_menu',
        'Filter_Plugin_Admin_UserMng_SubMenu'      => 'admin_menu',
        'Filter_Plugin_Index_Header'               => 'index_head',
        'Filter_Plugin_Index_Footer'               => 'index_footer',
        'Filter_Plugin_View_Post'                  => 'article_view',
        'Filter_Plugin_View_Article'               => 'article_view',
        'Filter_Plugin_PostComment_Core'           => 'comment_post',
        'Filter_Plugin_PostComment_Save'           => 'comment_saved',
        'Filter_Plugin_PostComment_Comment'         => 'comment_post',
        'Filter_Plugin_Cmd_Begin'                  => 'appBegin',
        'Filter_Plugin_Cmd_Ajax'                   => 'cmd_ajax',
        'Filter_Plugin_PostArticle_Succeed'        => 'article_save',
        'Filter_Plugin_DelArticle_Succeed'         => 'article_delete',
        'Filter_Plugin_PostPage_Succeed'           => 'page_save',
        'Filter_Plugin_PostCategory_Succeed'       => 'category_save',
        'Filter_Plugin_PostTag_Succeed'            => 'tag_save',
        'Filter_Plugin_Zbp_BuildTemplate'          => 'template_build',
        'Filter_Plugin_Upload_SaveFile'            => 'upload',
        'Filter_Plugin_Upload_Url'                 => 'upload',
    );

    public static function mapInterface($if)
    {
        return self::$map[$if] ?? $if;
    }

    /** 安全加载：预置常量 → 输出缓冲 → require（防止 exit/die 中断） */
    public static function load($alias, $dir)
    {
        // 预定义所有 Z-Blog 防护常量（必须在 require 插件文件之前）
        self::predefineConstants();

        require_once \BASE_PATH . 'ZhiCms/base/compat/zblog_api.php';

        $inc = $dir . '/include.php';
        if (is_file($inc)) {
            self::safeRequire($inc, $alias);
        }
    }

    public static function install($alias, $dir)
    {
        self::load($alias, $dir);
        $fn = 'InstallPlugin_' . $alias;
        if (function_exists($fn)) {
            try { call_user_func($fn); } catch (\Throwable $e) {}
        }
    }

    public static function uninstall($alias, $dir)
    {
        self::load($alias, $dir);
        $fn = 'UninstallPlugin_' . $alias;
        if (function_exists($fn)) {
            try { call_user_func($fn); } catch (\Throwable $e) {}
        }
    }

    /**
     * 在加载任何插件文件前预置必需常量（三层防护中的第二层）
     */
    protected static function predefineConstants()
    {
        // Z-BlogPHP 核心常量
        if (!defined('ZBP_PATH'))       define('ZBP_PATH', \BASE_PATH);
        if (!defined('ZBP_HOOKERROR'))  define('ZBP_HOOKERROR', true);
        if (!defined('ZBP_SAFEMODE'))   define('ZBP_SAFEMODE', false);
        if (!defined('ZBP_VERSION'))    define('ZBP_VERSION', '1.7');
        if (!defined('ZBP_PLUGIN_DIR')) define('ZBP_PLUGIN_DIR', \BASE_PATH . 'zb_users/plugin/');
        // 兜底：其他平台的常量也预置，防止因 detectType 误判导致 exit
        if (!defined('ABSPATH'))        define('ABSPATH', \BASE_PATH);
        if (!defined('EMLOG_ROOT'))     define('EMLOG_ROOT', \BASE_PATH);
    }

    /**
     * 安全 require：用输出缓冲包裹，记录任何意外输出但防止中断
     * 注意：exit/die 仍会导致脚本终止——这是 PHP 的限制；
     * 但通过预置常量（done in predefineConstants），常见 exit('Access denied') 已不会触发。
     */
    protected static function safeRequire($file, $alias = '')
    {
        try {
            ob_start();
            require_once $file;
            $output = ob_get_clean();
            if ($output !== '' && trim($output) !== '') {
                // 记录意外输出，不中断流程
                error_log("[ZblogBridge] Plugin '$alias' produced unexpected output during load: " . substr($output, 0, 500));
            }
        } catch (\Throwable $e) {
            @ob_end_clean();
            error_log("[ZblogBridge] Plugin '$alias' error during load: " . $e->getMessage());
        }
    }

    /**
     * 渲染 Z-Blog 兼容插件的后台设置页
     * 为 Z-Blog 插件创建最小化的 admin 桩文件环境，捕获管理页输出
     * @return string HTML 片段
     */
    public static function renderAdmin($alias)
    {
        $dir = \BASE_PATH . 'plugins/' . $alias;
        if (!is_dir($dir)) {
            return '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>插件目录不存在</div>';
        }

        // 确定管理页入口文件（优先 plugin.xml 的 <path>，其次 main.php）
        $adminFile = '';
        $xmlFile = $dir . '/plugin.xml';
        if (is_file($xmlFile)) {
            $xml = @simplexml_load_file($xmlFile);
            if ($xml) {
                $p = (string)($xml->path ?? '');
                if ($p !== '' && is_file($dir . '/' . $p)) {
                    $adminFile = $dir . '/' . $p;
                }
            }
        }
        if (!$adminFile && is_file($dir . '/main.php')) {
            $adminFile = $dir . '/main.php';
        }
        if (!$adminFile) {
            return '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2"></i>该插件没有后台管理页面</div>';
        }

        // 1. 创建 Z-Blog admin 桩文件（两套路径都补齐）
        //    插件内部的 require '../../../zb_system/...' 从 plugins/{alias}/ 解析到
        //    parent(\BASE_PATH)/zb_system/，而 $zbp->systemdir 指向 \ZBP_SYSTEM_DIR
        //    （已迁移至 ZhiCms/base/compat/zb_system/）。
        //    通过 scanAdminRequires 从实际 admin 文件内容中精确找出引用路径并动态补桩。
        self::ensureAdminStubs($adminFile);

        // 检查并确保桩文件在 main.php require 的相对路径上也存在
        // （确保即使 ensureAdminStubs 写入失败也能在 require 前补救）
        self::ensureStubsForFile($adminFile);

        // 2. 预置环境
        self::predefineConstants();
        if (!class_exists('\\ZhiCms\\base\\compat\\ZbpShim')) {
            require_once \BASE_PATH . 'ZhiCms/base/compat/ZbpShim.php';
        }
        global $zbp;
        if (!isset($zbp) || !($zbp instanceof \ZhiCms\base\compat\ZbpShim)) {
            $zbp = new \ZhiCms\base\compat\ZbpShim();
        }
        require_once \BASE_PATH . 'ZhiCms/base/compat/zblog_api.php';

        // 加载插件的 include.php（注册函数和类）
        $inc = $dir . '/include.php';
        if (is_file($inc)) {
            self::safeRequire($inc, $alias);
        }

        // 3. 捕获管理页输出
        //    注意：fatal error（如 require 找不到文件）无法被 try/catch 捕获，
        //    因此依赖步骤 1 的桩文件补齐来防止 fatal error。
        try {
            ob_start();
            global $blogpath, $blogtitle, $lang;
            if (!isset($blogpath)) $blogpath = \BASE_PATH;
            if (!isset($blogtitle)) $blogtitle = '';
            if (!isset($lang) || !is_array($lang)) $lang = array('msg' => array('submit' => '提交'));

            require $adminFile;
            $content = ob_get_clean();
        } catch (\Throwable $e) {
            @ob_end_clean();
            $errFile = str_replace(\BASE_PATH, '', $e->getFile());
            $errMsg = $e->getMessage();
            // 如果是类/函数未定义，给出更有意义的提示
            if (preg_match('/Class\s+[\'"]?(\S+)[\'"]?\s+not found/', $errMsg, $cm)) {
                $errMsg = "缺少类: {$cm[1]}（插件依赖的 Z-Blog 类未被兼容层模拟）";
            }
            return '<div class="alert alert-danger"><i class="fas fa-bug mr-2"></i>加载管理页失败：' . htmlspecialchars($errMsg) . '<br><small>文件: ' . htmlspecialchars($errFile) . ':' . $e->getLine() . '</small></div>';
        }

        // 4. 提取主体内容
        $content = self::extractAdminBody($content);

        // 4.1 延迟内联脚本执行
        //     关键问题：模板在 emlog_footer.html 中才加载 jQuery CDN，
        //     而 $settingContent（即本插件的 HTML）渲染在前，导致插件内部的
        //     $(document).ready(...) 在 jQuery 未加载时执行 → ReferenceError。
        //     通过提取内联 <script> 块，用 base64 编码后包装为 jQuery 轮询加载器，
        //     确保在所有外部 JS 就绪后才执行。
        $content = self::deferInlineScripts($content);

        // 5. 重写表单 action：Z-Blog 插件的 form action 是相对路径（如 save_setting.php），
        //    在 ZhiCms 路由下无法正确工作，统一改写到 PluginController::setting
        $saveUrl = 'index.php?r=manage/plugin/setting&alias=' . urlencode($alias);
        $content = preg_replace(
            '#(<form[^>]*?\s)action\s*=\s*["\']([^"\']*save_setting\.php[^"\']*)["\']#i',
            '$1action="' . $saveUrl . '"',
            $content
        );
        // 替换其他可能的相对 action（如 main.php -> 自身）
        $content = preg_replace(
            '#(<form[^>]*?\s)action\s*=\s*["\']([^"\':]*\.php)["\']#i',
            '$1action="' . $saveUrl . '"',
            $content
        );
        // 同时替换 save_setting.php 作为 action 的 a 标签跳转
        $content = str_replace(
            "href=\"save_setting.php\"",
            'href="' . $saveUrl . '"',
            $content
        );

        // 5.1 重写插件内 JavaScript 中的 AJAX URL
        //      插件 JS 中 $.ajax({ url: "main.php?action=xxx" }) 这类相对路径在
        //      ZhiCms 路由下会被解析为错误的控制器路径（如 main/php），导致 404。
        //      统一改写为 PluginController::ajax 代理地址。
        $ajaxBaseUrl = 'index.php?r=manage/plugin/ajax&plugin=' . urlencode($alias);
        $content = str_replace('"main.php?', '"' . $ajaxBaseUrl . '&', $content);
        $content = str_replace("'main.php?", "'" . $ajaxBaseUrl . '&', $content);

        return $content;
    }

    /**
     * 确保 Z-Blog admin 桩文件存在（在 \BASE_PATH 和 parent(\BASE_PATH) 两个位置）
     * @param string|null $adminFile 可选，用于精确计算相对路径解析目标
     */
    protected static function ensureAdminStubs($adminFile = null)
    {
        $basePathClean = rtrim(str_replace('\\', '/', \BASE_PATH), '/');

        // 桩文件内容
        $stubBase = "if (!defined('ZBP_PATH')) { if (defined('\BASE_PATH')) define('ZBP_PATH', \BASE_PATH); else define('ZBP_PATH', dirname(dirname(dirname(__DIR__))) . '/'); }\n"
                  . "if (!isset(\$blogpath)) \$blogpath = ZBP_PATH;\n"
                  . "if (!isset(\$bloghost)) { \$bloghost = 'http://localhost/'; }\n";
        $stubAdmin = "if (!defined('ZBP_PATH')) define('ZBP_PATH', defined('\BASE_PATH') ? \BASE_PATH : dirname(dirname(dirname(__DIR__))) . '/');\n";
        $stubHeader = "if (!isset(\$blogtitle)) \$blogtitle = '';\n";

        $stubs = array(
            'zb_system/function/c_system_base.php'  => "<?php\n" . $stubBase,
            'zb_system/function/c_system_admin.php' => "<?php\n" . $stubAdmin,
            'zb_system/admin/admin_header.php'      => "<?php\n" . $stubHeader,
            'zb_system/admin/admin_top.php'         => "<?php\n",
            'zb_system/admin/admin_footer.php'      => "<?php\n",
        );

        // 计算需要桩文件的目标目录
        // 供 $zbp->systemdir 使用的目标目录：\ZBP_SYSTEM_DIR 已含 zb_system/ 后缀，
        // 故这里取其父目录，拼接 relPath('zb_system/...') 后正好落在 \ZBP_SYSTEM_DIR。
        $compatBase = defined('\ZBP_SYSTEM_DIR') ? rtrim(dirname(\ZBP_SYSTEM_DIR), '/') : $basePathClean;
        $targetDirs = array($compatBase);   // → 供 $zbp->systemdir 使用

        // parent(\BASE_PATH) → 供 plugins/{alias}/main.php 中的 ../../../zb_system/ 使用
        $parentPath = dirname($basePathClean);
        if ($parentPath !== $basePathClean && $parentPath !== '.') {
            $targetDirs[] = $parentPath;
        }

        // 如果提供了 admin 文件路径，精确计算该文件内 ../../../ 的解析目标
        if ($adminFile !== null) {
            $adminDir = dirname(str_replace('\\', '/', $adminFile));
            for ($i = 0; $i < 5; $i++) {  // 最多向上 5 层
                $candidate = dirname($adminDir);
                if ($candidate === $adminDir) break;
                $adminDir = $candidate;
                $targetDirs[] = $adminDir;
            }
            $targetDirs = array_unique($targetDirs);
        }

        foreach ($stubs as $relPath => $content) {
            foreach ($targetDirs as $baseDir) {
                $fullPath = $baseDir . '/' . $relPath;
                if (!is_file($fullPath)) {
                    $d = dirname($fullPath);
                    if (!is_dir($d)) {
                        $ok = @mkdir($d, 0755, true);
                        if (!$ok) continue;  // 跳过无法创建的目录
                    }
                    @file_put_contents($fullPath, $content);
                }
            }
        }
    }

    /**
     * 针对特定 admin 文件，精确解析其内部 require '../../../zb_system/...' 的路径
     * 并确保桩文件存在于解析后的位置。在 require $adminFile 之前调用。
     */
    protected static function ensureStubsForFile($adminFile)
    {
        // 读取文件内容，提取 require/include 的相对路径
        $content = @file_get_contents($adminFile);
        if ($content === false) return;

        $adminDir = dirname(str_replace('\\', '/', $adminFile));
        $patterns = array(
            // require '../../../zb_system/function/c_system_base.php'
            "#(?:require|include|require_once|include_once)\s+['\"](\.\.[\\\\/])+([^'\"]+zb_system[^'\"]*\.php)['\"]#",
            // require $zbp->systemdir . 'admin/admin_header.php'
            "#(?:require|include|require_once|include_once)\s+\\\$zbp->systemdir\s*\.\s*['\"]([^'\"]+\.php)['\"]#",
            // require $blogpath . 'zb_system/admin/...'
            "#(?:require|include|require_once|include_once)\s+\\\$blogpath\s*\.\s*['\"]([^'\"]+zb_system[^'\"]*\.php)['\"]#",
        );

        $neededStubs = array();
        $zbpSystemDir = defined('\ZBP_SYSTEM_DIR') ? \ZBP_SYSTEM_DIR : (\BASE_PATH . 'zb_system/');
        $blogpath = \BASE_PATH;

        foreach ($patterns as $idx => $pat) {
            if (!preg_match_all($pat, $content, $matches, PREG_SET_ORDER)) continue;
            foreach ($matches as $m) {
                if ($idx === 0) {
                    // 相对路径：构建完整路径
                    $relPart = $m[0];
                    // 提取完整路径
                    if (preg_match('#[\'"]([^\'"]*\.php)[\'\"]#', $relPart, $pathMatch)) {
                        $fullPath = $adminDir . '/' . $pathMatch[1];
                        // 标准化路径
                        $fullPath = self::normalizePath($fullPath);
                        $neededStubs[$fullPath] = "<?php\n";
                    }
                } elseif ($idx === 1) {
                    // $zbp->systemdir . 'xxx'
                    $fullPath = $zbpSystemDir . $m[1];
                    $neededStubs[$fullPath] = "<?php\n";
                } else {
                    // $blogpath . 'xxx'
                    $fullPath = $blogpath . $m[1];
                    $neededStubs[$fullPath] = "<?php\n";
                }
            }
        }

        // 特殊处理：为 admin_header.php / admin_top.php / admin_footer.php 添加基本内容
        $specialContent = array(
            'c_system_base'  => "<?php\nif (!defined('ZBP_PATH')) define('ZBP_PATH', \BASE_PATH);\nif (!isset(\$blogpath)) \$blogpath = ZBP_PATH;\nif (!isset(\$bloghost)) \$bloghost = 'http://localhost/';\n",
            'c_system_admin' => "<?php\nif (!defined('ZBP_PATH')) define('ZBP_PATH', \BASE_PATH);\n",
            'admin_header'   => "<?php\nif (!isset(\$blogtitle)) \$blogtitle = '';\n",
        );

        foreach ($neededStubs as $fullPath => $defaultContent) {
            if (!is_file($fullPath)) {
                $d = dirname($fullPath);
                if (!is_dir($d)) {
                    $ok = @mkdir($d, 0755, true);
                    if (!$ok) continue;
                }
                $content_stub = $defaultContent;
                foreach ($specialContent as $key => $special) {
                    if (strpos($fullPath, $key) !== false) {
                        $content_stub = $special;
                        break;
                    }
                }
                @file_put_contents($fullPath, $content_stub);
            }
        }
    }

    /** 标准化文件路径（处理 ../../ 等） */
    protected static function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $result = array();
        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($result);
            } elseif ($part !== '.' && $part !== '') {
                $result[] = $part;
            }
        }
        // Windows 绝对路径：保留盘符
        if (preg_match('#^[a-zA-Z]:$#', $parts[0] ?? '')) {
            return $parts[0] . '/' . implode('/', $result);
        }
        return '/' . implode('/', $result);
    }

    /**
     * 延迟执行内联脚本：解决模板 $settingContent 在 jQuery 加载前渲染的问题。
     *
     * 工作原理：
     * 1. 从 HTML 中提取所有无 src 属性的 <script> 块（外部脚本保持原样）
     * 2. 将原始脚本用 rawurlencode 编码，替换为 jQuery 轮询加载器
     * 3. 轮询加载器等待 jQuery 可用后，通过 decodeURIComponent 还原 UTF-8 文本
     *    并用 Blob URL 注入为新的 <script> 元素
     * 4. 因为运行时 DOM 已 ready，插件内部的 $(document).ready() 会立即触发
     *
     * ⚠️ 重要：不能使用 base64_encode/atob 方案！
     *    atob() 返回的是"二进制字符串"（每字符码点 0-255 代表一个字节），
     *    直接赋值给 textContent 时，UTF-8 多字节字符会被错误解释为多个
     *    Latin-1 字符（如"网络"→"ç½ç»"），导致中文乱码。
     *    改用 rawurlencode/decodeURIComponent 方案可正确保留 UTF-8 编码。
     */
    protected static function deferInlineScripts($html)
    {
        if (empty($html)) return $html;

        $hasInlineScript = false;
        $html = preg_replace_callback(
            '#<script\b([^>]*?)>(.*?)</script>#is',
            function ($m) use (&$hasInlineScript) {
                $attrs = $m[1];
                $body  = $m[2];

                // 外部脚本（有 src 属性）保持原样
                if (preg_match('/\bsrc\s*=/i', $attrs)) {
                    return $m[0];
                }
                // 空脚本跳过
                if (trim($body) === '') {
                    return '';
                }

                $hasInlineScript = true;
                // 使用 rawurlencode 编码脚本内容（保留 UTF-8 多字节字符的正确编码）
                // JavaScript 端通过 decodeURIComponent 还原为原始 UTF-8 文本
                $encoded = rawurlencode($body);
                return
                    '<script>' .
                    '(function(){var _r=function(){' .
                    'var _d=document.createElement("script");' .
                    'var _t=decodeURIComponent("' . $encoded . '");' .
                    '_d.textContent=_t;' .
                    'document.head.appendChild(_d);' .
                    '};' .
                    'if(typeof jQuery!=="undefined"){jQuery(document).ready(_r)}' .
                    'else{var _t=setInterval(function(){if(typeof jQuery!=="undefined"){clearInterval(_t);jQuery(document).ready(_r)}},80)}' .
                    '})();' .
                    '</script>';
            },
            $html
        );

        return $html;
    }

    /**
     * 处理兼容插件的 AJAX 请求（供 PluginController::ajax() 调用）
     *
     * 设置 Z-Blog 兼容环境，加载插件的 include.php，然后 include main.php
     * 让插件自身的 AJAX 处理逻辑接管请求。
     * 注意：main.php 中的 AJAX handler 通常以 die()/exit() 结束，
     * 因此调用此方法后会终止脚本执行。
     *
     * @param string $alias 插件别名
     */
    public static function handlePluginAjax($alias)
    {
        $dir = \BASE_PATH . 'plugins/' . $alias;
        $adminFile = $dir . '/main.php';

        if (!is_file($adminFile)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('status' => 'error', 'message' => '插件入口文件不存在'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 1. 确保 admin 桩文件存在
        self::ensureAdminStubs($adminFile);
        self::ensureStubsForFile($adminFile);

        // 2. 预置环境
        self::predefineConstants();
        if (!class_exists('\\ZhiCms\\base\\compat\\ZbpShim')) {
            require_once \BASE_PATH . 'ZhiCms/base/compat/ZbpShim.php';
        }
        global $zbp;
        if (!isset($zbp) || !($zbp instanceof \ZhiCms\base\compat\ZbpShim)) {
            $zbp = new \ZhiCms\base\compat\ZbpShim();
        }
        require_once \BASE_PATH . 'ZhiCms/base/compat/zblog_api.php';

        // 3. 加载插件 include.php
        $inc = $dir . '/include.php';
        if (is_file($inc)) {
            try {
                ob_start();
                require_once $inc;
                $output = ob_get_clean();
                if ($output !== '' && trim($output) !== '') {
                    error_log("[ZblogBridge] Plugin '$alias' unexpected output in AJAX include: " . substr($output, 0, 200));
                }
            } catch (\Throwable $e) {
                @ob_end_clean();
                error_log("[ZblogBridge] Plugin '$alias' AJAX include error: " . $e->getMessage());
            }
        }

        // 4. 初始化插件函数
        $initFn = $alias . '_init';
        if (function_exists($initFn)) {
            try { call_user_func($initFn); } catch (\Throwable $e) {}
        }

        // 5. 注入全局变量
        global $blogpath, $blogtitle, $lang, $bloghost;
        if (!isset($blogpath)) $blogpath = \BASE_PATH;
        if (!isset($blogtitle)) $blogtitle = '';
        if (!isset($bloghost)) $bloghost = 'http://localhost/';
        if (!isset($lang) || !is_array($lang)) $lang = array('msg' => array('submit' => '提交'));

        // 6. 让插件 main.php 处理 AJAX 请求
        //    内部通过 GetVars('action', 'GET') 区分不同 AJAX 动作
        require $adminFile;
        exit;
    }

    /**
     * 从 Z-Blog admin 输出中提取主体内容
     */
    protected static function extractAdminBody($html)
    {
        if (empty($html)) return '';
        if (preg_match('#<body[^>]*>(.*?)</body>#is', $html, $m)) {
            $html = $m[1];
        }
        return '<div class="zblog-plugin-admin" style="padding:10px;">' . $html . '</div>';
    }
}
