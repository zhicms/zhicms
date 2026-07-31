<?php
namespace plugins\wxapp_packer;

use ZhiCms\base\plugin\BasePlugin;
use ZhiCms\base\Hook;

class Plugin extends BasePlugin
{

    public function register()
    {
        // 插件无全局钩子需要注册
    }

    public function install()
    {
        // 确保下载目录存在
        $downloadDir = BASE_PATH . 'runtime/wxapp_packer/downloads/';
        if (!is_dir($downloadDir)) {
            @mkdir($downloadDir, 0755, true);
        }
    }

    public function uninstall()
    {
        // 清理下载目录
        $downloadDir = BASE_PATH . 'runtime/wxapp_packer/downloads/';
        if (is_dir($downloadDir)) {
            $this->removeDirectory($downloadDir);
        }
    }

    public function enable()
    {
        $this->install();
    }

    /**
     * 执行打包
     *
     * uniapp 模式：从远程下载 uniapp 源码 ZIP → 直接提供给用户下载（不替换）
     * miniprogram 模式：从远程下载编译后的小程序 ZIP → 解压 → 替换网址/appid/名称 → 重新打包
     *
     * @param string $mode      "uniapp" 或 "miniprogram"
     * @param array  $params    [ target_url, appid, app_name ]（仅 miniprogram 模式需要）
     * @return array            [ zip_path, file_name, download_url ]
     * @throws \Exception
     */
    public function build($mode, array $params)
    {
        // 从 Setting 获取下载地址（而非使用成员变量）
        try {
            $settingClass = '\\plugins\\wxapp_packer\\Setting';
            $setting = new $settingClass();
            $updateUrls = $setting->fetchUpdateUrls();
            $uniappUrl = $updateUrls['uniapp_url'];
            $mpUrl     = $updateUrls['mp_url'];
        } catch (\Throwable $e) {
            throw new \Exception('无法获取下载地址，请检查网络连接');
        }

        $downloadDir = BASE_PATH . 'runtime/wxapp_packer/downloads/';
        if (!is_dir($downloadDir)) {
            @mkdir($downloadDir, 0755, true);
        }

        if ($mode === 'uniapp') {
            // uniapp 模式：直接下载远程 ZIP → 存为输出文件，不做任何替换
            $fileName = 'uniapp-source_' . date('YmdHis') . '.zip';
            $zipPath  = $downloadDir . $fileName;

            $this->downloadRemoteFile($uniappUrl, $zipPath);

            return array(
                'zip_path'   => $zipPath,
                'file_name'  => $fileName,
                'download_url' => 'index.php?r=manage/plugin/download&alias=wxapp_packer&file=' . urlencode($fileName) . '&direct=1',
            );
        }

        // ====== miniprogram 模式 ======
        $targetUrl = rtrim(strval($params['target_url'] ?? ''), '/');
        $appid     = trim(strval($params['appid'] ?? ''));
        $appName   = trim(strval($params['app_name'] ?? ''));

        if ($targetUrl === '' || strpos($targetUrl, 'http') !== 0) {
            throw new \Exception('请填写正确的 HTTPS 网址');
        }
        if (stripos($targetUrl, 'https://') !== 0) {
            throw new \Exception('小程序源码模式必须使用 HTTPS 后端地址');
        }
        if ($appid === '' || !preg_match('/^wx[0-9a-f]{16}$/i', $appid)) {
            throw new \Exception('请填写正确的微信 AppID（格式：wx + 16位字母数字）');
        }
        if ($appName === '') {
            throw new \Exception('请填写小程序名称');
        }

        // 准备临时目录
        $baseTmp = BASE_PATH . 'runtime/wxapp_packer/';
        if (!is_dir($baseTmp)) {
            @mkdir($baseTmp, 0755, true);
        }
        $sessionId = substr(md5(session_id() . microtime(true)), 0, 12);
        $workDir   = $baseTmp . 'build_' . $sessionId . '/';
        $targetDir = $workDir . 'zhicms-miniprogram/';

        if (!is_dir($workDir) && !@mkdir($workDir, 0755, true)) {
            throw new \Exception('无法创建临时目录');
        }

        try {
            // 1. 下载远程小程序源码 ZIP 并解压
            $this->downloadAndExtract($mpUrl, $targetDir, 'mp_source');

            // 2. 替换占位符
            $this->replacePlaceholders($targetDir, $mode, $targetUrl, $appid, $appName);

            // 3. 打包 ZIP
            $fileName = 'miniprogram-source_' . date('YmdHis') . '.zip';
            $zipPath  = $downloadDir . $fileName;
            $this->zipDirectory($targetDir, $zipPath, 'zhicms-miniprogram');

            // 4. 清理临时目录
            $this->removeDirectory($workDir);

            return array(
                'zip_path'   => $zipPath,
                'file_name'  => $fileName,
                'download_url' => 'index.php?r=manage/plugin/download&alias=wxapp_packer&file=' . urlencode($fileName) . '&direct=1',
            );
        } catch (\Throwable $e) {
            if (is_dir($workDir)) {
                $this->removeDirectory($workDir);
            }
            throw $e;
        }
    }

