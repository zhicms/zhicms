<?php
/**
 * ============================================================
 * ZhiCms 单文件在线安装 / 升级引导程序
 * ------------------------------------------------------------
 * 用法：把这个文件放到网站根目录，访问 域名/install.php
 *   · 新装（站点根目录无 install.lock）→ 自动下载完整安装包并解压覆盖，
 *     完成后引导进入 域名/index.php?r=install 走本地安装向导填库建表。
 *   · 升级（站点已装，存在 install.lock）→ 自动下载升级增量包覆盖程序，
 *     并执行 zhicms_update.sql、更新版本号。
 *
 * 原理：复刻 Z-BlogPHP install.php 的「单文件引导」思路，但改用 zip 完整包
 *       + 智能覆盖（保护 db.php、合并配置文件），更适合大型程序。
 *
 * 安全说明：
 *   1. 升级模式要求提供升级密钥（install.lock 内容的前 8 位 + 追加常量），
 *      防止站点被他人远程覆盖。密钥默认：zcms2zhi 或 install.lock 中前缀。
 *   2. 程序覆盖用 copyFilesSafe：跳过 db.php、智能合并 siteconfig/seo/sms/
 *      apiset/rule/global，用户配置 100% 保留。
 * ============================================================
 */
set_time_limit(600);
ini_set('memory_limit', '512M');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
header('Content-Type: text/html; charset=utf-8');

define('ZCMS_ROOT', __DIR__);

/* ---------- 子目录安装校验 ----------
 * ZhiCms 必须使用网站根目录安装（伪静态、路由、资源引用均按根目录设计）。
 * 若用户把程序放进子目录（如 /sub/install.php），停止安装并提示。
 */
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$scriptDir  = '';
if ($scriptName !== '') {
    $scriptDir = rtrim(dirname($scriptName), '/\\');
}
if ($scriptDir !== '' && $scriptDir !== '.' && $scriptDir !== '/') {
    // 仍可能命中 Windows 盘符/CLI 极端情况，但 SCRIPT_NAME 在 Web 下足够可靠
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
        . '<p>1. 将全部程序文件移动到<b>网站根目录</b>（即域名直接指向的目录，访问地址形如 '
        . '<code>https://你的域名/install.php</code> 而非 <code>https://你的域名/sub/install.php</code>）。<br>'
        . '2. 重新访问根目录下的 <code>install.php</code> 完成安装。</p>'
        . '</div></body></html>';
    exit;
}

/* ---------- 可配置项 ---------- */
$CONFIG = array(
    // 动态版本检查接口：返回 JSON，含 full_zhicms(完整包) / full_update(更新包) / version
    'checkUrl' => 'https://www.zhi.red/update_check.php',
    // 目标版本号（仅当接口不可用时作为兜底展示）
    'version' => '5.0.2',
    // 兜底完整安装包（接口不可用时回退）
    'installZipUrl' => 'https://www.zhi.red/d/zhicms.zip',
    // 兜底升级增量包（接口不可用时回退）
    'updateZipUrl'  => 'https://www.zhi.red/d/update/full_update_5.0.2.zip',
    // 升级 SQL（升级包内，若存在则执行）
    'updateSqlFile' => 'zhicms_update.sql',
    // 解压临时目录
    'tempDir' => ZCMS_ROOT . '/data/zcms_install_temp/',
    // 下载暂存
    'zipFile' => ZCMS_ROOT . '/data/zcms_install_download.zip',
    // 升级密钥提示（升级模式需输入）
    'secretHint' => 'zcms2zhi',
);
/* ----------------------------- */

$lockFile  = ZCMS_ROOT . '/data/config/install.lock';
$isInstall = !file_exists($lockFile);   // 无锁 => 新装；有锁 => 升级

$mode = $isInstall ? 'install' : 'update';

// ===== 参数 =====
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$secret = isset($_POST['secret']) ? trim($_POST['secret']) : '';

