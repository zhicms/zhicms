<?php

namespace app\manage\controller;

use app\base\controller\ManageControllerTrait;

class IndexController extends \ZhiCms\base\Controller {

    use ManageControllerTrait;

    // 升级/安装接口地址（与 install.php 一致）
    const UPDATE_URL  = 'https://www.zhi.red/update_check.php';
    const KEY         = 'yun_zhicms_2024';

    public function index() {
        $this->checkManageSession();
		$this->pageText = array("	ZhiCms", "控制台");
        $manage = obj("api/ApiData")->thisQuery("SELECT * FROM `yun_manage` ORDER BY `id` ASC");

        if ($manage === null || $manage === false) {
            $manage = array();
        }

        $articleCount = 0;
        $forumCount   = 0;
        $userCount    = 0;
        $itemCount    = 0;

        try {
            $row = obj("api/ApiData")->thisQuery("SELECT COUNT(*) AS c FROM `yun_article`");
            $articleCount = isset($row[0]['c']) ? (int)$row[0]['c'] : 0;
        } catch (\Exception $e) {}

        try {
            $row = obj("api/ApiData")->thisQuery("SELECT COUNT(*) AS c FROM `yun_forum`");
            $forumCount = isset($row[0]['c']) ? (int)$row[0]['c'] : 0;
        } catch (\Exception $e) {}

        try {
            $row = obj("api/ApiData")->thisQuery("SELECT COUNT(*) AS c FROM `yun_user`");
            $userCount = isset($row[0]['c']) ? (int)$row[0]['c'] : 0;
        } catch (\Exception $e) {}

        try {
            $row = obj("api/ApiData")->thisQuery("SELECT COUNT(*) AS c FROM `yun_items`");
            $itemCount = isset($row[0]['c']) ? (int)$row[0]['c'] : 0;
        } catch (\Exception $e) {}

        // 本地版本号 + 已应用补丁 md5 列表
        $localVersion = \app\common\ConfigStore::load('version', 'version');
        if (empty($localVersion)) {
            $localVersion = defined('VERSION') ? VERSION : '未知';
        }
        $appliedPatches = \app\common\ConfigStore::load('version', 'applied_patches');
        if (!is_array($appliedPatches)) {
            $appliedPatches = array();
        }

        $serverVersion = '';
        $patchMd5      = '';
        $vinfo         = '';
        $updateTime    = '';
        $updateInfo    = '';
        $vlist         = array();
        $vlistHtml     = '';
        $getup         = 0;
        $isPatch       = 0; // 1 表示同版本号下的补丁更新

        try {
            $updateUrl = self::UPDATE_URL . '?ver=' . rawurlencode($localVersion);
            $json = \ZhiCms\ext\Http::doGet($updateUrl, 8);
            if (!empty($json)) {
                $ret = json_decode($json, true);
                if (is_array($ret)) {
                    if (!empty($ret['version'])) {
                        $serverVersion = $ret['version'];
                    }
                    // 补丁包 md5（服务器返回，用于识别是否已应用过该补丁）
                    if (!empty($ret['full_md5'])) {
                        $patchMd5 = $ret['full_md5'];
                    } elseif (!empty($ret['patch_md5'])) {
                        $patchMd5 = $ret['patch_md5'];
                    }
                    if (!empty($ret['vinfo'])) {
                        $vinfo = $ret['vinfo'];
                    }
                    if (!empty($ret['time'])) {
                        $updateTime = $ret['time'];
                    }
                    if (!empty($ret['update_content'])) {
                        $updateInfo = $ret['update_content'];
                    }
                    // 更新明细列表：接口返回 vlist
                    // - 若本身就是含 <li> 的 HTML 片段，则原样保留（前端直接输出，li 分行）
                    // - 若是 JSON 数组 / 换行分隔字符串，则解析为数组（前端逐行包 <li>）
                    $vlist = array();
                    $vlistHtml = '';
                    if (isset($ret['vlist']) && $ret['vlist'] !== '') {
                        $raw = $ret['vlist'];
                        if (is_array($raw)) {
                            $vlist = $raw;
                        } elseif (stripos($raw, '<li') !== false) {
                            // 已经是 <li> 形式的 HTML，原样输出
                            $vlistHtml = $raw;
                        } else {
                            $dec = json_decode($raw, true);
                            if (is_array($dec)) {
                                $vlist = $dec;
                            } else {
                                // 按换行/分号拆分为多行
                                $tmp = preg_split('/[\r\n;]+/', $raw);
                                foreach ($tmp as $t) {
                                    $t = trim($t);
                                    if ($t !== '') $vlist[] = $t;
                                }
                            }
                        }
                    }
                    // getup=1 表示建议更新（接口决定：版本更高 或 有未应用补丁）
                    $getup = (!empty($ret['getup']) && $ret['getup'] == 1) ? 1 : 0;
                }
            }
        } catch (\Exception $e) {
            $updateInfo = '升级信息获取失败：' . $e->getMessage();
        }

        // 是否可升级（getup=1）：可升级时再判定升级 / 补丁 文案
        // 版本号变动（服务器 version != 本地）→ 立即升级；版本号不变 → 打补丁
        $canUpgrade  = ($getup == 1) ? 1 : 0;
        if ($canUpgrade && !empty($serverVersion) && $serverVersion == $localVersion) {
            $isPatch = 1;
        }
        // 若该补丁 md5 已应用过，则不提示升级（避免重复打同一补丁）
        if ($canUpgrade && !empty($patchMd5) && in_array($patchMd5, $appliedPatches, true)) {
            $canUpgrade = 0;
        }

        // 是否已安装（无 install.lock 视为未安装，需走安装流程）
        $installed = is_file(dirname(__DIR__, 3) . '/data/config/install.lock');

        // 系统信息：本地版本（即系统版本，不重复展示）；后接更新标题/更新时间/更新内容。
        // getup=1 时展示升级按钮（版本变动=升级，版本不变=补丁）。后台除"安装"外只有"升级"。
        $this->assign(array(
            'manage'       => $manage,
            'articleCount' => $articleCount,
            'forumCount'   => $forumCount,
            'userCount'    => $userCount,
            'itemCount'    => $itemCount,
            'localVersion' => $localVersion,
            'serverVersion'=> $serverVersion,
            'patchMd5'     => $patchMd5,
            'vinfo'        => $vinfo,
            'updateTime'   => $updateTime,
            'isPatch'      => $isPatch,
            'updateInfo'   => $updateInfo,
            'vlist'        => $vlist,
            'vlistHtml'    => $vlistHtml,
            'getup'        => $getup,
            'canUpgrade'   => $canUpgrade,
            'installed'    => $installed,
        ));

        return $this->display();
    }