    /**
     * 下载打包好的 ZIP
     */
    public function serveDownload($fileName)
    {
        $downloadDir = BASE_PATH . 'runtime/wxapp_packer/downloads/';
        // 防止路径穿越
        $fileName = basename($fileName);
        $filePath = $downloadDir . $fileName;

        if (!file_exists($filePath)) {
            header('HTTP/1.1 404 Not Found');
            echo '文件不存在或已过期';
            exit;
        }

        // 获取文件扩展名
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        
        // 设置正确的MIME类型
        $mimeTypes = array(
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z'  => 'application/x-7z-compressed'
        );
        $contentType = isset($mimeTypes[$fileExtension]) ? $mimeTypes[$fileExtension] : 'application/octet-stream';

        // 清除输出缓冲
        if (ob_get_level()) {
            ob_end_clean();
        }

        // 设置下载头
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        
        // 防止超时
        set_time_limit(0);
        
        // 读取并输出文件
        $chunkSize = 1024 * 1024; // 1MB chunks
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            header('HTTP/1.1 500 Internal Server Error');
            echo '无法读取文件';
            exit;
        }
        
        while (!feof($handle)) {
            echo fread($handle, $chunkSize);
            flush();
            if (connection_status() != 0) {
                fclose($handle);
                exit;
            }
        }
        
