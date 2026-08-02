<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class FilecheckController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /** 基线清单存储目录（相对站点根） */
    private $baseDir = 'data/filecheck/';
    private $manifestFile = 'data/filecheck/manifest.json';

    /** 参与校验的目录 */
    private $scanDirs = array('app', 'ZhiCms', 'public', 'plugins');
    /** 参与校验的文件扩展名 */
    private $scanExt = array('php', 'html', 'js', 'css');

    /** 官方更新检测接口（固定地址，返回含 gitee 代码仓字段的 JSON） */
    const UPDATE_CHECK_URL = 'https://www.zhi.red/update_check.php';
    /** gitee 代码仓兜底地址（接口不可达时使用） */
    const GITEE_FALLBACK = 'https://gitee.com/dazensun/zhicms';
    /** gitee 原始文件访问分支（可在设置中调整） */
    const DEFAULT_BRANCH = 'master';
    /** 官方完整包压缩包地址（gitee 不可达时作为最终兜底，从中抽取文件） */
    const ZIP_URL = 'https://www.zhi.red/d/zhicms.zip';
    /** 压缩包本地缓存有效期（秒），到期重新下载以维持最新版，默认 24 小时 */
    const ZIP_TTL = 86400;

    /**
     * 受保护的核心/框架文件前缀（不允许被修改或篡改）
     * 这些文件如被改动，必须从代码仓/官方包恢复，不能通过后台编辑
     */
    private $protectedPrefixes = array(
        'ZhiCms/',          // 自写框架核心
        'vendor/',          // 第三方/TP6 等框架依赖
        'app/base/',        // 基础控制器/模型
        'app/api/',         // 接口层
        'app/common/',      // 公共组件
        'public/',          // 前端核心资源（含框架入口）
    );

    /**
     * 扫描目录返回相对路径列表
     */
    private function scanFiles(){
        $root = \ROOT_PATH;
        $files = array();
        foreach ($this->scanDirs as $d) {
            $base = $root . $d;
            if (!is_dir($base)) continue;
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $f) {
                if ($f->isDir()) continue;
                $ext = strtolower($f->getExtension());
                if (!in_array($ext, $this->scanExt)) continue;
                $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
                $files[$rel] = $rel;
            }
        }
        ksort($files);
        return array_values($files);
    }

    /**
     * 计算文件 MD5（大文件采用分块，避免内存溢出）
     */
    private function md5File($path){
        if (filesize($path) > 5 * 1024 * 1024) {
            return md5_file($path);
        }
        return md5_file($path);
    }

    /**
     * 读取基线清单
     */
    private function loadManifest(){
        $path = \ROOT_PATH . $this->manifestFile;
        if (!file_exists($path)) return array();
        $json = json_decode(file_get_contents($path), true);
        return is_array($json) ? $json : array();
    }

    /**
     * 判断文件是否属于受保护的核心/框架文件（不可修改/篡改）
     */
    private function isProtected($rel){
        foreach ($this->protectedPrefixes as $p) {
            if (strpos($rel, $p) === 0) return true;
        }
        return false;
    }

    /**
     * 代码仓分支（固定 master，设置项已移除，保持内部调用兼容）
     */
    private function branch(){
        return self::DEFAULT_BRANCH;
    }

    /**
     * 获取 gitee 代码仓地址：优先从官方接口 update_check.php 的 gitee 字段解析，
     * 接口不可达则使用兜底地址（写死）
     */
    private function giteeUrl(){
        $cfg = \app\common\ConfigStore::load('filecheck');
        if (!empty($cfg['gitee'])) return rtrim($cfg['gitee'], '/');
        $url = self::UPDATE_CHECK_URL;
        $json = \ZhiCms\ext\Http::doGet($url, 8);
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['gitee'])) {
                // 缓存到配置，减少重复请求
                $cfg['gitee'] = $data['gitee'];
                \app\common\ConfigStore::save('filecheck', $cfg);
                return rtrim($data['gitee'], '/');
            }
        }
        return self::GITEE_FALLBACK;
    }

    /**
     * 拼接 gitee 原始文件访问地址（raw）
     */
    private function rawUrl($rel, $branch){
        $base = $this->giteeUrl();
        return $base . '/raw/' . $branch . '/' . ltrim($rel, '/');
    }

    /**
     * 从 gitee 拉取单个文件内容（返回字符串，失败返回 false）
     */
    private function fetchFromRepo($rel, $branch){
        $url = $this->rawUrl($rel, $branch);
        $content = \ZhiCms\ext\Http::doGet($url, 15);
        if ($content === false || $content === '') return false;
        // gitee raw 在文件不存在时返回 HTML 页面，简单以内容长度与 <?php 等特征不可靠，
        // 这里仅判断：若返回内容明显是 gitee 的 404 页面则视为失败
        if (stripos($content, '404 Not Found') !== false || stripos($content, 'Page not found') !== false) return false;
        return $content;
    }

    /**
     * 本地托底代码仓目录（服务器上你上传的官方包副本），默认 data/repo_ref/
     * 当 gitee 不可达/需登录/被限流时作为兜底恢复源
     */
    private function localRefDir(){
        $cfg = \app\common\ConfigStore::load('filecheck');
        $dir = isset($cfg['local_ref']) ? trim($cfg['local_ref']) : '';
        if ($dir === '') $dir = \ROOT_PATH . 'data/repo_ref/';
        $dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';
        return is_dir($dir) ? $dir : '';
    }

    /**
     * 官方压缩包本地缓存路径（data/repo_ref/zhicms.zip）
     */
    private function zipCachePath(){
        return \ROOT_PATH . 'data/repo_ref/zhicms.zip';
    }

    /**
     * 确保本地有最新的官方压缩包：不存在或已过期则重新下载（维持最新版）
     * 返回压缩包路径或 false
     */
    private function ensureZip(){
        $zip = $this->zipCachePath();
        $need = true;
        if (is_file($zip)) {
            // 过期判断：用文件 mtime 与 TTL 比较
            if ((time() - filemtime($zip)) < self::ZIP_TTL) $need = false;
        }
        if ($need) {
            $dir = dirname($zip);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $bin = $this->downloadZip(self::ZIP_URL);
            if ($bin === false) return is_file($zip) ? $zip : false;   // 下载失败但有旧包则用旧包
            file_put_contents($zip, $bin);
        }
        return is_file($zip) ? $zip : false;
    }

    /**
     * 下载压缩包（使用 curl，长超时，支持大文件）
     */
    private function downloadZip($url){
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        $bin = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($bin === false || $code != 200) return false;
        return $bin;
    }

    /**
     * 从官方压缩包中抽取单个文件内容（压缩包内部条目与站点相对路径一致，如 app/xxx.php）
     * 返回内容字符串或 false
     */
    private function fetchFromZip($rel){
        $zip = $this->ensureZip();
        if ($zip === false) return false;
        if (!class_exists('ZipArchive')) return false;
        $z = new ZipArchive;
        if ($z->open($zip) !== true) return false;
        $content = false;
        // 直接匹配，以及常见的根目录前缀（如 zhicms/）
        $candidates = array(ltrim($rel, '/'), 'zhicms/' . ltrim($rel, '/'));
        foreach ($candidates as $name) {
            $s = $z->getFromName($name);
            if ($s !== false) { $content = $s; break; }
        }
        $z->close();
        return $content;
    }

    /**
     * 获取受保护文件内容：优先 gitee → 本地托底目录 → 官方压缩包
     * 返回 array('ok'=>bool, 'content'=>string, 'source'=>string)
     */
    private function fetchFile($rel, $branch){
        $content = $this->fetchFromRepo($rel, $branch);
        if ($content !== false) {
            return array('ok' => true, 'content' => $content, 'source' => 'gitee');
        }
        $local = $this->localRefDir();
        if ($local !== '' && is_file($local . $rel)) {
            $c = file_get_contents($local . $rel);
            if ($c !== false) {
                return array('ok' => true, 'content' => $c, 'source' => 'local');
            }
        }
        $zipContent = $this->fetchFromZip($rel);
        if ($zipContent !== false) {
            return array('ok' => true, 'content' => $zipContent, 'source' => 'zip');
        }
        return array('ok' => false, 'content' => '', 'source' => '');
    }

    /**
     * 恢复来源的中文标签
     */
    private function sourceLabel($src){
        switch ($src) {
            case 'gitee': return '代码仓(gitee)';
            case 'local': return '本地托底目录';
            case 'zip':   return '官方压缩包';
            default:      return $src;
        }
    }

    /**
     * 查询某个文件是否可修改（受保护 + 可写状态）
     * 返回 JSON：{status:'y', protected:bool, writable:bool, editable:bool, restorable:bool}
     */
    public function query(){
        $this->checkManageSession();
        $f = trim($this->arg('file', ''));
        if ($f === '' || strpos($f, '..') !== false) exit(json_encode(array('status' => 'n', 'info' => '参数非法')));
        $protected = $this->isProtected($f);
        $full = \ROOT_PATH . $f;
        $writable = is_file($full) ? is_writable($full) : is_writable(dirname($full));
        $restorable = $protected;   // 受保护文件均可通过代码仓恢复
        exit(json_encode(array(
            'status'     => 'y',
            'protected'  => $protected,
            'writable'   => $writable,
            'editable'   => (!$protected && $writable),
            'restorable' => $restorable,
        )));
    }

    /**
     * 从代码仓/本地托底/官方压缩包远程下载并恢复单个文件（在线升级单文件）
     * 适用于受保护核心文件与被改动过的普通文件，用官方最新版本覆盖本地
     */
    public function restore(){
        $this->checkManageSession();
        $f = trim($this->arg('file', ''));
        if ($f === '' || strpos($f, '..') !== false) exit(json_encode(array('status' => 'n', 'info' => '参数非法')));
        if (!is_file(\ROOT_PATH . $f) && !$this->isProtected($f)) {
            // 普通文件且本地不存在：仍允许尝试从官方源拉取（等于补回官方文件）
        }
        $branch = $this->branch();
        $res = $this->fetchFile($f, $branch);
        if (!$res['ok']) exit(json_encode(array('status' => 'n', 'info' => '远程下载失败：gitee、本地托底目录、官方压缩包均无可用的该文件（请检查网络或在“本地托底目录”上传官方副本）：' . $f)));
        $dst = \ROOT_PATH . $f;
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
        if (file_put_contents($dst, $res['content']) === false) exit(json_encode(array('status' => 'n', 'info' => '写入失败，请检查目录权限：' . $f)));
        \ZhiCms\ext\AdminLog::write('filecheck', '在线升级(远程下载恢复)了文件：' . $f . ' [来源:' . $res['source'] . ']');
        exit(json_encode(array('status' => 'y', 'info' => '已从' . $this->sourceLabel($res['source']) . '远程下载并覆盖：' . $f)));
    }

    /**
     * 在线升级文件系统：遍历基线，将“与官方基线不一致”（被改动/缺失）的【受保护核心文件】，
     * 从 gitee（优先）→ 本地托底目录 → 官方压缩包 重新拉取官方最新版本覆盖。
     * 与“在线升级系统”互补：系统升级升级程序版本，这里把被改乱/缺失的核心文件还原为官方最新版。
     * 注意：仅处理受保护核心文件（ZhiCms/vendor/app/base/app/api/app/common/public 等），
     * 普通可修改文件（如后台视图定制的改动）不会被强制覆盖，避免冲掉用户已有的正常修改。
     */
    public function pull(){
        $this->checkManageSession();
        $manifest = $this->loadManifest();
        if (empty($manifest)) exit(json_encode(array('status' => 'n', 'info' => '请先建立基线')));
        $branch = $this->branch();
        $restored = 0;
        $failed = array();
        $skipped = 0;   // 非核心文件（用户正常改动），不处理
        $fromGitee = 0;
        $fromLocal = 0;
        $fromZip = 0;
        foreach ($manifest as $rel => $hash) {
            if ($rel === '__time') continue;
            if (!$this->isProtected($rel)) { $skipped++; continue; }   // 非核心文件：保留用户修改，不在线升级
            $current = is_file(\ROOT_PATH . $rel) ? $this->md5File(\ROOT_PATH . $rel) : '';
            if ($current !== '' && $current === $hash) continue;   // 与基线一致则跳过（无需升级）
            $res = $this->fetchFile($rel, $branch);
            if (!$res['ok']) { $failed[] = $rel; continue; }
            $dst = \ROOT_PATH . $rel;
            $dstDir = dirname($dst);
            if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
            if (file_put_contents($dst, $res['content']) !== false) {
                $restored++;
                if ($res['source'] === 'gitee') $fromGitee++;
                elseif ($res['source'] === 'local') $fromLocal++;
                else $fromZip++;
            } else {
                $failed[] = $rel;
            }
        }
        // 增强：除基线内受保护文件外，额外拉取 zip/代码仓中“新增”的受保护核心文件（你上传到代码仓/更新包的新文件），
        // 让“在线升级”也能把新增文件补回本地（仅受保护核心文件，不冲掉普通文件）。
        $added = 0;
        $zip = $this->ensureZip();
        if ($zip !== false && class_exists('ZipArchive')) {
            $z = new ZipArchive;
            if ($z->open($zip) === true) {
                $branch = $this->branch();
                $manifestKeys = $manifest;
                unset($manifestKeys['__time']);
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $name = $z->getNameIndex($i);
                    if ($name === false) continue;
                    $name = ltrim($name, '/');
                    if (strpos($name, 'zhicms/') === 0) $name = substr($name, strlen('zhicms/'));
                    if (substr($name, -1) === '/') continue;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $this->scanExt)) continue;
                    $inScan = false;
                    foreach ($this->scanDirs as $d) { if (strpos($name, $d . '/') === 0) { $inScan = true; break; } }
                    if (!$inScan) continue;
                    if (!$this->isProtected($name)) continue;          // 仅受保护核心文件
                    if (isset($manifestKeys[$name])) continue;          // 基线内已处理过
                    if (is_file(\ROOT_PATH . $name)) continue;          // 本地已存在则跳过
                    $res = $this->fetchFile($name, $branch);
                    if (!$res['ok']) { $failed[] = $name; continue; }
                    $dst = \ROOT_PATH . $name;
                    $dstDir = dirname($dst);
                    if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
                    if (file_put_contents($dst, $res['content']) !== false) {
                        $added++;
                        if ($res['source'] === 'gitee') $fromGitee++;
                        elseif ($res['source'] === 'local') $fromLocal++;
                        else $fromZip++;
                    } else {
                        $failed[] = $name;
                    }
                }
                $z->close();
            }
        }

        \ZhiCms\ext\AdminLog::write('filecheck', '在线升级文件系统，覆盖核心文件 ' . $restored . ' 个（gitee:' . $fromGitee . ' 本地:' . $fromLocal . ' 压缩包:' . $fromZip . '），新增补回 ' . $added . ' 个，跳过普通文件 ' . $skipped . ' 个，失败 ' . count($failed) . ' 个');
        $msg = '在线升级完成：已用官方最新版覆盖 ' . $restored . ' 个核心文件';
        $msg .= '（gitee:' . $fromGitee . ' 本地:' . $fromLocal . ' 压缩包:' . $fromZip . '）';
        if ($skipped > 0) $msg .= '；已跳过 ' . $skipped . ' 个普通文件（保留你的修改）';
        if ($added > 0) $msg .= '；已补回 ' . $added . ' 个新增核心文件（来自代码仓/更新包）';
        if ($failed) $msg .= '；' . count($failed) . ' 个核心文件拉取失败（网络不可达，可重试）：' . implode('、', array_slice($failed, 0, 5));
        // 仅当“有受保护文件需要处理但全部失败”时才判为失败；部分成功/跳过均视为成功
        $status = ($restored === 0 && $failed) ? 'n' : 'y';
        exit(json_encode(array('status' => $status, 'info' => $msg)));
    }

    /**
     * 检测代码仓/更新包是否有新版本或新文件（期望“检测代码仓有更新”场景）
     * 逻辑：
     *  1) 优先解析官方更新包 zip（data/repo_ref/zhicms.zip，ensureZip 自动下载/维持最新）的条目清单；
     *  2) 与本地扫描文件对比，发现“新增文件”（zip 有、本地无，多为你上传到代码仓/更新包的新文件）；
     *  3) 与基线内【受保护】文件对比，发现“可升级文件”（受保护核心文件与 zip 内容 hash 不同）；
     *  4) 软检测 gitee 版本号（官方接口返回），失败不影响主流程；
     *  5) 返回 has_update / new_count / upgrade_count / new_files / upgrade_files / source / remote_version。
     */
    public function detectUpdate(){
        $this->checkManageSession();
        $zip = $this->ensureZip();
        $zipEntries = array();      // zip 内相对站点根的文件清单（已规范化）
        if ($zip !== false && class_exists('ZipArchive')) {
            $z = new ZipArchive;
            if ($z->open($zip) === true) {
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $name = $z->getNameIndex($i);
                    if ($name === false) continue;
                    $name = ltrim($name, '/');
                    // 规范化：zip 内常见根前缀 zhicms/ 去掉
                    if (strpos($name, 'zhicms/') === 0) $name = substr($name, strlen('zhicms/'));
                    if (substr($name, -1) === '/') continue;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $this->scanExt)) continue;
                    $zipEntries[$name] = true;
                }
                $z->close();
            }
        }

        $local = $this->scanFiles();
        $localSet = array_flip($local);

        // 新增文件：zip 有而本地扫描目录无（可能是你上传到代码仓/更新包的新文件）
        $newFiles = array();
        foreach (array_keys($zipEntries) as $rel) {
            // 仅关注我们校验的目录，避免误报
            $inScan = false;
            foreach ($this->scanDirs as $d) {
                if (strpos($rel, $d . '/') === 0) { $inScan = true; break; }
            }
            if (!$inScan) continue;
            if (!isset($localSet[$rel])) $newFiles[] = $rel;
        }

        // 可升级文件：基线内受保护文件与 zip 内容 hash 不同
        $manifest = $this->loadManifest();
        $upgradeFiles = array();
        if (!empty($manifest)) {
            $branch = $this->branch();
            foreach ($manifest as $rel => $hash) {
                if ($rel === '__time') continue;
                if (!isset($zipEntries[$rel])) continue;          // zip 没有该文件，无法判定
                if (!$this->isProtected($rel)) continue;          // 仅核心文件纳入“升级”
                $zipContent = $this->fetchFromZip($rel);
                if ($zipContent === false) continue;
                if (md5($zipContent) !== $hash) $upgradeFiles[] = $rel;
            }
        }

        // 软检测 gitee 版本号（官方接口 update_check.php）
        $remoteVersion = '';
        $json = \ZhiCms\ext\Http::doGet(self::UPDATE_CHECK_URL, 8);
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['version'])) $remoteVersion = $data['version'];
        }

        // 检测 gitee 代码仓“近期动态”（仓库 events 公开接口，无需登录），每次检测都实时拉取最新推送并展示，
        // 不再依赖本地缓存判断“是否已见过”，确保每次点“检测更新”都能看到代码仓最新动态。
        $giteeLatestSha = '';
        $giteeLatestTime = '';
        $giteeLatestMsg = '';
        $giteeEvents = \ZhiCms\ext\Http::doGet('https://gitee.com/api/v5/repos/dazensun/zhicms/events', 10);
        if ($giteeEvents) {
            $ev = json_decode($giteeEvents, true);
            if (is_array($ev)) {
                foreach ($ev as $e) {
                    if (isset($e['type']) && $e['type'] === 'PushEvent' && !empty($e['payload']['commits'][0]['sha'])) {
                        $giteeLatestSha = $e['payload']['commits'][0]['sha'];
                        $giteeLatestTime = isset($e['created_at']) ? $e['created_at'] : '';
                        $giteeLatestMsg = isset($e['payload']['commits'][0]['message']) ? $e['payload']['commits'][0]['message'] : '';
                        break;   // 第一条即最新
                    }
                }
            }
        }
        // 每次都如实展示最新动态（gitee_online 表示代码仓在线并可获取动态）
        $giteeOnline = ($giteeLatestSha !== '');
        $giteeTip = $giteeOnline
            ? ('代码仓最新推送（' . ($giteeLatestTime ?: '刚刚') . ($giteeLatestMsg ? '：' . $giteeLatestMsg : '') . '）')
            : '';

        // 注意：gitee 动态仅作“展示”，真正的可升级判定仍基于更新包的文件差异（new_files / upgrade_files），
        // 避免每次都因“代码仓有动态”而误报“有更新”。
        $hasUpdate = (!empty($newFiles) || !empty($upgradeFiles));
        $source = $zip !== false ? 'zip' : 'none';
        $msg = $hasUpdate
            ? ('检测到更新：更新包中有 ' . count($newFiles) . ' 个新增文件、' . count($upgradeFiles) . ' 个核心文件可升级' . ($giteeTip ? '；' . $giteeTip : ''))
            : '未检测到新版本或新文件（本地与更新包一致）' . ($giteeTip ? '；' . $giteeTip : '');

        exit(json_encode(array(
            'status'          => 'y',
            'has_update'      => $hasUpdate,
            'new_count'       => count($newFiles),
            'upgrade_count'   => count($upgradeFiles),
            'new_files'       => $newFiles,
            'upgrade_files'   => $upgradeFiles,
            'source'          => $source,
            'remote_version'  => $remoteVersion,
            'gitee_sha'       => $giteeLatestSha,
            'gitee_time'      => $giteeLatestTime,
            'gitee_msg'       => $giteeLatestMsg,
            'gitee_online'    => $giteeOnline,
            'gitee_tip'       => $giteeTip,
            'info'            => $msg,
        )));
    }

    /**
     * 保存设置（本地托底目录）
     */
    public function save(){
        $this->checkManageSession();
        $localRef = trim($this->arg('local_ref', ''));
        if ($localRef !== '') {
            $localRef = rtrim(str_replace('\\', '/', $localRef), '/') . '/';
            // 目录不存在时自动创建（避免“目录不存在”导致保存失败，提升易用性）
            if (!is_dir($localRef)) {
                if (!@mkdir($localRef, 0755, true) && !is_dir($localRef)) {
                    exit(json_encode(array('status' => 'n', 'info' => '本地托底目录无法创建：' . $localRef . '（请检查父目录写权限）')));
                }
            }
        }
        $cfg = \app\common\ConfigStore::load('filecheck');
        $cfg['local_ref'] = $localRef;
        \app\common\ConfigStore::save('filecheck', $cfg);
        \app\common\ConfigStore::clearCache('filecheck');
        \ZhiCms\ext\AdminLog::write('filecheck', '保存了文件校对设置（本地托底：' . ($localRef ?: '默认 data/repo_ref/') . '）');
        exit(json_encode(array('status' => 'y', 'info' => '设置已保存')));
    }

    /**
     * 立即下载官方压缩包到服务器缓存（data/repo_ref/zhicms.zip）
     */
    public function dlZip(){
        $this->checkManageSession();
        $zip = $this->zipCachePath();
        $dir = dirname($zip);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $bin = $this->downloadZip(self::ZIP_URL);
        if ($bin === false) exit(json_encode(array('status' => 'n', 'info' => '下载官方压缩包失败（请检查服务器外网或压缩包地址）')));
        if (file_put_contents($zip, $bin) === false) exit(json_encode(array('status' => 'n', 'info' => '写入压缩包失败，请检查 data/repo_ref/ 写权限')));
        \ZhiCms\ext\AdminLog::write('filecheck', '下载了官方压缩包到服务器缓存（' . strlen($bin) . ' 字节）');
        exit(json_encode(array('status' => 'y', 'info' => '官方压缩包已下载并缓存（' . round(strlen($bin)/1024/1024, 2) . ' MB），可作为离线兜底')));
    }

    /**
     * 一键恢复整站：从官方压缩包（代码仓快照）抽取代码覆盖除数据库配置外的全部文件，
     * 并清理各类缓存，用于用户把站点搞乱时快速还原。
     */
    public function restoreAll(){
        $this->checkManageSession();
        // 恢复时优先本地缓存的压缩包，过期自动重新下载（维持最新版）
        $zip = $this->ensureZip();
        if ($zip === false) exit(json_encode(array('status' => 'n', 'info' => '恢复失败：无法获取官方压缩包（请检查服务器外网，或先点“立即下载官方压缩包”）')));
        if (!class_exists('ZipArchive') || ($z = new ZipArchive) === false || $z->open($zip) !== true) {
            exit(json_encode(array('status' => 'n', 'info' => '恢复失败：无法打开官方压缩包')));
        }

        // 恢复前自动备份当前程序文件，便于出问题时回滚
        $backup = $this->backupCurrent();
        $backupName = $backup ? basename($backup) : '（备份失败，请确认 data/repo_ref/ 可写）';

        // 需要覆盖的代码目录（含框架 vendor，确保彻底还原）
        $restoreDirs = array('app', 'ZhiCms', 'public', 'plugins', 'vendor');
        // 必须保留的站点配置（不覆盖，避免恢复后连不上数据库/丢失站点设置）
        $keep = array(
            'data/config/db.php',
            'data/config/siteconfig.php',
            'data/config/install.lock',
            'data/config/version.php',
            'data/config/seopush_log.json',
        );
        $keepPrefixes = array('upload/', 'data/repo_ref/', 'data/filecheck/', 'data/spiderlog/', 'data/ai_chat_history/');

        $written = 0;
        $skipped = 0;
        $failed  = array();
        for ($i = 0; $i < $z->numFiles; $i++) {
            $name = $z->getNameIndex($i);
            if ($name === false) continue;
            $name = ltrim($name, '/');
            if (substr($name, -1) === '/') continue;          // 目录条目跳过
            // 仅覆盖代码目录内的文件
            $inRestore = false;
            foreach ($restoreDirs as $d) {
                if (strpos($name, $d . '/') === 0) { $inRestore = true; break; }
            }
            if (!$inRestore) continue;
            // 保护站点配置
            if (in_array($name, $keep, true)) { $skipped++; continue; }
            $protected = false;
            foreach ($keepPrefixes as $p) { if (strpos($name, $p) === 0) { $protected = true; break; } }
            if ($protected) { $skipped++; continue; }

            $content = $z->getFromName($name);
            if ($content === false) continue;
            $dst = \ROOT_PATH . $name;
            $dstDir = dirname($dst);
            if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
            if (file_put_contents($dst, $content) !== false) $written++;
            else $failed[] = $name;
        }
        $z->close();

        // 清理缓存，确保恢复后立即生效
        $this->clearCaches();
        // 重建自动加载类映射（若有脚本）
        $this->rebuildClassmap();

        \ZhiCms\ext\AdminLog::write('filecheck', '一键恢复整站：覆盖 ' . $written . ' 个文件，跳过 ' . $skipped . ' 个配置，失败 ' . count($failed) . '，备份：' . $backupName);
        if ($failed) {
            exit(json_encode(array('status' => 'n', 'info' => '恢复完成，但 ' . count($failed) . ' 个文件写入失败：' . implode('、', array_slice($failed, 0, 5)) . '（恢复前备份：' . $backupName . '）')));
        }
        exit(json_encode(array('status' => 'y', 'info' => '整站已恢复（覆盖 ' . $written . ' 个文件，已保留数据库/站点配置并清理缓存）。恢复前已自动备份当前程序文件：' . $backupName)));
    }

    /**
     * 恢复前自动备份当前程序文件（app/ZhiCms/public/plugins/vendor）到 data/repo_ref/backup_时间戳.zip
     * 返回备份路径或 false
     */
    private function backupCurrent(){
        if (!class_exists('ZipArchive')) return false;
        $backupDir = \ROOT_PATH . 'data/repo_ref/';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
        $stamp = date('Ymd_His');
        $zipPath = $backupDir . 'backup_' . $stamp . '.zip';
        $backupDirs = array('app', 'ZhiCms', 'public', 'plugins', 'vendor');
        $z = new ZipArchive;
        if ($z->open($zipPath, ZipArchive::CREATE) !== true) return false;
        $added = 0;
        foreach ($backupDirs as $d) {
            $base = \ROOT_PATH . $d;
            if (!is_dir($base)) continue;
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $f) {
                if ($f->isDir()) continue;
                $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(\ROOT_PATH))), '/');
                if ($z->addFile($f->getPathname(), $rel)) $added++;
            }
        }
        $z->close();
        return $added > 0 ? $zipPath : false;
    }

    /**
     * 清理各类运行时缓存，帮助恢复后立即生效
     */
    private function clearCaches(){
        $dirs = array(
            'data/cache',
            'data/apicache',
            'data/log',
            'data/filecheck',
            'runtime',
            'runtime/cache',
        );
        foreach ($dirs as $d) {
            $path = \ROOT_PATH . $d;
            if (!is_dir($path)) continue;
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($rii as $f) {
                if ($f->isFile()) @unlink($f->getPathname());
            }
        }
        // 模板编译缓存（data/cache/tpl 已在上面被清，这里确保）
        $tpl = \ROOT_PATH . 'data/cache/tpl';
        if (is_dir($tpl)) {
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tpl, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($rii as $f) { if ($f->isFile()) @unlink($f->getPathname()); }
        }
    }

    /**
     * 若存在类映射重建脚本则执行（vendor 覆盖后可能需要）
     */
    private function rebuildClassmap(){
        $script = \ROOT_PATH . 'rebuild_classmap.php';
        if (is_file($script)) {
            // 静默执行，忽略输出
            @include $script;
        }
    }

    public function index(){
        // 禁止浏览器缓存本页，避免“检测更新”等按钮的前端脚本被旧缓存命中（导致接口返回数据异常）
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $this->checkManageSession();
        $this->pageText = array('文件校对');
        $this->toolTitle = '文件校对';

        $manifest = $this->loadManifest();
        $this->hasBaseline = !empty($manifest);
        $this->baselineTime = $this->hasBaseline ? (isset($manifest['__time']) ? $manifest['__time'] : 0) : 0;
        $this->baselineCount = $this->hasBaseline ? (count($manifest) - (isset($manifest['__time']) ? 1 : 0)) : 0;
        $cfg = \app\common\ConfigStore::load('filecheck');
        $this->gitee = $this->giteeUrl();
        $this->branch = $this->branch();
        $this->localRef = isset($cfg['local_ref']) ? $cfg['local_ref'] : '';
        $this->hasLocal = ($this->localRef !== '' && is_dir($this->localRef)) || is_dir(\ROOT_PATH . 'data/repo_ref/');

        // 若已建立基线，自动执行一次比对并展示结果
        $this->result = null;
        if ($this->hasBaseline) {
            $this->result = $this->decorate($this->doCheck());
        }
        $this->display();
    }

    /**
     * 建立基线（记录当前所有文件 MD5）
     */
    public function build(){
        $this->checkManageSession();
        try {
            $dir = \ROOT_PATH . $this->baseDir;
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    exit(json_encode(array('info' => '无法创建目录：' . $this->baseDir . '（请检查 data/ 写权限）', 'status' => 'n')));
                }
            }
            $files = $this->scanFiles();
            if (empty($files)) {
                exit(json_encode(array('info' => '未发现可校验文件', 'status' => 'n')));
            }
            $manifest = array('__time' => time());
            foreach ($files as $rel) {
                $manifest[$rel] = $this->md5File(\ROOT_PATH . $rel);
            }
            $json = json_encode($manifest, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                exit(json_encode(array('info' => '基线数据序列化失败', 'status' => 'n')));
            }
            if (file_put_contents(\ROOT_PATH . $this->manifestFile, $json) === false) {
                exit(json_encode(array('info' => '写入基线文件失败（请检查 ' . $this->baseDir . ' 写权限）', 'status' => 'n')));
            }
            \ZhiCms\ext\AdminLog::write('filecheck', '建立了文件校验基线（' . count($files) . ' 个文件）');
            exit(json_encode(array('info' => '基线已建立，共 ' . count($files) . ' 个文件', 'status' => 'y')));
        } catch (\Throwable $e) {
            exit(json_encode(array('info' => '建立失败：' . $e->getMessage(), 'status' => 'n')));
        }
    }

    /**
     * 执行比对
     */
    public function check(){
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $this->checkManageSession();
        $manifest = $this->loadManifest();
        if (empty($manifest)) exit(json_encode(array('info' => '请先建立基线', 'status' => 'n')));
        $result = $this->decorate($this->doCheck());
        $this->result = $result;
        $this->hasBaseline = true;
        $this->baselineTime = $manifest['__time'];
        $this->baselineCount = count($manifest) - 1;
        $this->gitee = $this->giteeUrl();
        $this->branch = $this->branch();
        $cfg = \app\common\ConfigStore::load('filecheck');
        $this->localRef = isset($cfg['local_ref']) ? $cfg['local_ref'] : '';
        $this->hasLocal = ($this->localRef !== '' && is_dir($this->localRef)) || is_dir(\ROOT_PATH . 'data/repo_ref/');
        // check 复用 index 模板展示比对结果（不存在 check.html，避免“模板未找到”报错）
        $this->display('app/manage/view/filecheck/index');
    }

    /**
     * 核心比对逻辑（返回 changed/added/missing 三类）
     */
    private function doCheck(){
        $manifest = $this->loadManifest();
        $baseline = $manifest;
        unset($baseline['__time']);

        $current = array();
        foreach ($this->scanFiles() as $rel) {
            $current[$rel] = $this->md5File(\ROOT_PATH . $rel);
        }

        $changed = array();
        $missing = array();
        $added   = array();

        foreach ($baseline as $rel => $hash) {
            if (!isset($current[$rel])) {
                $missing[] = $rel;
            } elseif ($current[$rel] !== $hash) {
                $changed[] = $rel;
            }
        }
        foreach ($current as $rel => $hash) {
            if (!isset($baseline[$rel])) {
                $added[] = $rel;
            }
        }

        return array(
            'changed' => $changed,
            'missing' => $missing,
            'added'   => $added,
            'total'   => count($baseline),
            'scanned' => count($current),
            'clean'   => (empty($changed) && empty($missing) && empty($added)),
        );
    }

    /**
     * 为比对结果中的每个文件附加元信息：是否受保护、是否可写、是否可从代码仓恢复
     */
    private function decorate($result){
        if (!is_array($result)) return $result;
        foreach (array('changed', 'missing', 'added') as $key) {
            $out = array();
            foreach ($result[$key] as $rel) {
                $full = \ROOT_PATH . $rel;
                $writable = is_file($full) ? is_writable($full) : is_writable(dirname($full));
                $out[] = array(
                    'file'       => $rel,
                    'protected'  => $this->isProtected($rel),
                    'writable'   => $writable,
                    'editable'   => (!$this->isProtected($rel) && $writable),
                    'restorable' => $this->isProtected($rel),   // 受保护文件均可从代码仓恢复
                );
            }
            $result[$key] = $out;
        }
        return $result;
    }
}
