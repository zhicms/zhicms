<?php
/**
 * ZhiCms 安装向导控制器
 * 兼容 PHP 5.6 - 8.4，支持 PDO 和 mysqli 双驱动
 */
namespace app\install\controller;

class IndexController
{
    private $phpMinVersion = '5.6.0';
    private $requiredExts = array('pdo', 'curl', 'gd', 'mbstring', 'json');
    private $writeDirs = array('data', 'data/config', 'data/cache', 'data/log', 'upload');
    private $configPath;
    private $dbConnection = null;   // 存储数据库连接（PDO 或 mysqli）
    private $dbDriver = '';         // 'pdo' 或 'mysqli'

    public function index()
    {
        // 子目录安装校验：ZhiCms 必须部署在网站根目录，不允许在子目录安装运行
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $scriptDir  = '';
        if ($scriptName !== '') {
            $scriptDir = rtrim(dirname($scriptName), '/\\');
        }
        if ($scriptDir !== '' && $scriptDir !== '.' && $scriptDir !== '/') {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>安装被阻止</title><style>'
                . 'body{font-family:"Microsoft YaHei",system-ui,sans-serif;background:#f5f7fa;'
                . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
                . '.card{background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08);'
                . 'max-width:520px;width:92%;padding:36px 32px}'
                . 'h2{color:#cf1322;font-size:18px;margin:0 0 14px}'
                . 'p{font-size:14px;color:#555;line-height:1.9}'
                . 'code{background:#f0f0f0;padding:2px 6px;border-radius:4px;color:#cf1322}'
                . '</style></head><body><div class="card"><h2>⛔ 安装被阻止：必须在网站根目录安装</h2>'
                . '<p>检测到当前程序位于子目录 <code>' . htmlspecialchars($scriptDir, ENT_QUOTES) . '</code>，'
                . 'ZhiCms 不支持在子目录下安装与运行（伪静态规则、路由解析、静态资源路径均按根目录设计，'
                . '放在子目录会导致整站 404 与资源加载失败）。</p>'
                . '<p>请按以下步骤操作：</p>'
                . '<p>1. 将全部程序文件移动到<b>网站根目录</b>（访问地址形如 '
                . '<code>https://你的域名/index.php</code> 而非 <code>https://你的域名/sub/index.php</code>）。<br>'
                . '2. 重新访问根目录下的安装入口完成安装。</p>'
                . '</div></body></html>';
            exit;
        }

        $this->configPath = \ROOT_PATH . 'data/config/';

        // 如果已安装，跳转到首页
        if (file_exists($this->configPath . 'install.lock')) {
            header('Location: ' . \ROOT_URL . 'index.php');
            exit;
        }

        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $step = $this->handlePost();
            // handlePost 内部已直接输出 HTML（renderSuccess/renderError），不再重复渲染
            if ($step >= 2) exit;
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

    /**
     * 获取可用的数据库驱动（PDO 优先，mysqli 回退）
     */
    private function getAvailableDriver()
    {
        // 优先检查 PDO
        if (class_exists('PDO')) {
            try {
                $drivers = \PDO::getAvailableDrivers();
                if (is_array($drivers) && in_array('mysql', $drivers)) {
                    return 'pdo';
                }
            } catch (\Exception $e) {
                // PDO 检查失败，继续尝试 mysqli
            }
        }

        // 回退到 mysqli
        if (function_exists('mysqli_connect')) {
            return 'mysqli';
        }

        return '';
    }

    /**
     * 测试数据库连接
     */
    private function connectDatabase($dbHost, $dbPort, $dbUser, $dbPwd)
    {
        $driver = $this->getAvailableDriver();

        if ($driver === 'pdo') {
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            try {
                // PHP 5.6 兼容：不使用 ATTR_TIMEOUT（PHP 7.0+ 才有）
                $options = array(
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                );

                // PHP 7.0+ 支持 ATTR_TIMEOUT
                if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
                    $options[\PDO::ATTR_TIMEOUT] = 5;
                }

                $pdo = new \PDO($dsn, $dbUser, $dbPwd, $options);
                $this->dbConnection = $pdo;
                $this->dbDriver = 'pdo';
                return true;
            } catch (\PDOException $e) {
                $this->dbConnection = null;
                $this->dbDriver = '';
                return false;
            }
        } elseif ($driver === 'mysqli') {
            // mysqli 连接（兼容 PHP 5.6+）
            if (function_exists('mysqli_init')) {
                $mysqli = \mysqli_init();
                if ($mysqli) {
                    // 设置超时
                    @mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
                    $connected = @mysqli_real_connect($mysqli, $dbHost, $dbUser, $dbPwd, '', (int)$dbPort);
                    if ($connected) {
                        mysqli_set_charset($mysqli, 'utf8mb4');
                        $this->dbConnection = $mysqli;
                        $this->dbDriver = 'mysqli';
                        return true;
                    }
                    @mysqli_close($mysqli);
                }
            } else {
                // PHP 5.6 旧版 mysqli 过程式接口
                $conn = @mysqli_connect($dbHost, $dbUser, $dbPwd, '', (int)$dbPort);
                if ($conn) {
                    mysqli_set_charset($conn, 'utf8mb4');
                    $this->dbConnection = $conn;
                    $this->dbDriver = 'mysqli';
                    return true;
                }
            }
            $this->dbConnection = null;
            $this->dbDriver = '';
            return false;
        }

        return false;
    }

