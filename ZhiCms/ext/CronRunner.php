<?php
namespace ZhiCms\ext;

/**
 * 计划任务运行器
 * - 支持调度：every N minute | hour | day，以及 5 段标准 cron 表达式
 * - 支持执行方式：url（HTTP 回调）/ php（代码或脚本）/ shell / python
 * - 任务来源：系统任务（代码注册，不可删）+ 自定义任务（后台配置）
 */
class CronRunner {

    /** 计算下次执行时间（Unix 时间戳），无法解析返回 0 */
    public static function nextRun($schedule, $from = null){
        $from = $from === null ? time() : $from;
        $schedule = trim($schedule);
        if ($schedule === '') return 0;

        // 简易表达式：every N second|minute|hour|day
        if (preg_match('/^every\s+(\d+)\s+(second|minute|hour|day)s?$/i', $schedule, $m)) {
            $n = (int)$m[1];
            $unit = strtolower($m[2]);
            $sec = $unit === 'second' ? 1 : ($unit === 'minute' ? 60 : ($unit === 'hour' ? 3600 : 86400));
            return $from + $n * $sec;
        }
        // 每日一次、随机时段：daily random H1-H2（每天在 H1~H2 点之间随机一个时刻执行）
        if (preg_match('/^daily\s+random\s+(\d+)-(\d+)$/i', $schedule, $m)) {
            $h1 = max(0, min(23, (int)$m[1]));
            $h2 = max(0, min(23, (int)$m[2]));
            if ($h2 < $h1) list($h1, $h2) = array($h2, $h1);
            $hour = mt_rand($h1, $h2);
            $minute = mt_rand(0, 59);
            $t = mktime($hour, $minute, 0, (int)date('n', $from), (int)date('j', $from), (int)date('Y', $from));
            if ($t <= $from) $t += 86400;
            return $t;
        }
        // 标准 cron 5 段
        if (substr_count($schedule, ' ') + 1 === 5) {
            return self::nextCron($schedule, $from);
        }
        return 0;
    }

    private static function nextCron($expr, $from){
        $parts = preg_split('/\s+/', $expr);
        list($m, $h, $dom, $mon, $dow) = $parts;
        // 从下一分钟开始枚举（最多 2 年）
        $t = $from + 60;
        $limit = $from + 2 * 365 * 86400;
        while ($t <= $limit) {
            $mi = (int)date('i', $t);
            $ho = (int)date('G', $t);
            $dm = (int)date('j', $t);
            $mo = (int)date('n', $t);
            $dw = (int)date('w', $t);
            if (self::matchField($mi, $m) && self::matchField($ho, $h) && self::matchField($dm, $dom)
                && self::matchField($mo, $mon) && self::matchField($dw, $dow)) {
                return $t;
            }
            $t += 60;
        }
        return 0;
    }

    private static function matchField($val, $field){
        if ($field === '*') return true;
        foreach (explode(',', $field) as $part) {
            if (strpos($part, '/') !== false) {
                list($range, $step) = explode('/', $part);
                $step = (int)$step;
                if ($range === '*') { if ($step > 0 && $val % $step === 0) return true; continue; }
            }
            if (strpos($part, '-') !== false) {
                list($a, $b) = explode('-', $part);
                if ($val >= (int)$a && $val <= (int)$b) return true;
                continue;
            }
            if ((int)$part === $val) return true;
        }
        return false;
    }

    /**
     * 记录一次外部 cron/计划任务触发（健康检查用）
     */
    public static function markPing(){
        $f = self::pingFile();
        @file_put_contents($f, (string)time());
    }

    /**
     * 读取最近一次外部触发时间（Unix 时间戳），从未触发返回 0
     */
    public static function lastPing(){
        $f = self::pingFile();
        if (!is_file($f)) return 0;
        $t = (int)@file_get_contents($f);
        return $t > 0 ? $t : 0;
    }