        fclose($handle);
        exit;
    }

    // ===================== 内部方法 =====================

    /**
     * 下载远程文件到本地路径
     *
     * @param string $url       远程 URL
     * @param string $savePath  本地保存路径
     * @throws \Exception
     */
    protected function downloadRemoteFile($url, $savePath)
    {
        // 优先使用 cURL
        if (function_exists('curl_init')) {
            $fp = fopen($savePath, 'wb');
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL            => $url,
                CURLOPT_FILE           => $fp,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'ZhiCms-WxappPacker/1.0',
            ));
            $ok = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($ok && $code === 200) {
                return;
            }
            @unlink($savePath);
            throw new \Exception('下载远程源码失败（HTTP ' . $code . '）：' . $err);
        }

        // 兜底：file_get_contents
        $ctx = stream_context_create(array(
            'ssl'  => array('verify_peer' => false, 'verify_peer_name' => false),
            'http' => array('timeout' => 120, 'user_agent' => 'ZhiCms-WxappPacker/1.0'),
        ));
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            throw new \Exception('下载远程源码失败：' . $url . '（请检查服务器网络或 allow_url_fopen 设置）');
        }
        @file_put_contents($savePath, $data);
    }

    /**
     * 下载远程 ZIP 并解压到目标目录（带缓存，6 小时内不重复下载）
     *
     * @param string $url        远程 ZIP 地址
     * @param string $targetDir  解压目标目录
     * @param string $cacheKey   缓存键名
     * @throws \Exception
     */
    protected function downloadAndExtract($url, $targetDir, $cacheKey)
    {
        if (!extension_loaded('zip')) {
            throw new \Exception('服务器未启用 ZipArchive 扩展');
        }

        // 缓存目录
        $cacheDir = BASE_PATH . 'runtime/wxapp_packer/cache/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheFile  = $cacheDir . $cacheKey . '.zip';
        $cacheTtl   = 21600; // 6 小时

        // 命中缓存则直接用，否则重新下载
        if (!file_exists($cacheFile) || (time() - filemtime($cacheFile) > $cacheTtl)) {
            $this->downloadRemoteFile($url, $cacheFile);
        }

        // 解压
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($cacheFile) !== true) {
            // 缓存损坏，重新下载
            @unlink($cacheFile);
            $this->downloadRemoteFile($url, $cacheFile);
            if ($zip->open($cacheFile) !== true) {
                throw new \Exception('无法打开下载的 ZIP 文件');
            }
        }
        $zip->extractTo($targetDir);
        $zip->close();

        // 如果 ZIP 内只有一个根目录，将其内容提升到 targetDir
        $entries = array_diff(scandir($targetDir), array('.', '..'));
        if (count($entries) === 1) {
            $onlyEntry = $targetDir . '/' . reset($entries);
            if (is_dir($onlyEntry)) {
                $subItems = array_diff(scandir($onlyEntry), array('.', '..'));
                foreach ($subItems as $item) {
                    @rename($onlyEntry . '/' . $item, $targetDir . '/' . $item);
                }
                @rmdir($onlyEntry);
            }
        }
    }

    /**
     * 在目标目录中批量替换占位符
     */
    protected function replacePlaceholders($targetDir, $mode, $targetUrl, $appid, $appName)
    {
        // 构造替换表
        $searchApp      = array('wx1fec127dc0352598');
        $replaceApp     = array($appid);

        $searchUrl      = array('http://localhost');
        $replaceUrl     = array($targetUrl);

        // 名称替换：按从长到短顺序替换，避免部分匹配遗漏
        $searchNames  = array('ZhiCms 配套 AI 智能购物助手（小程序 / H5 / App 多端）', 'AIZhiCms', '好价精选', 'ZhiCms');
        $replaceNames = array($appName . ' 配套小程序', $appName, $appName, $appName);

        // 可替换的文件扩展名（文本文件才替换，避免破坏图片）
        $textExts = array('json', 'js', 'wxml', 'wxss', 'wxs', 'vue', 'scss', 'css', 'html', 'xml', 'txt', 'md', 'editorconfig', 'gitignore');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($targetDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $fileName = $item->getFilename();
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // 对于无扩展名的文件（如 .gitignore、.editorconfig），检查文件名
            $isTextFile = false;
            if ($ext !== '' && in_array($ext, $textExts, true)) {
                $isTextFile = true;
            } elseif (in_array(strtolower($fileName), array('.gitignore', '.editorconfig'), true)) {
                $isTextFile = true;
            } elseif ($fileName === 'config' || $fileName === 'project.config' || $fileName === 'project.private.config') {
                $isTextFile = true;
            }
            if (!$isTextFile) {
                continue;
            }

            $path = $item->getPathname();
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }

            $newContent = $content;

            // 1. 先替换 AppID
            $newContent = str_replace($searchApp, $replaceApp, $newContent);

            // 2. 替换后端网址
            $newContent = str_replace($searchUrl, $replaceUrl, $newContent);

            // 3. 替换名称（从长到短避免冲突）
            $newContent = str_replace($searchNames, $replaceNames, $newContent);

            if ($newContent !== $content) {
                @file_put_contents($path, $newContent, LOCK_EX);
            }
        }

        // 额外：uniapp 模式下 pages.json 中的 page titles 需要单独处理
        if ($mode === 'uniapp' && file_exists($targetDir . '/pages.json')) {
            $pagesJson = @file_get_contents($targetDir . '/pages.json');
            if ($pagesJson !== false) {
                // 导航栏标题替换（更精准，避免误替换其他字段）
                $pagesJson = preg_replace('/"navigationBarTitleText"\s*:\s*"[^"]*好价精选[^"]*"/u', '"navigationBarTitleText": "' . $appName . '"', $pagesJson);
                $pagesJson = preg_replace('/"navigationBarTitleText"\s*:\s*"[^"]*AIZhiCms[^"]*"/u', '"navigationBarTitleText": "' . $appName . '"', $pagesJson);
                @file_put_contents($targetDir . '/pages.json', $pagesJson, LOCK_EX);
            }
        }

        // 小程序模式下，app.json 的全局标题、pages/*/index.json 的页面标题
        if ($mode === 'miniprogram') {
            if (file_exists($targetDir . '/app.json')) {
                $appJson = @file_get_contents($targetDir . '/app.json');
                if ($appJson !== false) {
                    $appJson = preg_replace('/"navigationBarTitleText"\s*:\s*"[^"]*AIZhiCms[^"]*"/u', '"navigationBarTitleText": "' . $appName . '"', $appJson);
                    @file_put_contents($targetDir . '/app.json', $appJson, LOCK_EX);
                }
            }
            if (file_exists($targetDir . '/pages/index/index.json')) {
                $idxJson = @file_get_contents($targetDir . '/pages/index/index.json');
                if ($idxJson !== false) {
                    $idxJson = preg_replace('/"navigationBarTitleText"\s*:\s*"[^"]*好价精选[^"]*"/u', '"navigationBarTitleText": "' . $appName . '"', $idxJson);
                    @file_put_contents($targetDir . '/pages/index/index.json', $idxJson, LOCK_EX);
                }
            }
        }
    }

    /**
     * 将目录打包成 ZIP
     *
     * @param string $sourceDir  要打包的目录
     * @param string $zipPath    输出 ZIP 文件路径
     * @param string $rootName   ZIP 内根文件夹名称
     * @throws \Exception
     */
    protected function zipDirectory($sourceDir, $zipPath, $rootName = '')
    {
        if (!extension_loaded('zip')) {
            throw new \Exception('服务器未启用 ZipArchive 扩展，请联系管理员开启');
        }

        $zip = new \ZipArchive();
        $openFlag = file_exists($zipPath) ? (\ZipArchive::OVERWRITE) : (\ZipArchive::CREATE);
        if ($zip->open($zipPath, $openFlag) !== true) {
            throw new \Exception('无法创建 ZIP 文件：' . $zipPath);
        }

        $sourceDir = rtrim($sourceDir, '/\\');
        $rootPrefix = trim($rootName, '/\\');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = $iterator->getSubPathName();
            $relativePath = str_replace('\\', '/', $relativePath);
            $zipEntryPath = ($rootPrefix !== '') ? ($rootPrefix . '/' . $relativePath) : $relativePath;

            if ($item->isDir()) {
                $zip->addEmptyDir($zipEntryPath);
            } else {
                $zip->addFile($item->getPathname(), $zipEntryPath);
            }
        }

        $zip->close();
    }

    protected function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), array('.', '..'));
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * 清理超过 1 小时的旧打包文件
     */
    public function cleanupOld()
    {
        $downloadDir = BASE_PATH . 'runtime/wxapp_packer/downloads/';
        if (!is_dir($downloadDir)) {
            return;
        }
        $ttl = 3600;
        $now = time();
        foreach (glob($downloadDir . '*.zip') as $f) {
            if ($now - filemtime($f) > $ttl) {
                @unlink($f);
            }
        }
    }
}
