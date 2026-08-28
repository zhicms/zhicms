<?php
/**
 * 后台文件上传控制器 - 统一接口 + WebP 转换
 * 
 * 所有上传统一走 upload() 方法，自动转换为 WebP 格式
 */

namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class FileController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /**
     * 返回 JSON 响应并终止
     */
    private function jsonExit($data)
    {
        // 彻底关闭错误显示，防止任何 PHP 错误污染 JSON
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 0);

        // 清理所有输出缓冲（包括 gzip 等嵌套缓冲）
        // 最多清理 10 层，确保万无一失
        $level = ob_get_level();
        $maxClean = 10;
        while ($level > 0 && $maxClean > 0) {
            @ob_end_clean();
            $level--;
            $maxClean--;
        }

        // 确保没有任何东西被提前输出
        if (headers_sent()) {
            // 如果 header 已发送，直接输出 JSON（无法改 Content-Type）
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        echo $json;
        exit;
    }

    /**
     * 检查管理后台 Session（返回 JSON 错误）
     * 同时在此处统一关闭错误显示，防止 PHP 错误污染 JSON
     */
    private function checkSession()
    {
        // 立即关闭错误显示，防止任何 PHP 错误污染 JSON 响应
        error_reporting(0);
        ini_set('display_errors', 0);
        
        if (!isset($_SESSION['manage_system']) || empty($_SESSION['manage_system'])) {
            $this->jsonExit(['url' => '', 'error' => 1, 'message' => '登录已过期，请重新登录']);
        }
    }

    /**
     * 图片转 WebP 格式
     * 自动处理调色板图像（GIF/PNG 索引色），转换为真彩色后再输出 WebP
     * 
     * @param string $sourcePath 源文件路径
     * @param string $targetPath 目标 WebP 文件路径
     * @return bool 是否转换成功
     */
    private function convertToWebP($sourcePath, $targetPath)
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        $mime = $imageInfo['mime'];
        $srcImage = false;

        switch ($mime) {
            case 'image/jpeg':
                $srcImage = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $srcImage = @imagecreatefrompng($sourcePath);
                if ($srcImage) {
                    imagealphablending($srcImage, true);
                    imagesavealpha($srcImage, true);
                }
                break;
            case 'image/gif':
                $srcImage = @imagecreatefromgif($sourcePath);
                break;
            case 'image/bmp':
                $srcImage = @imagecreatefrombmp($sourcePath);
                break;
            case 'image/webp':
                @copy($sourcePath, $targetPath);
                return true;
            case 'image/x-icon':
            case 'image/vnd.microsoft.icon':
                return false;
            default:
                return false;
        }

        if (!$srcImage) {
            return false;
        }

        // 检查是否为调色板图像（索引色），如果是则转换为真彩色
        // PHP 7.x 的 imagewebp() 虽然能处理，但某些 GD 版本会报致命错误
        $width = imagesx($srcImage);
        $height = imagesy($srcImage);
        $trueColor = imagecreatetruecolor($width, $height);
        
        // 处理透明度
        imagealphablending($trueColor, false);
        imagesavealpha($trueColor, true);
        $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
        imagefilledrectangle($trueColor, 0, 0, $width - 1, $height - 1, $transparent);
        imagecopy($trueColor, $srcImage, 0, 0, 0, 0, $width, $height);

        // 转换为 WebP
        $result = @imagewebp($trueColor, $targetPath, 85);
        imagedestroy($srcImage);
        imagedestroy($trueColor);

        return $result;
    }

    /**
     * 统一上传处理（所有类型都走这里）
     * 
     * @param string $uploadDir 上传目录名
     * @param string $fieldName 表单字段名
     * @return array
     */
    private function doUpload($uploadDir, $fieldName = 'file')
    {
        try {
        return $this->_doUploadInner($uploadDir, $fieldName);
        } catch (\Throwable $e) {
            return ['error' => 1, 'message' => '上传异常: ' . $e->getMessage(), 'url' => ''];
        }
    }

    private function _doUploadInner($uploadDir, $fieldName = 'file')
    {
        $file = isset($_FILES[$fieldName]) ? $_FILES[$fieldName] : null;
        
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMsgs = [
                UPLOAD_ERR_INI_SIZE   => '文件大小超过服务器限制',
                UPLOAD_ERR_FORM_SIZE  => '文件大小超过表单限制',
                UPLOAD_ERR_PARTIAL    => '文件只有部分被上传',
                UPLOAD_ERR_NO_FILE    => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '临时目录不存在',
                UPLOAD_ERR_CANT_WRITE => '写入文件失败',
            ];
            $errCode = $file ? $file['error'] : UPLOAD_ERR_NO_FILE;
            return ['error' => 1, 'message' => $errorMsgs[$errCode] ?? '上传失败', 'url' => ''];
        }

        // 检查文件大小 (10MB)
        if ($file['size'] > 10485760) {
            return ['error' => 1, 'message' => '文件过大，限制 10MB', 'url' => ''];
        }

        // 仅允许图片扩展名；非图片一律拒绝，杜绝任意文件上传(RCE)
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            return ['error' => 1, 'message' => '仅支持图片文件上传', 'url' => ''];
        }
        $isImage = true;

        // 生成文件名（md5前缀 + 时间戳，32位冲突概率极低）
        $fileName = md5($file['name'] . microtime(true)) . time();

        // 生成目录路径（按日期组织）
        $dateDir = date('Ymd');
        $targetDir = \ROOT_PATH . "upload/{$uploadDir}/{$dateDir}";

        // 创建目录
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0777, true)) {
                return ['error' => 1, 'message' => '创建上传目录失败', 'url' => ''];
            }
        }

        if ($isImage) {
            // 图片：先保存为临时文件，再转换为 WebP
            $tempPath = $targetDir . '/' . $fileName . '.' . $ext;
            if (!@move_uploaded_file($file['tmp_name'], $tempPath)) {
                @unlink($file['tmp_name']);
                return ['error' => 1, 'message' => '保存文件失败', 'url' => ''];
            }

            // 转换为 WebP
            $webpPath = $targetDir . '/' . $fileName . '.webp';
            if ($this->convertToWebP($tempPath, $webpPath)) {
                @unlink($tempPath); // 删除临时文件
                $url = cdn_url("upload/{$uploadDir}/{$dateDir}/{$fileName}.webp");
                
                // 获取图片信息
                $imageInfo = @getimagesize($webpPath);
                $width = $imageInfo ? $imageInfo[0] : 0;
                $height = $imageInfo ? $imageInfo[1] : 0;
                $mimeType = 'image/webp';
            } else {
                // 转换失败，保留原格式
                $url = cdn_url("upload/{$uploadDir}/{$dateDir}/{$fileName}." . $ext);
                $imageInfo = @getimagesize($tempPath);
                $width = $imageInfo ? $imageInfo[0] : 0;
                $height = $imageInfo ? $imageInfo[1] : 0;
                $mimeType = $imageInfo ? $imageInfo['mime'] : $ext;
            }
        } else {
            // 非图片：直接保存
            $targetPath = $targetDir . '/' . $fileName . '.' . $ext;
            if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
                @unlink($file['tmp_name']);
                return ['error' => 1, 'message' => '保存文件失败', 'url' => ''];
            }
            $url = cdn_url("upload/{$uploadDir}/{$dateDir}/{$fileName}." . $ext);
            $width = 0;
            $height = 0;
            $mimeType = $ext;
        }

        return [
            'error'  => 0,
            'url'    => $url,
            'message' => '上传成功',
            'file'   => [
                'name'   => $file['name'],
                'size'   => $file['size'],
                'width'  => $width,
                'height' => $height,
                'mime'   => $mimeType,
            ],
        ];
    }

    /* ================================================
     * 统一上传入口
     * 所有后台上传都走 upload() 方法
     * URL: index.php?r=manage/File/upload&type=xxx
     * ================================================ */

    /**
     * 测试接口：返回固定 JSON，用于排查路由是否正常
     */
    public function test()
    {
        error_reporting(0);
        ini_set('display_errors', 0);
        
        $level = ob_get_level();
        while ($level > 0) { @ob_end_clean(); $level--; }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 0, 'url' => 'test-ok', 'message' => 'FileController test OK'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 测试上传接口：直接返回 $_FILES 信息
     */
    public function testupload()
    {
        error_reporting(0);
        ini_set('display_errors', 0);
        
        $level = ob_get_level();
        while ($level > 0) { @ob_end_clean(); $level--; }
        
        $file = $_FILES['file'] ?? null;
        $info = [];
        if ($file) {
            $info = [
                'name' => $file['name'],
                'type' => $file['type'],
                'size' => $file['size'],
                'error' => $file['error'],
                'tmp_name' => $file['tmp_name'],
            ];
        } else {
            $info = ['error' => 'no file in $_FILES'];
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'files' => $info, 'session' => isset($_SESSION['manage_system']) ? 'yes' : 'no'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 统一上传接口
     * 
     * 参数: type=上传类型（article/page/items/manage/huan 等）
     */
    public function upload()
    {
        // 立即关闭错误显示，防止任何 PHP 错误污染 JSON 响应
        // 这行必须在最前面，因为 App::init() 可能设置了 display_errors=On
        error_reporting(0);
        ini_set('display_errors', 0);

        $this->checkSession();

        $type = isset($_GET['type']) ? $_GET['type'] : 'article';
        $fieldName = isset($_GET['field']) ? $_GET['field'] : 'file';

        try {
            $result = $this->doUpload($type, $fieldName);
        } catch (\Throwable $e) {
            $this->jsonExit([
                'error'   => 1,
                'url'     => '',
                'message' => '上传异常: ' . $e->getMessage(),
            ]);
        }

        // 返回统一格式
        $this->jsonExit([
            'error'   => $result['error'],
            'url'     => $result['url'],
            'message' => $result['message'] ?? '',
        ]);
    }

    /**
     * 兼容旧接口：文章编辑器上传 (TinyMCE)
     */
    public function article()
    {
        $this->checkSession();
        $result = $this->doUpload('article', 'imgFile');
        $this->jsonExit([
            'error' => $result['error'],
            'url'   => $result['url'],
        ]);
    }

    /**
     * 兼容旧接口：单页编辑器上传 (TinyMCE)
     */
    public function page()
    {
        $this->checkSession();
        $result = $this->doUpload('page', 'imgFile');
        $this->jsonExit([
            'error' => $result['error'],
            'url'   => $result['url'],
        ]);
    }

    /**
     * 兼容旧接口：商品图片
     */
    public function items()
    {
        $this->checkSession();
        $result = $this->doUpload('items', 'file');
        $this->jsonExit(['url' => $result['url']]);
    }

    /**
     * 兼容旧接口：后台设置
     */
    public function manage()
    {
        $this->checkSession();
        $result = $this->doUpload('manage', 'file');
        $this->jsonExit(['url' => $result['url']]);
    }

    /**
     * 兼容旧接口：管理员头像
     */
    public function manageuser()
    {
        $this->checkSession();
        $result = $this->doUpload('manageuser', 'file');
        $this->jsonExit(['url' => $result['url']]);
    }

    /**
     * 兼容旧接口：文章封面
     */
    public function articlepic()
    {
        $this->checkSession();
        $result = $this->doUpload('articlepic', 'file');
        $this->jsonExit(['url' => $result['url']]);
    }

    /**
     * 兼容旧接口：幻灯广告
     */
    public function huan()
    {
        $this->checkSession();
        $result = $this->doUpload('huan', 'file');
        $this->jsonExit(['url' => $result['url']]);
    }

    /**
     * 兼容旧接口：发现图标
     */
    public function findtype()
    {
        $this->checkSession();
        $result = $this->doUpload('findtype', 'file');
        $this->jsonExit(['url' => $result['url']]);
    }

    /**
     * 兼容旧接口：EditorMD 上传
     */
    public function editor()
    {
        $this->checkSession();
        $type = isset($_GET['type']) ? $_GET['type'] : 'article';
        $result = $this->doUpload($type, 'editormd-image-file');
        $this->jsonExit([
            'success' => $result['error'] ? 0 : 1,
            'message' => $result['message'],
            'url'     => $result['url'],
        ]);
    }
}
