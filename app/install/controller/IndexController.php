<?php
/**
 * ZhiCms 安装向导控制器
 */
namespace app\install\controller;

class IndexController
{
    private $phpMinVersion = '7.0.0';
    private $requiredExts = array('pdo', 'pdo_mysql', 'curl', 'gd', 'mbstring', 'json');
    private $writeDirs = array('data', 'data/config', 'data/cache', 'data/log', 'upload');
    private $configPath;

    public function index()
    {
        $this->configPath = ROOT_PATH . 'data/config/';

        // 如果已安装，跳转到首页
        if (file_exists($this->configPath . 'install.lock')) {
            header('Location: ' . ROOT_URL . 'index.php');
            exit;
        }

        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $step = $this->handlePost();
        }

        $this->renderStep($step);
    }

    private function handlePost()
    {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'install') {
            return $this->doInstall();
        }
        return 1;
    }

    private function doInstall()
    {
        $dbHost   = isset($_POST['db_host']) ? trim($_POST['db_host']) : 'localhost';
        $dbPort   = isset($_POST['db_port']) ? trim($_POST['db_port']) : '3306';
        $dbUser   = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
        $dbPwd    = isset($_POST['db_pwd']) ? trim($_POST['db_pwd']) : '';
        $dbName   = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
        $dbPrefix = isset($_POST['db_prefix']) ? trim($_POST['db_prefix']) : 'yun_';
        $adminUser = isset($_POST['admin_user']) ? trim($_POST['admin_user']) : 'admin';
        $adminPwd  = isset($_POST['admin_pwd']) ? trim($_POST['admin_pwd']) : '';
        $siteName  = isset($_POST['site_name']) ? trim($_POST['site_name']) : 'ZhiCms';

        // 验证
        $errors = array();
        if (empty($dbUser)) $errors[] = '数据库用户名不能为空';
        if (empty($dbName)) $errors[] = '数据库名不能为空';
        if (empty($adminUser)) $errors[] = '管理员用户名不能为空';
        if (empty($adminPwd)) $errors[] = '管理员密码不能为空';
        if (strlen($adminPwd) < 6) $errors[] = '管理员密码至少6位';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbPrefix)) $errors[] = '表前缀只能包含字母、数字、下划线';
        if (!empty($errors)) {
            $this->renderError($errors);
            return 2;
        }

        // 测试数据库连接
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new \PDO($dsn, $dbUser, $dbPwd, array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ));

            // 尝试创建数据库
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
        } catch (\PDOException $e) {
            $this->renderError(array('数据库连接失败：' . $e->getMessage()));
            return 2;
        }

        // 导入 SQL
        $sqlFile = $this->configPath . 'zhicms.sql';
        if (!file_exists($sqlFile)) {
            $this->renderError(array('找不到数据库安装文件：' . $sqlFile));
            return 2;
        }

        $sqlContent = file_get_contents($sqlFile);
        $sqlContent = str_replace('yun_', $dbPrefix, $sqlContent);
        $sqlContent = str_replace("\r", "\n", $sqlContent);

        $segments = explode(";\n", trim($sqlContent));
        $successCount = 0;
        $errorSql = array();

        foreach ($segments as $sql) {
            $sql = trim($sql);
            if (empty($sql)) continue;

            // 跳过注释行
            $lines = explode("\n", $sql);
            $cleanLines = array();
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '--') === 0) {
                    continue;
                }
                $cleanLines[] = $line;
            }
            $sql = implode("\n", $cleanLines);
            if (empty($sql)) continue;

            try {
                $pdo->exec($sql);
                $successCount++;
            } catch (\PDOException $e) {
                // ALTER TABLE ADD COLUMN 可能字段已存在，忽略
                if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    continue;
                }
                $errorSql[] = substr($sql, 0, 80) . '... => ' . $e->getMessage();
            }
        }

        // 更新管理员密码
        try {
            $hashedPwd = md5($adminPwd);
            $stmt = $pdo->prepare("UPDATE `{$dbPrefix}manage` SET `username` = ?, `password` = ? WHERE `id` = 1");
            $stmt->execute(array($adminUser, $hashedPwd));
            // 如果管理员表为空，插入默认管理员
            $check = $pdo->query("SELECT COUNT(*) FROM `{$dbPrefix}manage`")->fetchColumn();
            if ($check == 0) {
                $stmt = $pdo->prepare("INSERT INTO `{$dbPrefix}manage` (`username`, `password`) VALUES (?, ?)");
                $stmt->execute(array($adminUser, $hashedPwd));
            }
        } catch (\PDOException $e) {
            $this->renderError(array('创建管理员账号失败：' . $e->getMessage()));
            return 2;
        }

        // 写入数据库配置文件
        $dbConfig = "<?php\n\n";
        $dbConfig .= "\$db=array(\n";
        $dbConfig .= "    'DB'=>array(\n";
        $dbConfig .= "        'default'=>array(\n";
        $dbConfig .= "            'DB_TYPE' => 'MysqlPdo',\n";
        $dbConfig .= "            'DB_HOST' => '" . addslashes($dbHost) . "',\n";
        $dbConfig .= "            'DB_USER' => '" . addslashes($dbUser) . "',\n";
        $dbConfig .= "            'DB_PWD' => '" . addslashes($dbPwd) . "',\n";
        $dbConfig .= "            'DB_PORT' => '" . addslashes($dbPort) . "',\n";
        $dbConfig .= "            'DB_NAME' => '" . addslashes($dbName) . "',\n";
        $dbConfig .= "            'DB_CHARSET' => 'utf8mb4',\n";
        $dbConfig .= "            'DB_PREFIX' => '" . addslashes($dbPrefix) . "',\n";
        $dbConfig .= "            'DB_CACHE' => 'DB_CACHE',\n";
        $dbConfig .= "        ),\n";
        $dbConfig .= "    ),\n";
        $dbConfig .= ");\n";

        if (!@file_put_contents($this->configPath . 'db.php', $dbConfig)) {
            $this->renderError(array('无法写入数据库配置文件 data/config/db.php，请检查目录权限'));
            return 2;
        }

        // 写入站点名称配置
        $siteConfigPath = $this->configPath . 'siteconfig.php';
        if (file_exists($siteConfigPath)) {
            $content = file_get_contents($siteConfigPath);
            // 尝试替换 site_name 或 siteurl
            if (preg_match('/"site_name"\s*=>\s*"[^"]*"/', $content)) {
                $content = preg_replace('/("site_name"\s*=>\s*)"[^"]*"/', '$1"' . addslashes($siteName) . '"', $content);
                @file_put_contents($siteConfigPath, $content);
            }
        }

        // 创建安装锁文件
        @file_put_contents($this->configPath . 'install.lock', date('Y-m-d H:i:s'));

        // 安装成功
        $this->renderSuccess($adminUser, $adminPwd, $siteName, $errorSql);
        return 3;
    }

    private function renderStep($step)
    {
        $envCheck = $this->getEnvCheck();
        $canNext  = $envCheck['all_pass'];

        $html = $this->getHeader($step);
        switch ($step) {
            case 1:
                $html .= $this->renderEnvCheck($envCheck, $canNext);
                break;
            default:
                $html .= $this->renderForm($envCheck, $canNext);
                break;
        }
        $html .= $this->getFooter();
        echo $html;
    }

    private function renderEnvCheck($env, $canNext)
    {
        $rows = '';
        foreach ($env['items'] as $item) {
            $icon   = $item['pass'] ? '✓' : '✗';
            $cls    = $item['pass'] ? 'pass' : 'fail';
            $sugg   = $item['pass'] ? '' : '<span class="suggest">' . $item['suggest'] . '</span>';
            $rows  .= '<tr class="' . $cls . '"><td>' . $item['name'] . '</td>'
                    . '<td>' . $item['current'] . '</td>'
                    . '<td>' . ($item['pass'] ? $item['required'] : $item['required']) . '</td>'
                    . '<td><span class="icon">' . $icon . '</span> ' . $sugg . '</td></tr>';
        }

        $nextBtn = $canNext
            ? '<a href="?r=install&step=2" class="btn">下一步：数据库配置</a>'
            : '<button class="btn btn-disabled" disabled>请先解决以上问题再继续</button>';

        return <<<HTML
        <div class="step-box">
            <h2>环境检查</h2>
            <table class="env-table">
                <thead><tr><th>检查项</th><th>当前值</th><th>要求</th><th>结果</th></tr></thead>
                <tbody>{$rows}</tbody>
            </table>
            <div class="action-bar">{$nextBtn}</div>
        </div>
HTML;
    }

    private function renderForm($env, $canNext)
    {
        if (!$canNext) {
            return '<div class="step-box"><h2>环境未通过</h2><p class="warn">请先满足环境要求再继续安装。</p>'
                 . '<a href="?r=install" class="btn">返回检查</a></div>';
        }

        return <<<HTML
        <div class="step-box">
            <h2>数据库与站点配置</h2>
            <form method="post" id="install-form">
                <input type="hidden" name="action" value="install">

                <div class="form-section">
                    <h3>数据库设置</h3>
                    <div class="form-row">
                        <label>数据库主机</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-row">
                        <label>数据库端口</label>
                        <input type="text" name="db_port" value="3306" required>
                    </div>
                    <div class="form-row">
                        <label>数据库用户名</label>
                        <input type="text" name="db_user" placeholder="数据库用户名" required>
                    </div>
                    <div class="form-row">
                        <label>数据库密码</label>
                        <input type="text" name="db_pwd" placeholder="数据库密码（可为空）">
                    </div>
                    <div class="form-row">
                        <label>数据库名称</label>
                        <input type="text" name="db_name" placeholder="数据库名" required>
                    </div>
                    <div class="form-row">
                        <label>表前缀</label>
                        <input type="text" name="db_prefix" value="yun_" required>
                    </div>
                </div>

                <div class="form-section">
                    <h3>管理员账号</h3>
                    <div class="form-row">
                        <label>管理员用户名</label>
                        <input type="text" name="admin_user" value="admin" required>
                    </div>
                    <div class="form-row">
                        <label>管理员密码</label>
                        <input type="password" name="admin_pwd" placeholder="至少6位密码" required minlength="6">
                    </div>
                </div>

                <div class="form-section">
                    <h3>站点信息</h3>
                    <div class="form-row">
                        <label>站点名称</label>
                        <input type="text" name="site_name" value="ZhiCms导购" required>
                    </div>
                </div>

                <div class="action-bar">
                    <button type="submit" class="btn btn-primary">开始安装</button>
                </div>
            </form>
        </div>
HTML;
    }

    private function renderError($errors)
    {
        $list = '';
        foreach ($errors as $e) {
            $list .= '<li>' . htmlspecialchars($e) . '</li>';
        }
        $html = $this->getHeader(2);
        $html .= '<div class="step-box"><h2>安装出错</h2><ul class="error-list">' . $list
               . '</ul><div class="action-bar"><a href="?r=install&step=2" class="btn">返回修改</a></div></div>';
        $html .= $this->getFooter();
        echo $html;
    }

    private function renderSuccess($adminUser, $adminPwd, $siteName, $errorSql)
    {
        $sqlWarn = '';
        if (!empty($errorSql)) {
            $sqlWarn = '<div class="warn-box"><strong>部分 SQL 执行异常（已跳过，不影响使用）：</strong><ul>';
            foreach ($errorSql as $se) {
                $sqlWarn .= '<li>' . htmlspecialchars($se) . '</li>';
            }
            $sqlWarn .= '</ul></div>';
        }

        $html = $this->getHeader(3);
        $html .= <<<HTML
        <div class="step-box success-box">
            <div class="success-icon">✓</div>
            <h2>安装成功！</h2>
            <p class="success-msg">{$siteName} 已安装完成</p>
            {$sqlWarn}
            <div class="info-box">
                <h3>登录信息</h3>
                <table>
                    <tr><td>后台地址</td><td><strong>{$this->getSiteUrl()}index.php?r=manage</strong></td></tr>
                    <tr><td>管理员</td><td><strong>{$adminUser}</strong></td></tr>
                    <tr><td>密码</td><td><strong>{$adminPwd}</strong></td></tr>
                </table>
            </div>
            <div class="warn-box">
                <strong>安全提醒：</strong>
                <ol>
                    <li>请立即删除 <code>data/config/install.lock</code> 文件或通过后台修改密码</li>
                    <li>建议删除 <code>app/install/</code> 安装目录</li>
                </ol>
            </div>
            <div class="action-bar">
                <a href="{$this->getSiteUrl()}index.php" class="btn">访问前台</a>
                <a href="{$this->getSiteUrl()}index.php?r=manage" class="btn btn-primary" target="_blank">进入后台</a>
            </div>
        </div>
HTML;
        $html .= $this->getFooter();
        echo $html;
    }

    private function getHeader($currentStep)
    {
        $steps = array(
            1 => array('num' => '1', 'name' => '环境检查'),
            2 => array('num' => '2', 'name' => '安装配置'),
            3 => array('num' => '3', 'name' => '安装完成'),
        );

        $stepHtml = '';
        foreach ($steps as $s => $info) {
            $active = ($s <= $currentStep) ? ' active' : '';
            $done   = ($s < $currentStep) ? ' done' : '';
            $stepHtml .= '<div class="step' . $active . $done . '">'
                       . '<span class="step-num">' . $info['num'] . '</span>'
                       . '<span class="step-name">' . $info['name'] . '</span></div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZhiCms 安装向导</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f0f2f5;color:#333;line-height:1.6;min-height:100vh}
.header{background:linear-gradient(135deg,#ff6a00,#ff3d00);color:#fff;padding:40px 20px 30px;text-align:center}
.header h1{font-size:28px;font-weight:700;margin-bottom:8px}
.header p{opacity:.85;font-size:14px}
.steps{display:flex;justify-content:center;align-items:center;padding:30px 20px;max-width:700px;margin:0 auto;gap:0}
.step{display:flex;align-items:center;gap:10px;padding:0 20px;position:relative;color:#bbb}
.step.active{color:#ff5000;font-weight:600}
.step.done{color:#52c41a}
.step-num{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;border:2px solid #ddd;font-size:14px;font-weight:700;background:#fff;flex-shrink:0}
.step.active .step-num{border-color:#ff5000;background:#ff5000;color:#fff}
.step.done .step-num{border-color:#52c41a;background:#52c41a;color:#fff}
.step::after{content:'';position:absolute;right:-30px;top:50%;transform:translateY(-50%);width:60px;height:2px;background:#ddd;margin-left:0}
.step:last-child::after{display:none}
.step.active::after,.step.done::after{background:#52c41a}
.step-name{font-size:14px;white-space:nowrap}
.main{max-width:700px;margin:0 auto 40px;padding:0 20px}
.step-box{background:#fff;border-radius:10px;padding:30px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.step-box h2{font-size:20px;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
.env-table{width:100%;border-collapse:collapse;font-size:13px}
.env-table th{background:#fafafa;padding:10px 12px;text-align:left;font-weight:600;border-bottom:2px solid #e8e8e8}
.env-table td{padding:10px 12px;border-bottom:1px solid #f0f0f0}
.env-table .pass{color:#52c41a}
.env-table .fail{color:#ff4d4f;background:#fff2f0}
.env-table .icon{font-size:16px;font-weight:700}
.suggest{color:#999;font-size:12px;display:block;margin-top:2px}
.form-section{margin-bottom:25px}
.form-section h3{font-size:15px;font-weight:600;margin-bottom:15px;padding-bottom:8px;border-bottom:1px solid #f0f0f0;color:#ff5000}
.form-row{display:flex;align-items:center;margin-bottom:14px}
.form-row label{width:120px;flex-shrink:0;text-align:right;padding-right:15px;font-size:14px;color:#555}
.form-row input{flex:1;height:40px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;transition:border-color .2s}
.form-row input:focus{border-color:#ff5000;box-shadow:0 0 0 2px rgba(255,80,0,.1)}
.action-bar{text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid #f0f0f0}
.btn{display:inline-block;padding:12px 36px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;margin:0 8px}
.btn-primary{background:linear-gradient(135deg,#ff6a00,#ff3d00);color:#fff}
.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
.btn{background:#fff;color:#ff5000;border:1px solid #ff5000}
.btn:hover{background:#fff5f0}
.btn-disabled{background:#f5f5f5;color:#bbb;border-color:#d9d9d9;cursor:not-allowed}
.error-list{list-style:none;padding:0}
.error-list li{background:#fff2f0;border:1px solid #ffccc7;border-radius:6px;padding:10px 15px;margin-bottom:8px;color:#ff4d4f;font-size:14px}
.warn{color:#ff4d4f;font-size:14px;margin:10px 0}
.warn-box{background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:15px 18px;margin:15px 0;font-size:13px;color:#ad6800}
.warn-box ol,.warn-box ul{margin:8px 0 0 20px;line-height:1.8}
.success-icon{width:80px;height:80px;border-radius:50%;background:#52c41a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:40px;margin:0 auto 20px}
.success-box{text-align:center}
.success-msg{font-size:16px;color:#666;margin-bottom:20px}
.info-box{background:#f6ffed;border:1px solid #b7eb8f;border-radius:8px;padding:18px;margin:15px 0;text-align:left}
.info-box h3{margin-bottom:12px;color:#52c41a;font-size:14px}
.info-box table{width:100%;font-size:14px}
.info-box td{padding:6px 0}
.info-box td:first-child{color:#999;width:80px}
code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-size:12px;color:#ff5000}
@media (max-width:600px){
    .steps{flex-wrap:wrap;gap:10px}
    .step::after{display:none}
    .form-row{flex-direction:column;align-items:flex-start}
    .form-row label{width:auto;text-align:left;margin-bottom:6px;padding-right:0}
    .form-row input{width:100%}
    .step-box{padding:20px}
}
</style>
</head>
<body>
<div class="header">
    <h1>ZhiCms 安装向导</h1>
    <p>版本 5.0.0 | PHP 电商导购 CMS 系统</p>
</div>
<div class="steps">{$stepHtml}</div>
<div class="main">
HTML;
    }

    private function getFooter()
    {
        return <<<HTML
</div>
<div style="text-align:center;padding:30px;color:#bbb;font-size:12px">
    Powered by ZhiCms 5.0.0
</div>
</body>
</html>
HTML;
    }

    private function getEnvCheck()
    {
        $items = array();
        $allPass = true;

        // PHP 版本
        $phpVer = PHP_VERSION;
        $phpPass = version_compare($phpVer, $this->phpMinVersion, '>=');
        if (!$phpPass) $allPass = false;
        $items[] = array(
            'name'     => 'PHP 版本',
            'current'  => $phpVer,
            'required' => '>= ' . $this->phpMinVersion,
            'pass'     => $phpPass,
            'suggest'  => '请升级 PHP 到 ' . $this->phpMinVersion . ' 或更高版本',
        );

        // PHP 扩展
        foreach ($this->requiredExts as $ext) {
            $loaded = extension_loaded($ext);
            if (!$loaded) $allPass = false;
            $items[] = array(
                'name'     => 'PHP 扩展 - ' . $ext,
                'current'  => $loaded ? '已安装' : '未安装',
                'required' => '必须安装',
                'pass'     => $loaded,
                'suggest'  => '请在 php.ini 中启用 ' . $ext . ' 扩展',
            );
        }

        // allow_url_fopen
        $fopen = ini_get('allow_url_fopen');
        $items[] = array(
            'name'     => 'allow_url_fopen',
            'current'  => $fopen ? '开启' : '关闭',
            'required' => '建议开启',
            'pass'     => true, // 不强制
            'suggest'  => '建议开启，部分 API 功能需要',
        );

        // 目录权限
        foreach ($this->writeDirs as $dir) {
            $fullPath = ROOT_PATH . $dir;
            $writable = is_writable($fullPath);
            if (!$writable) $allPass = false;
            $items[] = array(
                'name'     => '目录可写 - /' . $dir,
                'current'  => $writable ? '可写' : (file_exists($fullPath) ? '不可写' : '不存在'),
                'required' => '必须可写',
                'pass'     => $writable,
                'suggest'  => '请设置 /' . $dir . ' 目录权限为 777',
            );
        }

        return array('items' => $items, 'all_pass' => $allPass);
    }

    private function getSiteUrl()
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $path   = rtrim(dirname($script), '/\\');
        return $scheme . '://' . $host . $path . '/';
    }
}