    /**
     * 记录已应用补丁：把补丁 md5 追加到本地 applied_patches，并更新版本号
     */
    private function recordAppliedPatch($patchMd5, $newVersion) {
        $applied = \app\common\ConfigStore::load('version', 'applied_patches');
        if (!is_array($applied)) {
            $applied = array();
        }
        if (!empty($patchMd5) && !in_array($patchMd5, $applied, true)) {
            $applied[] = $patchMd5;
        }
        $data = array('applied_patches' => $applied);
        if (!empty($newVersion)) {
            $data['version'] = $newVersion;
        }
        \app\common\ConfigStore::save('version', $data);
    }

    /**
     * 升级 / 安装 —— 整合 install.php 单文件逻辑
     * getup 触发：mode=upgrade（升级，下载 full_update 增量包并安全覆盖）
     * 未安装触发：mode=install（安装，下载 full_zhicms 整包 + SQL + 版本）
     */
    public function downloadFile() {
        $trace = function($msg) {
            if (!getenv('ZHI_DEBUG')) return;
            @file_put_contents(dirname(__DIR__, 3) . '/runtime/update_trace.log',
                date('Y-m-d H:i:s') . ' | ' . $msg . "\n", FILE_APPEND);
        };

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Type: application/json; charset=utf-8');
        while (ob_get_level()) ob_end_clean();

        try {
            $this->checkManageSession();

            $mode = $this->arg('mode', 'upgrade'); // upgrade | install

            // 本地版本号
            $localVersion = \app\common\ConfigStore::load('version', 'version');
            if (empty($localVersion)) {
                $localVersion = defined('VERSION') ? VERSION : '';
            }

            // 1) 向升级接口请求包地址（升级=update，安装=install）
            $type = ($mode === 'install') ? 'install' : 'update';
            $updateUrl = self::UPDATE_URL . '?ver=' . rawurlencode($localVersion) . '&type=' . $type;
            $json = \ZhiCms\ext\Http::doGet($updateUrl, 8);
            $trace('check ' . $type . ' json=' . mb_substr($json, 0, 200));
            if (empty($json)) {
                exit(json_encode(array('info' => '未获取到升级服务器响应', 'status' => 'n')));
            }
            $ret = json_decode($json, true);
            if (!is_array($ret)) {
                exit(json_encode(array('info' => '升级返回数据异常', 'status' => 'n')));
            }

            $key  = $ret['key']  ?? '';
            $zipUrl = ($type === 'install') ? ($ret['full_zhicms'] ?? '') : ($ret['full_update'] ?? '');
            $patchMd5 = $ret['full_md5'] ?? ($ret['patch_md5'] ?? '');
            if (empty($zipUrl)) {
                exit(json_encode(array('info' => '未获取到升级包地址', 'status' => 'n')));
            }

            // 2) 下载升级包
            $tmp = $this->downloadToTemp($zipUrl);
            if (!$tmp) {
                exit(json_encode(array('info' => '升级包下载失败', 'status' => 'n')));
            }
            $trace('downloaded zip=' . $tmp . ' size=' . filesize($tmp));

            // 2.1) 校验包 md5（服务器返回 full_md5），防止下载损坏/被篡改
            if (!empty($patchMd5)) {
                $localMd5 = md5_file($tmp);
                if ($localMd5 !== strtolower($patchMd5)) {
                    @unlink($tmp);
                    $trace('md5 mismatch local=' . $localMd5 . ' server=' . $patchMd5);
                    exit(json_encode(array('info' => '升级包校验失败（md5 不一致，文件可能已损坏或被篡改）', 'status' => 'n')));
                }
                $trace('md5 ok=' . $localMd5);
            }

            // 3) 校验解压密码（与 install.php 一致：KEY + key）
            $password = self::KEY . $key;
            $dir = $this->extractZip($tmp, $password);
            if (!$dir) {
                @unlink($tmp);
                exit(json_encode(array('info' => '升级包解压失败（密钥错误或被篡改）', 'status' => 'n')));
            }
            $trace('extracted to=' . $dir);

            // 4) 安全覆盖（升级只覆盖可覆盖文件；安装覆盖整包）
            $this->copyFilesSafe($dir);
            $trace('files copied');

            // 5) 执行 SQL（包内 zhicms_update.sql / zhicms.sql）
            $sqlFile = $type === 'install'
                ? $dir . '/zhicms.sql'
                : $dir . '/zhicms_update.sql';
            $sqlErrors = array();
            if (is_file($sqlFile)) {
                $sqlResult = $this->execSqlFile($sqlFile);
                $sqlErrors = $sqlResult['errors'];
                $trace('sql executed: ' . basename($sqlFile) . ' ok=' . $sqlResult['ok'] . ' err=' . count($sqlErrors));
                foreach ($sqlErrors as $se) {
                    $trace('SQL ERROR: ' . $se);
                }
            }

            // 6) 记录版本号 + 已应用补丁 md5（同版本号补丁也能识别"点过没点过"）
            $newVersion = $ret['version'] ?? '';
            $this->recordAppliedPatch($patchMd5, $newVersion);
            $trace('recorded patch=' . $patchMd5 . ' version=' . $newVersion);

            // 7) 清理
            @unlink($tmp);
            $this->delDir($dir);

            // 数据库脚本有失败时必须如实告知，不能"假成功"
            if (!empty($sqlErrors)) {
                exit(json_encode(array(
                    'info'   => ($type === 'install' ? '安装' : '升级') . '已完成文件更新，但有 ' . count($sqlErrors)
                                . ' 条数据库语句执行失败，请检查数据库权限或手动导入 SQL。首条错误：' . $sqlErrors[0],
                    'status' => 'n',
                    'sql_errors' => array_slice($sqlErrors, 0, 10),
                    'version' => $newVersion,
                ), JSON_UNESCAPED_UNICODE));
            }

            exit(json_encode(array(
                'info'   => ($type === 'install' ? '安装' : '升级') . '完成，当前版本：' . $newVersion,
                'status' => 'y',
            ), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $trace('EXCEPTION ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            exit(json_encode(array('info' => '升级失败：' . $e->getMessage(), 'status' => 'n')));
        }
    }

    /**
     * 下载远程 zip 到临时文件
     */
    private function downloadToTemp($url) {
        $tmp = tempnam(sys_get_temp_dir(), 'zc_up_') . '.zip';
        $ch = curl_init($url);
        $fp = fopen($tmp, 'wb');
        curl_setopt_array($ch, array(
            CURLOPT_FILE           => $fp,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_USERAGENT      => 'ZhiCms-Updater',
        ));
        // 启用 TLS 证书校验（仅在环境无 CA 证书包时降级），避免中间人篡改升级包
        foreach (\ZhiCms\ext\Http::sslOpts() as $k => $v) {
            curl_setopt($ch, $k, $v);
        }
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $code != 200 || !is_file($tmp) || filesize($tmp) < 10) {
            @unlink($tmp);
            return false;
        }
        return $tmp;
    }

    /**
     * 用密码解压 zip（与 install.php 一致：ZipArchive + 原生加密）
     */
    private function extractZip($zipPath, $password) {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        $dest = tempnam(sys_get_temp_dir(), 'zc_ex_');
        @unlink($dest);
        mkdir($dest, 0777, true);
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }
        // 先尝试带密码
        if (method_exists($zip, 'setPassword')) {
            $zip->setPassword($password);
        }
        $ok = @$zip->extractTo($dest);
        // 部分系统 setPassword 不生效，再逐文件解压
        if (!$ok) {
            $ok = true;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($zip->extractTo($dest, $name) === false) {
                    $ok = false;
                    break;
                }
            }
        }
        $zip->close();
        if (!$ok) {
            $this->delDir($dest);
            return false;
        }
        return rtrim($dest, '/\\');
    }