    private static function pingFile(){
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__));
        $dir = rtrim($root, '/\\') . '/data/cache/';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return $dir . 'cron_ping.txt';
    }

    /**
     * 执行一个任务（数组：exec_type, command）
     * @return array ['ok'=>bool, 'output'=>string]
     */
    public static function execute($task){
        $type = isset($task['exec_type']) ? $task['exec_type'] : 'url';
        $cmd  = isset($task['command']) ? $task['command'] : '';
        $output = '';
        $ok = false;
        try {
            switch ($type) {
                case 'url':
                    $out = \ZhiCms\ext\Http::doGet($cmd, 30);
                    $output = is_string($out) ? substr($out, 0, 500) : '非字符串返回';
                    $ok = ($out !== false && $out !== '');
                    break;

                case 'php':
                    // 支持三种写法：
                    // 1) PHP CLI 命令（如 php think run -p 8888 / php cli.php）→ 用 php 二进制执行
                    // 2) .php 脚本文件路径 → include 执行
                    // 3) PHP 代码段 → eval 执行
                    $trimCmd = trim($cmd);
                    if (preg_match('/^php(?:\s+|$)/i', $trimCmd) && $trimCmd !== 'php') {
                        // 以 "php xxx" 开头的 CLI 命令：改为调用 php 二进制，在项目根目录执行
                        $phpBin = self::phpBinary();
                        if (!$phpBin) { $output = '未找到 php 可执行文件'; $ok = false; break; }
                        // 去掉开头的 "php "，用绝对 php 路径补全
                        $realCmd = preg_replace('/^php\s+/i', '', $trimCmd, 1);
                        $full = $phpBin . ' ' . $realCmd;
                        $block = self::blockedCommand($realCmd);
                        if ($block) { $output = '危险命令已拦截：' . $block; $ok = false; break; }
                        $output = self::runProc($full);
                        $ok = ($output !== null);
                    } else if (preg_match('/\.php$/', $trimCmd) && is_file($trimCmd)) {
                        ob_start();
                        include $trimCmd;
                        $output = substr(ob_get_clean(), 0, 500);
                        $ok = true;
                    } else {
                        ob_start();
                        eval($cmd);
                        $output = substr(ob_get_clean(), 0, 500);
                        $ok = true;
                    }
                    break;

                case 'shell':
                    $block = self::blockedCommand($cmd);
                    if ($block) { $output = '危险命令已拦截：' . $block . '（为保证服务器安全，该命令不可执行）'; $ok = false; break; }
                    $output = self::runProc($cmd);
                    $ok = ($output !== null);
                    break;

                case 'python':
                    $block = self::blockedCommand($cmd);
                    if ($block) { $output = '危险命令已拦截：' . $block . '（为保证服务器安全，该命令不可执行）'; $ok = false; break; }
                    $py = self::pythonBinary();
                    if (!$py) { $output = '未找到 python 可执行文件'; $ok = false; break; }
                    if (preg_match('/\.py$/', trim($cmd)) && is_file($cmd)) {
                        $output = self::runProc($py . ' ' . escapeshellarg($cmd));
                    } else {
                        $tmp = tempnam(sys_get_temp_dir(), 'py_') . '.py';
                        file_put_contents($tmp, $cmd);
                        $output = self::runProc($py . ' ' . escapeshellarg($tmp));
                        @unlink($tmp);
                    }
                    $ok = ($output !== null);
                    break;

                default:
                    $output = '未知执行方式：' . $type;
                    $ok = false;
            }
        } catch (\Throwable $e) {
            $output = '执行异常：' . $e->getMessage();
            $ok = false;
        }
        return array('ok' => $ok, 'output' => is_string($output) ? $output : strval($output));
    }

    /**
     * 危险命令检测：shell / python 任务中禁止使用会危害服务器/操作系统的命令。
     * @param string $command 命令内容
     * @return string 命中则返回命中的危险命令；否则返回 ''
     */
    private static function blockedCommand($command){
        if (trim($command) === '') return '';
        $cmd = strtolower($command);

        // 明确列出的危险命令/参数（多词命令：精确或带分隔边界匹配）
        $dangerous = array(
            'init 0', 'init 6', 'telinit 0', 'telinit 6',
            'mkfs.ext', 'mke2fs',
            'chpasswd --stdin', 'rm -fr /', 'del /s', 'format c:',
            'dd if=/dev/zero', '> /dev/sda', '> /dev/hda',
            'chmod -r /', 'chmod 777 /', 'chown -r /',
            'kill -9 1', 'killall init',
        );
        foreach ($dangerous as $d) {
            if ($cmd === $d) return $d;
            // 多词危险命令：要求词边界，避免误伤路径（如 rm -rf /tmp 不拦截，只拦 rm -rf / 根目录）
            if (preg_match('/(?:^|[^\w])' . preg_quote($d, '/') . '(?:$|[^\w])/', $cmd)) return $d;
        }

        // rm -rf / （仅根目录本身，不拦 rm -rf /path）
        if (preg_match('/(?:^|[^\w])rm\s+-[rfrf]{2}\s+\/\s*$/i', $cmd) || preg_match('/(?:^|[^\w])rm\s+-[rfrf]{2}\s+\/\s+["\']?$/', $cmd)) return 'rm -rf /';

        // 词边界匹配单命令危险词（shutdown / mkfs / passwd / reboot 等）
        $single = array('shutdown', 'reboot', 'halt', 'poweroff', 'mkfs', 'fdisk', 'parted', 'passwd', 'chpasswd');
        foreach ($single as $s) {
            if (preg_match('/(?:^|[^\w])' . preg_quote($s, '/') . '(?:$|[^\w])/', $cmd)) return $s;
        }
        // mkfs.ext* / mkfs.* 系列（词边界）
        if (preg_match('/(?:^|[^\w])mkfs(?:\.[a-z0-9]+)?(?:$|[^\w])/', $cmd)) return 'mkfs';
        // init 0 / init 6 / telinit 0 / telinit 6
        if (preg_match('/(?:^|[^\w])init\s+[06](?:$|[^\w])/', $cmd)) return 'init 0/6';
        if (preg_match('/(?:^|[^\w])telinit\s+[06](?:$|[^\w])/', $cmd)) return 'telinit 0/6';

        return '';
    }

    /**
     * 在指定工作目录下执行外部命令（默认项目根目录，便于找到 think / 脚本文件等）。
     * @param string $command 命令
     * @param string|null $cwd 工作目录；null 时用项目根目录
     */
    private static function runProc($command, $cwd = null){
        if (!function_exists('proc_open')) return 'proc_open 不可用';
        if ($cwd === null) {
            $cwd = defined('ROOT_PATH') ? ROOT_PATH : (getcwd() ?: null);
        }
        $descriptors = array(0 => array('pipe','r'), 1 => array('pipe','w'), 2 => array('pipe','w'));
        $proc = @proc_open($command, $descriptors, $pipes, $cwd);
        if (!is_resource($proc)) return '无法启动进程';
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
        proc_close($proc);
        $result = $out . ($err ? "\n[err] " . $err : '');
        return substr($result, 0, 800);
    }

    private static function phpBinary(){
        $candidates = array(PHP_BINARY, 'php', '/usr/bin/php', '/usr/local/bin/php', 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe');
        foreach ($candidates as $c) {
            if ($c && is_string($c) && trim($c) !== '') {
                if (is_file($c)) return $c;
            }
        }
        // 用 where 探测
        foreach (array('php', '/usr/bin/php', '/usr/local/bin/php') as $c) {
            $out = @shell_exec("where $c 2>nul");
            if ($out && trim($out) !== '') return trim(explode("\n", $out)[0]);
        }
        return '';
    }

    private static function pythonBinary(){
        $candidates = array('python3', 'python', '/usr/bin/python3', '/usr/bin/python', 'C:/Python39/python.exe');
        foreach ($candidates as $c) {
            $out = @shell_exec("where $c 2>nul");
            if ($out && trim($out) !== '') return trim(explode("\n", $out)[0]);
        }
        return '';
    }
}