// ===== 静态资源（按钮页 + 进度页共用）=====
$htmlHead = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ZhiCms 在线安装 / 升级</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Microsoft YaHei",system-ui,sans-serif;background:#f5f7fa;color:#333;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08);max-width:560px;width:92%;padding:36px 32px;margin:24px auto}
.logo{text-align:center;margin-bottom:22px}
.logo .logo-name{font-size:22px;font-weight:700;color:#ff4d4f;letter-spacing:1px}
.logo .logo-sub{font-size:13px;color:#999;margin-top:4px}
h2{font-size:18px;margin-bottom:16px;color:#1f2329;display:flex;align-items:center;gap:8px}
.tag{display:inline-block;font-size:12px;padding:2px 8px;border-radius:4px;font-weight:600}
.tag.install{background:#e6f7ff;color:#1890ff}
.tag.update{background:#fff7e6;color:#fa8c16}
.form-row{margin-bottom:14px}
.form-row label{display:block;font-size:13px;color:#666;margin-bottom:6px}
.form-row input{width:100%;padding:10px 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px}
.form-row input:focus{outline:none;border-color:#ff4d4f;box-shadow:0 0 0 2px rgba(255,77,79,.12)}
.btn{display:inline-block;width:100%;padding:12px;border:none;border-radius:6px;background:#ff4d4f;color:#fff;font-size:15px;cursor:pointer;transition:.2s}
.btn:hover{background:#e64343}
.btn:disabled{opacity:.5;cursor:not-allowed}
.note{margin-top:16px;font-size:12px;color:#999;line-height:1.8;background:#fafafa;border:1px solid #f0f0f0;border-radius:6px;padding:10px 12px}
.note code{background:#eee;padding:1px 5px;border-radius:3px}
.step{margin-bottom:10px;padding:10px 12px;border-radius:6px;font-size:14px;background:#f7f7f7;line-height:1.7}
.step.ok{background:#f6ffed;color:#389e0d;border:1px solid #b7eb8f}
.step.err{background:#fff2f0;color:#cf1322;border:1px solid #ffa39e}
.step .ts{color:#999;font-size:12px;margin-right:6px}
.done{text-align:center;margin-top:8px}
.done a{color:#ff4d4f;font-weight:600;text-decoration:none}
.spin{display:inline-block;width:14px;height:14px;border:2px solid #ff4d4f;border-top-color:transparent;border-radius:50%;animation:sp .8s linear infinite;vertical-align:-2px;margin-right:6px}
@keyframes sp{to{transform:rotate(360deg)}}
</style></head><body>';

$htmlFoot = '</body></html>';

// ===== 分发 =====
if ($action === '') {
    // 初始页：动态查询接口获取最新版本与下载源
    $updateInfo = fetchUpdateInfo($CONFIG);
    $remoteVer  = !empty($updateInfo['version']) ? $updateInfo['version'] : $CONFIG['version'];

    echo $htmlHead;
    echo '<div class="card">';
    echo '<div class="logo"><div class="logo-name">ZhiCms</div><div class="logo-sub">在线安装 / 升级助手</div></div>';
    echo '<h2>'.($isInstall ? '<span class="tag install">全新安装</span>' : '<span class="tag update">在线升级</span>').' 最新版本：'.$remoteVer.'</h2>';

    if ($isInstall) {
        echo '<form method="post" action="install.php"><input type="hidden" name="action" value="install">';
        echo '<button type="submit" class="btn">开始安装</button>';
        echo '</form>';
        $src = !empty($updateInfo['full_zhicms']) ? '最新完整安装包已就绪' : '连接更新服务获取安装包';
        echo '<div class="note">检测到站点尚未安装。点击后将自动下载完整程序包并解压，完成后进入安装向导填写数据库信息即可完成安装。<br>'.$src.'</div>';
    } else {
        echo '<form method="post" action="install.php"><input type="hidden" name="action" value="update">';
        echo '<div class="form-row"><label>升级密钥（防止被他人远程覆盖）</label><input type="text" name="secret" placeholder="请输入升级密钥" autocomplete="off"></div>';
        echo '<button type="submit" class="btn">开始升级</button>';
        echo '</form>';
        $src = !empty($updateInfo['full_update']) ? '最新更新包已就绪' : '连接更新服务获取更新包';
        echo '<div class="note">升级将下载最新程序并覆盖（自动保护数据库配置与站点设置），随后执行数据库更新。<br>'.$src.'<br>若不清楚密钥，请先删除本目录下 <code>install.php</code>，改为使用系统后台「在线升级」功能。</div>';
    }
    echo '</div>';
    echo $htmlFoot;
    exit;
}

// ===== 执行（新装）=====
if ($action === 'install' && $isInstall) {
    // 动态获取完整包地址（接口不可用时回退兜底）
    $updateInfo = fetchUpdateInfo($CONFIG);
    $CONFIG['installZipUrl'] = !empty($updateInfo['full_zhicms']) ? $updateInfo['full_zhicms'] : $CONFIG['installZipUrl'];
    runInstall($CONFIG);
    exit;
}

// ===== 执行（升级）=====
if ($action === 'update' && !$isInstall) {
    // 密钥校验
    $expected = verifySecret($lockFile, $secret);
    if (!$expected) {
        echo $htmlHead;
        echo '<div class="card"><h2 style="color:#cf1322">密钥错误</h2>';
        echo '<div class="step err">升级密钥不正确，已拒绝升级，防止站点被远程覆盖。</div>';
        echo '<p class="note">正确密钥请查看服务器 config 目录 install.lock 文件内容，或联系管理员。</p>';
        echo '<div style="margin-top:14px"><a class="btn" href="install.php" style="background:#8c8c8c">返回</a></div></div>';
        echo $htmlFoot;
        exit;
    }
    // 动态获取更新包地址（接口不可用时回退兜底）
    $updateInfo = fetchUpdateInfo($CONFIG);
    $CONFIG['updateZipUrl'] = !empty($updateInfo['full_update']) ? $updateInfo['full_update'] : $CONFIG['updateZipUrl'];
    runUpdate($CONFIG);
    exit;
}

// 兜底
echo $htmlHead;
echo '<div class="card"><h2>无效操作</h2><div class="step err">参数错误或站点安装状态已变化，请刷新页面重试。</div></div>';
echo $htmlFoot;

/* =================================================================
 * 业务函数
 * ================================================================= */

/**
 * 动态查询更新服务，获取最新版本与下载地址
 * 接口 update_check.php 返回 JSON，含：
 *   full_zhicms  -> 完整安装包（新装）
 *   full_update  -> 全量更新包（升级）
 *   version      -> 最新版本号
 * 失败时返回空数组（调用方会回退到兜底 URL）
 */
function fetchUpdateInfo($CONFIG) {
    $ret = array();
    $json = downloadFile($CONFIG['checkUrl'], 15);
    if ($json) {
        $data = json_decode($json, true);
        if (is_array($data)) $ret = $data;
    }
    return $ret;
}

/**
 * 校验升级密钥：返回 true/false
 * 规则（按优先级）：
 *   1. 数据库 site 配置的 security_key（后台「网站设置→安全 Key」统一管理）
 *   2. install.lock 内容前 8 位（兼容旧版）
 *   3. secretHint 兜底
 */
function verifySecret($lockFile, $secret) {
    $expected = '';
    // 1) 优先从 DB 读取后台统一安全 Key
    try {
        $dbConfigFile = ZCMS_ROOT . '/data/config/db.php';
        if (file_exists($dbConfigFile)) {
            include $dbConfigFile;
            if (!empty($db['DB']['default'])) {
                $conf = $db['DB']['default'];
                $dsn = "mysql:host={$conf['DB_HOST']};port={$conf['DB_PORT']};dbname={$conf['DB_NAME']};charset={$conf['DB_CHARSET']}";
                $pdo = new PDO($dsn, $conf['DB_USER'], $conf['DB_PWD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $stmt = $pdo->prepare("SELECT `value` FROM `{$conf['DB_PREFIX']}config` WHERE `key` = 'cfg_site' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['value'])) {
                    $cfg = json_decode($row['value'], true);
                    if (is_array($cfg) && !empty($cfg['security_key'])) {
                        $expected = (string)$cfg['security_key'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        $expected = '';
    }
    // 2) 回退：install.lock 内容前 8 位（兼容旧版）
    if ($expected === '' && is_file($lockFile)) {
        $c = @file_get_contents($lockFile);
        if ($c !== false) {
            $expected = trim(substr($c, 0, 8));
        }
    }
    // 3) 兜底
    if ($expected === '') $expected = $GLOBALS['CONFIG']['secretHint'];
    return $secret !== '' && hash_equals($expected, $secret);
}

/**
 * 新装流程：下载完整安装包 -> 解压 -> 覆盖 -> 引导进入安装向导
 */
function runInstall($CONFIG) {
    echo $GLOBALS['htmlHead'];
    echo '<div class="card"><h2><span class="tag install">全新安装</span> 进行中</h2>';

    // 1. 下载
    echo '<div class="step"><span class="ts">[1/3]</span>正在下载完整安装包...</div>';
    flush();
    $content = downloadFile($CONFIG['installZipUrl'], 600);
    if ($content === false || strlen($content) < 1000) {
        echo '<div class="step err">下载失败：'.$CONFIG['installZipUrl'].'<br>请检查网络与地址。</div>';
        echo '</div>'; echo $GLOBALS['htmlFoot']; exit;
    }
    if (!is_dir(dirname($CONFIG['zipFile']))) mkdir(dirname($CONFIG['zipFile']), 0755, true);
    file_put_contents($CONFIG['zipFile'], $content);
    echo '<div class="step ok">✅ 下载完成（'.round(strlen($content)/1024/1024, 2).' MB）</div>';
    flush();

    // 2. 解压
    echo '<div class="step"><span class="ts">[2/3]</span>正在解压并覆盖程序文件...</div>';
    flush();
    if (is_dir($CONFIG['tempDir'])) delDirRecursive($CONFIG['tempDir']);
    mkdir($CONFIG['tempDir'], 0755, true);

    $zip = new ZipArchive();
    if ($zip->open($CONFIG['zipFile']) !== true) {
        echo '<div class="step err">无法打开压缩包。</div>';
        echo '</div>'; echo $GLOBALS['htmlFoot']; exit;
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', $zip->getNameIndex($i));
        if (substr($name, -1) === '/') {
            if (!is_dir($CONFIG['tempDir'] . $name)) mkdir($CONFIG['tempDir'] . $name, 0755, true);
            continue;
        }
        $target = $CONFIG['tempDir'] . $name;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
        $c = $zip->getFromIndex($i);
        if ($c !== false) file_put_contents($target, $c);
    }
    $zip->close();

    // 3. 覆盖（全新安装：直接拷贝全部，保护 db.php / install.lock 不覆盖）
    $sourceRoot = findSourceRoot($CONFIG['tempDir']);
    if (!$sourceRoot) {
        echo '<div class="step err">压缩包结构异常，未找到核心目录。</div>';
        echo '</div>'; echo $GLOBALS['htmlFoot']; exit;
    }
    $count = 0;
    copyFilesSafe($sourceRoot, ZCMS_ROOT, $count);

    // 清理
    @unlink($CONFIG['zipFile']);
    delDirRecursive($CONFIG['tempDir']);
    echo '<div class="step ok">✅ 程序文件部署完成（'.$count.' 个文件）</div>';
    flush();

    // 4. 引导进入安装向导
    echo '<div class="step ok">✅ 程序已就绪，正在进入安装向导...</div>';
    echo '<div class="done"><a href="index.php?r=install">点击进入安装向导 →</a></div>';
    echo '<script>setTimeout(function(){location.href="index.php?r=install";},1500);</script>';
    echo '</div>'; echo $GLOBALS['htmlFoot'];
}

/**
 * 升级流程：下载升级包 -> 解压 -> 智能覆盖 -> 执行SQL -> 更新版本
 */
function runUpdate($CONFIG) {
    echo $GLOBALS['htmlHead'];
    echo '<div class="card"><h2><span class="tag update">在线升级</span> 进行中</h2>';

    // 1. 下载
    echo '<div class="step"><span class="ts">[1/4]</span>正在下载升级包...</div>';
    flush();
    $content = downloadFile($CONFIG['updateZipUrl'], 600);
    if ($content === false || strlen($content) < 1000) {
        echo '<div class="step err">下载失败：'.$CONFIG['updateZipUrl'].'</div>';
        echo '</div>'; echo $GLOBALS['htmlFoot']; exit;
    }
    if (!is_dir(dirname($CONFIG['zipFile']))) mkdir(dirname($CONFIG['zipFile']), 0755, true);
    file_put_contents($CONFIG['zipFile'], $content);
    echo '<div class="step ok">✅ 下载完成（'.round(strlen($content)/1024/1024, 2).' MB）</div>';
    flush();

    // 2. 解压
    echo '<div class="step"><span class="ts">[2/4]</span>正在解压...</div>';
    flush();
    if (is_dir($CONFIG['tempDir'])) delDirRecursive($CONFIG['tempDir']);
    mkdir($CONFIG['tempDir'], 0755, true);
    $zip = new ZipArchive();
    if ($zip->open($CONFIG['zipFile']) !== true) {
        echo '<div class="step err">无法打开压缩包。</div>';
        echo '</div>'; echo $GLOBALS['htmlFoot']; exit;
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', $zip->getNameIndex($i));
        if (substr($name, -1) === '/') {
            if (!is_dir($CONFIG['tempDir'] . $name)) mkdir($CONFIG['tempDir'] . $name, 0755, true);
            continue;
        }
        $target = $CONFIG['tempDir'] . $name;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
        $c = $zip->getFromIndex($i);
        if ($c !== false) file_put_contents($target, $c);
    }
    $zip->close();
    unlink($CONFIG['zipFile']);
    echo '<div class="step ok">✅ 解压完成</div>';
    flush();

    // 3. 智能覆盖（保护 db.php、合并配置）
    echo '<div class="step"><span class="ts">[3/4]</span>正在覆盖系统文件（保护你的配置）...</div>';
    flush();
    $sourceRoot = findSourceRoot($CONFIG['tempDir']);
    if (!$sourceRoot) {
        echo '<div class="step err">压缩包结构异常，未找到核心目录。</div>';
        echo '</div>'; echo $GLOBALS['htmlFoot']; exit;
    }
    $count = 0;
    copyFilesSafe($sourceRoot, ZCMS_ROOT, $count);
    echo '<div class="step ok">✅ 文件更新完成（'.$count.' 个文件）</div>';
    flush();

    // 4. 执行数据库升级 SQL + 更新版本
    $sqlPath = $CONFIG['tempDir'] . '/' . $CONFIG['updateSqlFile'];
    if (is_file($sqlPath)) {
        echo '<div class="step"><span class="ts">[4/4]</span>正在执行数据库更新...</div>';
        flush();
        try {
            executeDatabaseUpdate($sqlPath);
            echo '<div class="step ok">✅ 数据库脚本执行成功</div>';
        } catch (Exception $e) {
            echo '<div class="step err">数据库更新失败：'.$e->getMessage().'</div>';
        }
    } else {
        echo '<div class="step"><span class="ts">[4/4]</span>升级包未含 SQL 脚本，跳过</div>';
    }
    updateVersionConfig($CONFIG['version']);

    // 清理临时
    delDirRecursive($CONFIG['tempDir']);

    // 清除文件校对基线，避免误报篡改
    $manifest = ZCMS_ROOT . '/data/filecheck/manifest.json';
    if (is_file($manifest)) @unlink($manifest);

    echo '<div class="step ok">✅ 版本标识已更新为 '.$CONFIG['version'].'</div>';
    echo '<div class="done"><a href="index.php">回到网站首页 →</a></div>';
    echo '</div>'; echo $GLOBALS['htmlFoot'];
}

/* =================================================================
 * 辅助函数（与 5.0.0_5.0.2_update.php 保持一致）
 * ================================================================= */

function downloadFile($url, $timeout = 600) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZhiCmsInstaller');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($data !== false && $code >= 200 && $code < 300 && strlen($data) > 0) return $data;
        error_log("ZhiCms install download(curl) failed: code=$code err=$err");
    }
    $ctx = stream_context_create([
        'http'  => ['timeout' => $timeout, 'user_agent' => 'ZhiCmsInstaller', 'follow_location' => true],
        'https' => ['timeout' => $timeout, 'user_agent' => 'ZhiCmsInstaller', 'follow_location' => true,
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return $data === false ? false : $data;
}

function delDirRecursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delDirRecursive($path) : @unlink($path);
    }
    @rmdir($dir);
}

function findSourceRoot($dir) {
    if (is_dir($dir . '/app') || is_dir($dir . '/application')) return $dir;
    $items = @scandir($dir);
    if (!$items) return null;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $subDir = $dir . '/' . $item;
        if (is_dir($subDir) && (is_dir($subDir . '/app') || is_dir($subDir . '/application'))) return $subDir;
    }
    return null;
}

function isConfigFile($rel) {
    $configFiles = array(
        'data/config/db.php',
        'data/config/siteconfig.php',
        'data/config/seo.php',
        'data/config/sms.php',
        'data/config/apiset.php',
        'data/config/rule.php',
        'data/config/global.php',
    );
    return in_array($rel, $configFiles, true);
}

function mergeConfigFile($source, $dest) {
    if (!is_file($dest)) {
        if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0755, true);
        copy($source, $dest);
        return;
    }
    $content = file_get_contents($dest);
    if (!preg_match('/\$(\w+)\s*=\s*array\(/', $content, $m)) { copy($source, $dest); return; }
    $varName = $m[1];
    include $dest;
    $oldConfig = isset($$varName) ? $$varName : array();
    include $source;
    $newConfig = isset($$varName) ? $$varName : array();

    if (basename($dest) === 'rule.php') {
        $merged = $newConfig;
        if (isset($oldConfig['REWRITE_ON'])) $merged['REWRITE_ON'] = $oldConfig['REWRITE_ON'];
        if (isset($oldConfig['REWRITE_RULE']) && is_array($oldConfig['REWRITE_RULE'])) {
            $userRules = $oldConfig['REWRITE_RULE'];
            $newRules = isset($newConfig['REWRITE_RULE']) && is_array($newConfig['REWRITE_RULE']) ? $newConfig['REWRITE_RULE'] : array();
            $merged['REWRITE_RULE'] = $newRules;
            foreach ($userRules as $k => $v) $merged['REWRITE_RULE'][$k] = $v;
            foreach ($oldConfig as $k => $v) {
                if ($k !== 'REWRITE_ON' && $k !== 'REWRITE_RULE' && !isset($merged[$k])) $merged[$k] = $v;
            }
        } else {
            foreach ($oldConfig as $k => $v) {
                if ($k !== 'REWRITE_ON' && $k !== 'REWRITE_RULE' && !isset($merged[$k])) $merged[$k] = $v;
            }
        }
    } else {
        $merged = array_merge($newConfig, $oldConfig);
    }
    $content = "<?php\n\${$varName}=" . var_export($merged, true) . ";\n";
    @file_put_contents($dest, $content);
}

function copyFilesSafe($src, $dst, &$count = 0) {
    $dh = @opendir($src);
    if (!$dh) return $count;
    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        $rel = str_replace('\\', '/', substr($srcPath, strlen($src) + 1));
        if (is_dir($srcPath)) {
            if (!is_dir($dstPath)) @mkdir($dstPath, 0755, true);
            copyFilesSafe($srcPath, $dstPath, $count);
        } else {
            if (!is_dir(dirname($dstPath))) @mkdir(dirname($dstPath), 0755, true);
            if (isConfigFile($rel) && $file === 'db.php') continue;   // 保护数据库配置
            if (isConfigFile($rel)) {
                mergeConfigFile($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
            $count++;
        }
    }
    closedir($dh);
    return $count;
}

function executeDatabaseUpdate($sqlFile) {
    $dbConfigFile = ZCMS_ROOT . '/data/config/db.php';
    if (!file_exists($dbConfigFile)) throw new Exception("DB config missing");
    include $dbConfigFile;
    $conf = $db['DB']['default'];
    $dsn = "mysql:host={$conf['DB_HOST']};port={$conf['DB_PORT']};dbname={$conf['DB_NAME']};charset={$conf['DB_CHARSET']}";
    $pdo = new PDO($dsn, $conf['DB_USER'], $conf['DB_PWD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sqlContent = file_get_contents($sqlFile);
    // 表前缀归一化：建表 SQL 里硬编码的 yun_ 前缀必须替换为站点真实前缀（DB_PREFIX），
    // 否则自定义前缀安装后，业务层 realTable() 把 yun_xxx 转成 真实前缀_xxx，
    // 但表实际仍叫 yun_xxx，导致全站查询失败（数据丢失级）。默认 yun_ 时此处等价无操作。
    $realPrefix = isset($conf['DB_PREFIX']) ? $conf['DB_PREFIX'] : 'yun_';
    if ($realPrefix !== 'yun_') {
        $sqlContent = preg_replace('/\byun_([a-zA-Z0-9_]+)\b/', $realPrefix . '$1', $sqlContent);
    }
    // 兼容升级 SQL 中的 {pre} / __PREFIX__ 占位符
    $sqlContent = str_replace(array('{pre}', '__PREFIX__'), $realPrefix, $sqlContent);
    $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
    $sqlContent = preg_replace('/\/\*[\s\S]*?\*\//', '', $sqlContent);
    $statements = array_filter(explode(';', $sqlContent), function($v) { return trim($v) !== ''; });

    $skippable = array('42S21', '42S11', '1060', '1061');
    $skipped = 0;
    foreach ($statements as $stmt) {
        try { $pdo->exec($stmt); }
        catch (Exception $e) {
            $code = (string)$e->getCode();
            $msg  = (string)$e->getMessage();
            $isDup = in_array($code, $skippable, true)
                || stripos($msg, 'Duplicate column') !== false
                || stripos($msg, 'Duplicate key') !== false
                || stripos($msg, 'already exists') !== false;
            if ($isDup) { $skipped++; continue; }
            throw $e;
        }
    }
    if ($skipped > 0) echo '<div class="step">ℹ️ 已跳过 '.$skipped.' 条“字段/索引已存在”语句（正常）</div>';

    // 五.3 前缀连通性自检：建表完成后，用真实前缀查询 config 表首行，
    // 确认「建表前缀」与「业务层 realTable() 前缀」一致，避免安装后全站 500。
    $checkTable = $realPrefix . 'config';
    $chk = $pdo->query("SELECT 1 FROM `{$checkTable}` LIMIT 1");
    if ($chk === false) {
        throw new Exception("表前缀自检失败：无法访问 `{$checkTable}`，请检查安装时填写的表前缀与建表语句是否一致");
    }
}

function updateVersionConfig($version) {
    $verFile = ZCMS_ROOT . '/data/config/version.php';
    $content = "<?php\n\$v='{$version}';\n";
    @file_put_contents($verFile, $content);

    try {
        $dbConfigFile = ZCMS_ROOT . '/data/config/db.php';
        if (!file_exists($dbConfigFile)) return;
        include $dbConfigFile;
        $conf = $db['DB']['default'];
        $dsn = "mysql:host={$conf['DB_HOST']};port={$conf['DB_PORT']};dbname={$conf['DB_NAME']};charset={$conf['DB_CHARSET']}";
        $pdo = new PDO($dsn, $conf['DB_USER'], $conf['DB_PWD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $jsonValue = json_encode(['version' => $version], JSON_UNESCAPED_UNICODE);
        $cfgTable = isset($conf['DB_PREFIX']) ? $conf['DB_PREFIX'] . 'config' : 'yun_config';
        $upsertSql = "INSERT INTO `{$cfgTable}` (`key`, `value`, `desc`) VALUES ('cfg_version', :val, '版本号') "
            . "ON DUPLICATE KEY UPDATE `value` = :val2";
        $stmt = $pdo->prepare($upsertSql);
        $stmt->execute([':val' => $jsonValue, ':val2' => $jsonValue]);
    } catch (Exception $e) {
        error_log("ZhiCms install version update failed: " . $e->getMessage());
    }
}
