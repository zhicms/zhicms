<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class EnvController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /**
     * 基本环境检测
     * 检测 PHP 版本、数据库驱动、运行所需扩展、目录可写性以及关键函数是否被禁用，
     * 缺失或不满足时给出开启扩展/修改配置的建议。
     */
    public function index()
    {
        $this->checkManageSession();

        $this->pageText = array("系统工具", "环境检测");
        $this->items = $this->buildEnvItems();
        $this->allPass = $this->isAllPass($this->items);

        $this->display('app/manage/view/env/index');
    }

    /**
     * AJAX 重新检测接口
     */
    public function check()
    {
        $this->checkManageSession();
        header('Content-Type: application/json; charset=utf-8');

        $items = $this->buildEnvItems();
        echo json_encode(array(
            'code'    => 0,
            'allPass' => $this->isAllPass($items),
            'items'   => $items,
        ), JSON_UNESCAPED_UNICODE);
    }

    // ==================== 环境检测核心 ====================

    /**
     * 组装检测项列表
     * 每项结构：
     *  name     检测名称
     *  current  当前状态描述
     *  required 要求
     *  pass     是否通过（true/false）
     *  level    级别：required(必须) / recommend(建议) / optional(可选)
     *  suggest  不通时的建议
     */
    private function buildEnvItems()
    {
        $items = array();

        // 1. PHP 版本
        $phpVer  = PHP_VERSION;
        $phpMin  = '7.0.0';
        $phpPass = version_compare($phpVer, $phpMin, '>=');
        $items[] = array(
            'name'     => 'PHP 版本',
            'current'  => $phpVer,
            'required' => '>= ' . $phpMin . '（推荐 7.3 - 8.3）',
            'pass'     => $phpPass,
            'level'    => 'required',
            'suggest'  => $phpPass ? '' : '请升级 PHP 到 ' . $phpMin . ' 或更高版本',
        );

        // 1.1 运行环境（Web Server）
        $serverSoft = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '未知';
        $items[] = array(
            'name'     => '运行环境 (Web Server)',
            'current'  => $serverSoft,
            'required' => 'Apache / Nginx / IIS 等',
            'pass'     => !empty($serverSoft),
            'level'    => 'optional',
            'suggest'  => '',
        );

        // 1.2 文件上传限制
        $uploadMax = ini_get('upload_max_filesize');
        $postMax   = ini_get('post_max_size');
        $items[] = array(
            'name'     => '文件上传限制 (upload_max_filesize)',
            'current'  => $uploadMax . '（post_max_size: ' . $postMax . '）',
            'required' => '>= 2M（视业务需要）',
            'pass'     => true,
            'level'    => 'optional',
            'suggest'  => '如需上传大文件，请调大 php.ini 中的 upload_max_filesize 与 post_max_size。',
        );

        // 1.3 脚本内存限制
        $memLimit = ini_get('memory_limit');
        $items[] = array(
            'name'     => '脚本内存限制 (memory_limit)',
            'current'  => $memLimit,
            'required' => '>= 128M（推荐 256M+）',
            'pass'     => true,
            'level'    => 'optional',
            'suggest'  => '采集/图片处理等大内存操作若失败，请调大 php.ini 中的 memory_limit。',
        );

        // 2. 数据库驱动
        $driver = $this->getAvailableDriver();
        $items[] = array(
            'name'     => '数据库驱动 (PDO / MySQLi)',
            'current'  => $driver === 'pdo' ? 'PDO (推荐)' : ($driver === 'mysqli' ? 'MySQLi' : '未安装'),
            'required' => '至少安装一个',
            'pass'     => !empty($driver),
            'level'    => 'required',
            'suggest'  => empty($driver) ? '请在 php.ini 中启用 pdo_mysql 或 mysqli 扩展' : '',
        );

        // 3. 必要/建议扩展
        $extChecks = array(
            'curl'     => array('level' => 'required', 'desc' => '远程 API 调用（AI、淘宝/大淘客、多麦联盟等）'),
            'gd'       => array('level' => 'required', 'desc' => '图片处理、验证码、缩略图、水印'),
            'mbstring' => array('level' => 'required', 'desc' => '多字节字符串处理（中文必须）'),
            'json'     => array('level' => 'required', 'desc' => 'JSON 编解码（接口通信必须）'),
            'openssl'  => array('level' => 'required', 'desc' => 'HTTPS/加密通信、微信/支付等'),
            'zlib'     => array('level' => 'recommend', 'desc' => 'Gzip 压缩输出、小程序打包'),
            'fileinfo' => array('level' => 'recommend', 'desc' => '上传文件真实 MIME 校验'),
            'simplexml'=> array('level' => 'recommend', 'desc' => 'XML 解析（部分联盟/微信接口）'),
            'iconv'    => array('level' => 'recommend', 'desc' => '字符编码转换'),
        );
        foreach ($extChecks as $ext => $meta) {
            $loaded = extension_loaded($ext);
            $items[] = array(
                'name'     => 'PHP 扩展 - ' . $ext,
                'current'  => $loaded ? '已安装' : '未安装',
                'required' => $meta['level'] === 'required' ? '必须安装' : '建议安装',
                'pass'     => $loaded,
                'level'    => $meta['level'],
                'suggest'  => $loaded ? '' : ('请在 php.ini 中启用 ' . $ext . ' 扩展。' . $meta['desc']),
            );
        }

        // 4. GD 的 WebP 支持位（GD 存在时才检测）
        if (extension_loaded('gd')) {
            $gdInfo   = gd_info();
            $webpSup  = !empty($gdInfo['WebP Support']);
            $items[] = array(
                'name'     => 'GD - WebP 支持',
                'current'  => $webpSup ? '支持' : '不支持',
                'required' => '建议开启',
                'pass'     => $webpSup,
                'level'    => 'recommend',
                'suggest'  => $webpSup ? '' : 'GD 已安装但未编译 WebP 支持，上传的 WebP 图片将无法被处理/转换。请重新编译 GD 并加上 --with-webp（或安装 libwebp 后重编 PHP）。',
            );
        }

        // 5. allow_url_fopen（影响 file_get_contents 远程抓取）
        $fopen = (string) ini_get('allow_url_fopen');
        $fopenOn = strtolower($fopen) === '1' || strtolower($fopen) === 'on';
        $items[] = array(
            'name'     => 'allow_url_fopen',
            'current'  => $fopenOn ? '开启' : '关闭',
            'required' => '建议开启',
            'pass'     => $fopenOn,
            'level'    => 'recommend',
            'suggest'  => $fopenOn ? '' : '部分功能（如多麦联盟 API、远程文件抓取）使用 file_get_contents 读取外部地址。若关闭，请设置 php.ini 中 allow_url_fopen = On，或确认已改用 curl 兜底。',
        );

        // 6. 关键函数是否被禁用（disable_functions）
        $disabled = $this->getDisabledFunctions();
        $funcChecks = array(
            'curl_exec'     => 'CURL 请求（AI / 商品 API 等）',
            'fsockopen'     => 'Socket 网络请求（部分 API 通信）',
            'pfsockopen'    => '持久 Socket 网络请求',
            'file_get_contents' => '文件/远程读取（含远程抓取，需配合 allow_url_fopen）',
            'proc_open'     => '异步/子进程执行（队列、异步任务）',
            'popen'         => '管道方式执行外部命令（异步相关）',
            'exec'          => '执行外部命令（异步/扩展能力）',
            'shell_exec'    => '执行 Shell 命令（异步/扩展能力）',
        );
        foreach ($funcChecks as $fn => $desc) {
            $ok = function_exists($fn) && !in_array(strtolower($fn), $disabled, true);
            $items[] = array(
                'name'     => '函数可用 - ' . $fn,
                'current'  => $ok ? '可用' : '被禁用/不存在',
                'required' => '视功能需要',
                'pass'     => $ok,
                // 这些函数被禁用通常不会让站点崩溃（有 curl 兜底），归为 optional
                'level'    => ($fn === 'curl_exec' || $fn === 'file_get_contents') ? 'recommend' : 'optional',
                'suggest'  => $ok ? '' : ('函数 ' . $fn . ' 被禁用（可能配置在 disable_functions 中）。' . $desc . ' 将不可用或降级；如不需要可忽略，否则请在 php.ini 的 disable_functions 中移除该函数。'),
            );
        }

        // 7. 异步执行能力（proc_open/exec/popen 至少一个可用）
        $asyncOk = ($this->fnUsable('proc_open', $disabled) || $this->fnUsable('exec', $disabled) || $this->fnUsable('popen', $disabled));
        $items[] = array(
            'name'     => '异步/命令行执行能力',
            'current'  => $asyncOk ? '可用' : '不可用',
            'required' => '可选',
            'pass'     => true, // 非必须，站点主体不依赖
            'level'    => 'optional',
            'suggest'  => $asyncOk ? '' : 'proc_open/exec/popen 均不可用，异步任务（如队列、后台推送）将无法执行，相关功能会降级为同步或跳过。如不需要可忽略。',
        );

        // 8. 目录可写性
        $writeDirs = array(
            'data/cache' => '缓存目录',
            'data'       => '数据目录',
            'upload'     => '上传目录',
            'runtime'    => '运行时目录',
        );
        foreach ($writeDirs as $dir => $desc) {
            $full  = \ROOT_PATH . $dir;
            $writable = is_dir($full) ? is_writable($full) : false;
            $items[] = array(
                'name'     => '目录可写 - /' . $dir,
                'current'  => $writable ? '可写' : (is_dir($full) ? '不可写' : '不存在'),
                'required' => '必须可写',
                'pass'     => $writable,
                'level'    => 'required',
                'suggest'  => $writable ? '' : ('请设置 /' . $dir . ' 目录为可写（Linux 通常 chmod 755/777，并确保属主为 Web 服务用户）。' . $desc . '不可写将导致缓存/上传失败。'),
            );
        }

        return $items;
    }

    /**
     * 判断整体是否通过（required + recommend 级别都通过才算全绿）
     */
    private function isAllPass($items)
    {
        foreach ($items as $it) {
            if (!$it['pass'] && ($it['level'] === 'required' || $it['level'] === 'recommend')) {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取可用的数据库驱动
     */
    private function getAvailableDriver()
    {
        if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
            return 'pdo';
        }
        if (extension_loaded('mysqli')) {
            return 'mysqli';
        }
        return '';
    }

    /**
     * 返回被禁用的函数列表（小写）
     */
    private function getDisabledFunctions()
    {
        $raw = ini_get('disable_functions');
        if (empty($raw)) {
            return array();
        }
        return array_map('trim', array_map('strtolower', explode(',', $raw)));
    }

    /**
     * 判断某函数是否可用（存在且未被禁用）
     */
    private function fnUsable($fn, $disabled)
    {
        return function_exists($fn) && !in_array(strtolower($fn), $disabled, true);
    }
}