    /**
     * 执行 SQL（自动适配 PDO 或 mysqli）
     */
    private function execSql($sql)
    {
        if ($this->dbDriver === 'pdo') {
            try {
                $this->dbConnection->exec($sql);
                return true;
            } catch (\PDOException $e) {
                return false;
            }
        } elseif ($this->dbDriver === 'mysqli') {
            $conn = $this->dbConnection;
            if ($conn) {
                $result = @mysqli_query($conn, $sql);
                // 释放结果集（如果是 SELECT 等）
                if ($result && $result !== true) {
                    @mysqli_free_result($result);
                }
                return $result !== false;
            }
        }
        return false;
    }

    /**
     * 执行 SQL 并返回错误信息
     */
    private function execSqlWithError($sql)
    {
        if ($this->dbDriver === 'pdo') {
            try {
                $this->dbConnection->exec($sql);
                return array('success' => true, 'error' => '');
            } catch (\PDOException $e) {
                return array('success' => false, 'error' => $e->getMessage());
            }
        } elseif ($this->dbDriver === 'mysqli') {
            $conn = $this->dbConnection;
            if ($conn) {
                $result = @mysqli_query($conn, $sql);
                if ($result === false) {
                    return array('success' => false, 'error' => mysqli_error($conn));
                }
                if ($result !== true) {
                    @mysqli_free_result($result);
                }
                return array('success' => true, 'error' => '');
            }
        }
        return array('success' => false, 'error' => '未知数据库驱动');
    }

    /**
     * 查询并获取单个值
     */
    private function fetchOne($sql)
    {
        if ($this->dbDriver === 'pdo') {
            try {
                $stmt = $this->dbConnection->query($sql);
                $row = $stmt->fetch(\PDO::FETCH_NUM);
                return $row ? $row[0] : null;
            } catch (\PDOException $e) {
                return null;
            }
        } elseif ($this->dbDriver === 'mysqli') {
            $conn = $this->dbConnection;
            if ($conn) {
                $result = @mysqli_query($conn, $sql);
                if ($result) {
                    $row = mysqli_fetch_row($result);
                    @mysqli_free_result($result);
                    return $row ? $row[0] : null;
                }
            }
        }
        return null;
    }

