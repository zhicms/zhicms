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
                    if (preg_match('/\.php$/', trim($cmd)) && is_file($cmd)) {
                        // 脚本文件
                        ob_start();
                        include $cmd;
                        $output = substr(ob_get_clean(), 0, 500);
                    } else {
                        ob_start();
                        eval($cmd);
                        $output = substr(ob_get_clean(), 0, 500);
                    }
                    $ok = true;
                    break;

                case 'shell':
                    $output = self::runProc($cmd);
                    $ok = ($output !== null);
                    break;

                case 'python':
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

    private static function runProc($command){
        if (!function_exists('proc_open')) return 'proc_open 不可用';
        $descriptors = array(0 => array('pipe','r'), 1 => array('pipe','w'), 2 => array('pipe','w'));
        $proc = proc_open($command, $descriptors, $pipes);
        if (!is_resource($proc)) return '无法启动进程';
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
        proc_close($proc);
        $result = $out . ($err ? "\n[err] " . $err : '');
        return substr($result, 0, 800);
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