    /**
     * 安全覆盖文件（整合 install.php copyFilesSafe）：
     * 保护用户配置文件不被覆盖（db.php/rule.php/siteconfig.php 等）。
     */
    private function copyFilesSafe($srcDir) {
        $protect = array(
            'data/config/db.php',
            'data/config/rule.php',
            'data/config/siteconfig.php',
            'data/config/seo.php',
            'data/config/sms.php',
            'data/config/apiset.php',
            'data/config/global.php',
            'data/config/install.lock',
            'data/config/version.php',
        );
        $root = dirname(__DIR__, 3);
        $this->copyDir($srcDir, $root, $protect);
    }

    private function copyDir($src, $dst, $protect = array()) {
        $dir = opendir($src);
        if ($dir === false) return;
        if (!is_dir($dst)) mkdir($dst, 0777, true);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            // 相对路径用于保护判断
            $rel = ltrim(preg_replace('#^' . preg_quote(dirname(__DIR__, 3), '#') . '#', '', $dstPath), '/\\');
            $rel = str_replace('\\', '/', $rel);
            if (is_dir($srcPath)) {
                $this->copyDir($srcPath, $dstPath, $protect);
            } else {
                if (in_array($rel, $protect, true)) {
                    continue; // 跳过用户配置文件
                }
                @copy($srcPath, $dstPath);
                @chmod($dstPath, 0644);
            }
        }
        closedir($dir);
    }

    /**
     * 执行 SQL 文件（与 install.php 一致：按 ; 拆分，跳过注释/空行）
     */
    private function execSqlFile($file) {
        $errors = array();
        $okCount = 0;
        $sql = @file_get_contents($file);
        if ($sql === false) {
            return array('ok' => 0, 'errors' => array('无法读取升级SQL文件: ' . basename($file)));
        }

        // 关键：把 SQL 中的表前缀统一为当前站点真实前缀
        // 升级包 SQL 里既可能写 __PREFIX__ 占位符，也可能历史遗留硬编码 yun_
        $prefix = $this->currentDbPrefix();
        $sql = str_replace('__PREFIX__', $prefix, $sql);
        $sql = str_replace('{pre}', $prefix, $sql);
        if ($prefix !== 'yun_') {
            $sql = preg_replace('/\byun_([a-zA-Z0-9_]+)\b/', $prefix . '$1', $sql);
        }

        $sql = str_replace("\r\n", "\n", $sql);
        $lines = explode("\n", $sql);
        $buffer = '';
        $statements = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '--') === 0 || strpos($line, '#') === 0) {
                continue;
            }
            $buffer .= $line . "\n";
            if (substr(rtrim($line), -1) === ';') {
                $stmt = trim($buffer);
                $buffer = '';
                if ($stmt !== '') $statements[] = $stmt;
            }
        }
        if (trim($buffer) !== '') $statements[] = trim($buffer);

        foreach ($statements as $stmt) {
            try {
                obj("api/ApiData")->executeQuery($stmt);
                $okCount++;
            } catch (\Throwable $e) {
                // 「已存在/重复」类错误属于重复执行升级包的正常现象，静默跳过
                if ($this->isIgnorableSqlError($e->getMessage())) {
                    $okCount++;
                    continue;
                }
                // 其余错误必须上报，避免升级"假成功"
                $errors[] = mb_substr(preg_replace('/\s+/', ' ', $stmt), 0, 80) . ' => ' . $e->getMessage();
            }
        }
        return array('ok' => $okCount, 'errors' => $errors);
    }

    /**
     * 读取当前站点真实表前缀
     */
    private function currentDbPrefix() {
        $prefix = \ZhiCms\base\Config::get('DB.default.DB_PREFIX');
        return $prefix ? $prefix : 'yun_';
    }

    /**
     * 判断 SQL 错误是否可忽略（重复执行升级包时的幂等性错误）
     */
    private function isIgnorableSqlError($msg) {
        $ignorable = array(
            'Duplicate column name',
            'Duplicate key name',
            'Duplicate entry',
            'already exists',
            "Can't DROP",
            'check that column/key exists',
            'Multiple primary key defined',
        );
        foreach ($ignorable as $kw) {
            if (stripos($msg, $kw) !== false) return true;
        }
        return false;
    }

    private function delDir($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->delDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