    /**
     * 预处理并执行（用于 UPDATE/INSERT 等带参数的查询）
     */
    private function prepareAndExecute($sql, $params)
    {
        if ($this->dbDriver === 'pdo') {
            try {
                $stmt = $this->dbConnection->prepare($sql);
                $stmt->execute($params);
                return true;
            } catch (\PDOException $e) {
                return false;
            }
        } elseif ($this->dbDriver === 'mysqli') {
            $conn = $this->dbConnection;
            if ($conn) {
                // 将 ? 占位符替换为 %s，然后用 vsprintf 填入转义后的参数
                $safeParams = array();
                foreach ($params as $v) {
                    if (is_int($v)) {
                        $safeParams[] = (string)$v;
                    } else {
                        $safeParams[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
                    }
                }
                $sql = str_replace('?', '%s', $sql);
                $fullSql = vsprintf($sql, $safeParams);
                if ($fullSql === false) {
                    // 参数数量与占位符数量不匹配，回退手动替换
                    $fullSql = $sql;
                    foreach ($safeParams as $val) {
                        $pos = strpos($fullSql, '%s');
                        if ($pos !== false) {
                            $fullSql = substr_replace($fullSql, $val, $pos, 2);
                        }
                    }
                }
                $result = @mysqli_query($conn, $fullSql);
                if ($result && $result !== true) {
                    @mysqli_free_result($result);
                }
                return $result !== false;
            }
        }
        return false;
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

        // 检查驱动可用性
        $driver = $this->getAvailableDriver();
        if (empty($driver)) {
            $this->renderError(array('未检测到可用的数据库驱动，请安装 PDO 或 mysqli 扩展'));
            return 2;
        }

        // 测试数据库连接
        if (!$this->connectDatabase($dbHost, $dbPort, $dbUser, $dbPwd)) {
            $errorMsg = ($this->dbDriver === 'mysqli' && $this->dbConnection) 
                ? mysqli_error($this->dbConnection) 
                : '连接失败，请检查数据库配置';
            $this->renderError(array('数据库连接失败：' . $errorMsg));
            return 2;
        }

        // 尝试创建数据库
        $createDbSql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $result = $this->execSqlWithError($createDbSql);
        if (!$result['success']) {
            // 尝试不指定 COLLATE（兼容旧版 MySQL）
            $createDbSql2 = "CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4";
            $result2 = $this->execSqlWithError($createDbSql2);
            if (!$result2['success']) {
                $this->renderError(array('创建数据库失败：' . $result['error']));
                return 2;
            }
        }

        // 选择数据库
        $useDbSql = "USE `{$dbName}`";
        $result = $this->execSqlWithError($useDbSql);
        if (!$result['success']) {
            $this->renderError(array('选择数据库失败：' . $result['error']));
            return 2;
        }

        // 导入 SQL
        $sqlFile = $this->configPath . 'zhicms.sql';
        if (!file_exists($sqlFile)) {
            $this->renderError(array('找不到数据库安装文件：' . $sqlFile));
            return 2;
        }

        $sqlContent = file_get_contents($sqlFile);
        if ($sqlContent === false) {
            $this->renderError(array('无法读取数据库安装文件'));
            return 2;
        }

        $sqlContent = str_replace('__PREFIX__', $dbPrefix, $sqlContent);
        $sqlContent = str_replace("\r", "\n", $sqlContent);

        // 分割并执行 SQL
        $segments = $this->splitSql($sqlContent);
        $successCount = 0;
        $errorSql = array();

        foreach ($segments as $sql) {
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

            $execResult = $this->execSqlWithError($sql);
            if ($execResult['success']) {
                $successCount++;
            } else {
                // 非致命错误可忽略（重复安装或部分 SQL 已存在时）
                $errMsg = $execResult['error'];
                $skipErrors = array(
                    'Duplicate column name',
                    'Duplicate entry',
                    'Table already exists',
                    'Key already exists',
                    'already exists',
                    'Duplicate',
                );
                $isSkippable = false;
                foreach ($skipErrors as $skipPattern) {
                    if (strpos($errMsg, $skipPattern) !== false) {
                        $isSkippable = true;
                        break;
                    }
                }
                if ($isSkippable) {
                    continue;
                }
                $errorSql[] = substr($sql, 0, 80) . '... => ' . $errMsg;
            }
        }

        // 更新管理员密码
        $hashedPwd = md5($adminPwd . 'yun_manage');
        $adminTable = $dbPrefix . 'manage';

        try {
            // 检查管理员表是否存在
            $tableCheckSql = "SHOW TABLES LIKE '{$adminTable}'";
            $tableExists = false;

            if ($this->dbDriver === 'pdo') {
                $stmt = $this->dbConnection->query($tableCheckSql);
                $tableExists = $stmt->fetch() !== false;
            } elseif ($this->dbDriver === 'mysqli') {
                $result = @mysqli_query($this->dbConnection, $tableCheckSql);
                $tableExists = $result && mysqli_num_rows($result) > 0;
                if ($result) @mysqli_free_result($result);
            }

            if (!$tableExists) {
                $this->renderError(array('管理员表不存在，请检查 SQL 是否正确导入'));
                return 2;
            }

            // 检查管理员表是否有 id=1 的记录
            $count = $this->fetchOne("SELECT COUNT(*) FROM `{$adminTable}`");

            if ($count == 0) {
                // 插入默认管理员
                $this->prepareAndExecute(
                    "INSERT INTO `{$adminTable}` (`id`, `username`, `password`) VALUES (?, ?, ?)",
                    array(1, $adminUser, $hashedPwd)
                );
            } else {
                // 更新管理员密码
                $this->prepareAndExecute(
                    "UPDATE `{$adminTable}` SET `username` = ?, `password` = ? WHERE `id` = 1",
                    array($adminUser, $hashedPwd)
                );
            }
        } catch (\Exception $e) {
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
            if ($content !== false && preg_match('/"site_name"\s*=>\s*"[^"]*"/', $content)) {
                $content = preg_replace('/("site_name"\s*=>\s*)"[^"]*"/', '$1"' . addslashes($siteName) . '"', $content);
                @file_put_contents($siteConfigPath, $content);
            }
        }

        // 创建安装锁文件
        @file_put_contents($this->configPath . 'install.lock', date('Y-m-d H:i:s'));

        // 关闭数据库连接
        $this->closeConnection();

        // 安装成功
        $this->renderSuccess($adminUser, $adminPwd, $siteName, $errorSql);
        return 3;
    }

    /**
     * 关闭数据库连接
     */
    private function closeConnection()
    {
        if ($this->dbDriver === 'mysqli' && $this->dbConnection) {
            @mysqli_close($this->dbConnection);
        }
        $this->dbConnection = null;
        $this->dbDriver = '';
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
                    . '<td>' . $item['required'] . '</td>'
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
            $list .= '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
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
                $sqlWarn .= '<li>' . htmlspecialchars($se, ENT_QUOTES, 'UTF-8') . '</li>';
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
    <p>版本 5.0.1 | PHP 电商导购 CMS 系统</p>
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
    Powered by ZhiCms 5.0.1
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
            'suggest'  => '请升级 PHP 到 ' . $this->phpMinVersion . ' 或更高版本（支持 PHP 5.6 - 8.4）',
        );

        // PHP 扩展
        $driverInfo = $this->getAvailableDriver();
        $items[] = array(
            'name'     => '数据库驱动 (PDO / MySQLi)',
            'current'  => $driverInfo === 'pdo' ? 'PDO (推荐)' : ($driverInfo === 'mysqli' ? 'MySQLi' : '未安装'),
            'required' => '至少安装一个',
            'pass'     => !empty($driverInfo),
            'suggest'  => '请在 php.ini 中启用 pdo_mysql 或 mysqli 扩展',
        );
        if (empty($driverInfo)) $allPass = false;

        // 其他必要扩展
        $otherExts = array('curl', 'gd', 'mbstring', 'json');
        foreach ($otherExts as $ext) {
            $loaded = extension_loaded($ext);
            if (!$loaded) $allPass = false;
            $items[] = array(
                'name'     => 'PHP 扩展 - ' . $ext,
                'current'  => $loaded ? '已安装' : '未安装',
                'required' => '建议安装',
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
            'pass'     => true,
            'suggest'  => '建议开启，部分 API 功能需要',
        );

        // 目录权限
        foreach ($this->writeDirs as $dir) {
            $fullPath = \ROOT_PATH . $dir;
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
        $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
        $path   = rtrim(dirname($script), '/\\');
        return $scheme . '://' . $host . $path . '/';
    }

    /**
     * 按分号分割 SQL 语句，正确处理字符串值内的分号
     * 只在字符串外部识别分号作为语句结束符
     */
    private function splitSql($content)
    {
        $statements = array();
        $current = '';
        $inString = false;
        $escaped = false;

        $len = strlen($content);
        for ($i = 0; $i < $len; $i++) {
            $ch = $content[$i];
            $current .= $ch;

            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($ch === '\\' && $inString) {
                $escaped = true;
                continue;
            }
            if ($ch === "'") {
                $inString = !$inString;
                continue;
            }
            if ($ch === ';' && !$inString) {
                $stmt = trim($current);
                if ($stmt !== '' && $stmt !== ';') {
                    $statements[] = $stmt;
                }
                $current = '';
            }
        }

        $stmt = trim($current);
        if ($stmt !== '' && $stmt !== ';') {
            $statements[] = $stmt;
        }

        return $statements;
    }
}
