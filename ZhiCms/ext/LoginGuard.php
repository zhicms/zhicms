<?php
/**
 * LoginGuard —— 后台登录全局失败封禁（文件级，抗 cookie 重置爆破）
 * ------------------------------------------------------------------
 * 原 LoginController 的限流基于 $_SESSION（会话级），攻击者在客户端清除
 * cookie 即可重置计数，无法抵御分布式/持续爆破。本类改为【文件级】持久计数：
 *
 *   - 维度：以「客户端 IP」为主维度（同 IP 换用户名也累计），「用户名」为辅助维度；
 *   - 窗口：300 秒内失败次数累计；
 *   - 阈值：任一维度失败 ≥ $limit 次 → 封禁该维度 $banSeconds 秒；
 *   - 封禁期间：无论账号密码是否正确，一律拒绝登录（打断爆破节奏）；
 *   - 存储：runtime/ban/*.json（runtime 目录已被 Web 服务器禁止访问，安全）；
 *   - 并发：写文件使用 LOCK_EX 原子锁，避免竞争条件。
 *
 * 用法（在 LoginController 登录前）：
 *   $guard = new \ZhiCms\ext\LoginGuard();
 *   if ($guard->isBlocked($ip, $user)) { /* 拒绝 *\/ exit; }
 *   // 登录失败： $guard->recordFail($ip, $user);
 *   // 登录成功： $guard->clear($ip, $user);
 */
namespace ZhiCms\ext;

class LoginGuard
{
    /** 失败窗口（秒） */
    protected $window = 300;
    /** 触发封禁的失败次数 */
    protected $limit = 10;
    /** 封禁时长（秒），默认 1 小时 */
    protected $banSeconds = 3600;
    /** 存储目录 */
    protected $dir;

    public function __construct($window = 300, $limit = 10, $banSeconds = 3600)
    {
        $this->window = $window;
        $this->limit = $limit;
        $this->banSeconds = $banSeconds;
        $root = defined('ROOT_PATH') ? ROOT_PATH : (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);
        $this->dir = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'ban' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    /**
     * 是否处于封禁状态
     * @param string $ip   客户端 IP
     * @param string $user 尝试的用户名（可为空）
     */
    public function isBlocked($ip, $user = '')
    {
        if ($this->blockedBy($ip)) return true;
        if ($user !== '' && $this->blockedBy($this->userKey($user))) return true;
        return false;
    }

    /**
     * 记录一次登录失败（累计到 IP 与用户名两个维度）
     */
    public function recordFail($ip, $user = '')
    {
        $this->hit($ip);
        if ($user !== '') {
            $this->hit($this->userKey($user));
        }
    }

    /**
     * 登录成功：清除该 IP 与用户名的失败记录
     */
    public function clear($ip, $user = '')
    {
        $this->reset($ip);
        if ($user !== '') {
            $this->reset($this->userKey($user));
        }
    }

    /**
     * 返回剩余封禁秒数（未封禁返回 0），供前端提示
     */
    public function remaining($ip, $user = '')
    {
        $r = $this->remainingFor($ip);
        if ($r > 0) return $r;
        if ($user !== '') {
            return $this->remainingFor($this->userKey($user));
        }
        return 0;
    }

    // ---------- 内部实现 ----------

    private function userKey($user)
    {
        return 'u_' . md5($user);
    }

    private function fileOf($key)
    {
        return $this->dir . $key . '.json';
    }

    private function blockedBy($key)
    {
        $data = $this->read($key);
        if (empty($data)) return false;
        // 处于封禁期
        if (!empty($data['ban_until']) && $data['ban_until'] > time()) {
            return true;
        }
        // 窗口内失败次数达到阈值
        if (!empty($data['time']) && $data['time'] > time() - $this->window && !empty($data['count']) && $data['count'] >= $this->limit) {
            // 触发封禁：写入 ban_until
            $data['ban_until'] = time() + $this->banSeconds;
            $this->write($key, $data);
            return true;
        }
        return false;
    }

    private function remainingFor($key)
    {
        $data = $this->read($key);
        if (!empty($data['ban_until']) && $data['ban_until'] > time()) {
            return $data['ban_until'] - time();
        }
        return 0;
    }

    private function hit($key)
    {
        $data = $this->read($key);
        $now = time();
        if (empty($data) || empty($data['time']) || $data['time'] < $now - $this->window) {
            $data = array('time' => $now, 'count' => 0, 'ban_until' => 0);
        }
        $data['count']++;
        $data['time'] = $now;
        $this->write($key, $data);
    }

    private function reset($key)
    {
        $file = $this->fileOf($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function read($key)
    {
        $file = $this->fileOf($key);
        if (!is_file($file)) return array();
        $raw = @file_get_contents($file);
        if ($raw === false) return array();
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : array();
    }

    private function write($key, $data)
    {
        $file = $this->fileOf($key);
        $tmp = $file . '.tmp';
        $ok = @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
        if ($ok !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }
}
