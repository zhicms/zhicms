<?php
/**
 * 前端文件上传控制器 - 统一接口 + WebP 转换
 * 
 * 处理前端用户上传的图片
 */

namespace app\index\controller;

use \app\base\controller\BaseController;

class FileController extends BaseController
{
    /**
     * 返回 JSON 响应并终止
     */
    private function jsonExit($data)
    {
        // 彻底关闭错误显示，防止任何 PHP 错误污染 JSON
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 0);

        // 清理所有输出缓冲（最多清理 10 层）
        $level = ob_get_level();
        $maxClean = 10;
        while ($level > 0 && $maxClean > 0) {
            @ob_end_clean();
            $level--;
            $maxClean--;
        }

        if (headers_sent()) {
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
     * 图片转 WebP 格式
     * 自动处理调色板图像（GIF/PNG 索引色），转换为真彩色后再输出 WebP
     */
    private function convertToWebP($sourcePath, $targetPath)
    {
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

        // 转换为真彩色，避免调色板图像导致 imagewebp 出错
        $width = imagesx($srcImage);
        $height = imagesy($srcImage);
        $trueColor = imagecreatetruecolor($width, $height);
        
        imagealphablending($trueColor, false);
        imagesavealpha($trueColor, true);
        $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
        imagefilledrectangle($trueColor, 0, 0, $width - 1, $height - 1, $transparent);
        imagecopy($trueColor, $srcImage, 0, 0, 0, 0, $width, $height);

        $result = @imagewebp($trueColor, $targetPath, 85);
        imagedestroy($srcImage);
        imagedestroy($trueColor);

        return $result;
    }

    /**
     * 统一上传处理（自动转换为 WebP）
     */
    private function doUpload($uploadDir, $fieldName = 'file')
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

        // 允许的图片扩展名
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $isImage = in_array($ext, $allowedExts);

        // 生成文件名
        $fileName = substr(md5($file['name']), 0, 4) . time();

        // 生成目录路径（按日期组织）
        $dateDir = date('Ymd');
        $targetDir = ROOT_PATH . "upload/{$uploadDir}/{$dateDir}";

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0777, true)) {
                return ['error' => 1, 'message' => '创建上传目录失败', 'url' => ''];
            }
        }

        if ($isImage) {
            // 先保存为临时文件
            $tempPath = $targetDir . '/' . $fileName . '.' . $ext;
            if (!@move_uploaded_file($file['tmp_name'], $tempPath)) {
                @unlink($file['tmp_name']);
                return ['error' => 1, 'message' => '保存文件失败', 'url' => ''];
            }

            // 转换为 WebP
            $webpPath = $targetDir . '/' . $fileName . '.webp';
            if ($this->convertToWebP($tempPath, $webpPath)) {
                @unlink($tempPath);
                $url = "/upload/{$uploadDir}/{$dateDir}/{$fileName}.webp";
            } else {
                $url = "/upload/{$uploadDir}/{$dateDir}/{$fileName}." . $ext;
            }
        } else {
            $targetPath = $targetDir . '/' . $fileName . '.' . $ext;
            if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
                @unlink($file['tmp_name']);
                return ['error' => 1, 'message' => '保存文件失败', 'url' => ''];
            }
            $url = "/upload/{$uploadDir}/{$dateDir}/{$fileName}." . $ext;
        }

        return ['error' => 0, 'url' => $url];
    }

    /**
     * 用户头像上传
     * URL: index.php?r=index/File/user
     */
    public function user()
    {
        // 立即关闭错误显示
        error_reporting(0);
        ini_set('display_errors', 0);
        
        if (!isset($_COOKIE['ZhiCmsUser']) || empty($_COOKIE['ZhiCmsUser'])) {
            $this->jsonExit(['url' => '', 'error' => 1, 'message' => '请先登录']);
        }

        $result = $this->doUpload('user', 'file');
        
        if ($result['error']) {
            $this->jsonExit(['url' => '', 'error' => 1, 'message' => $result['message']]);
        }

        // 更新用户头像
        $mobile = $_COOKIE['ZhiCmsUser'];
        $data['pic'] = $result['url'];
        $where[] = "`mobile` LIKE '{$mobile}'";
        obj('api/ApiData')->dataUpdate('yun_user', $data, $where);

        $this->jsonExit(['url' => $result['url']]);
    }

    /**
     * 前端管理上传（兼容旧接口）
     * URL: index.php?r=index/File/manage
     */
    public function manage()
    {
        error_reporting(0);
        ini_set('display_errors', 0);
        
        $result = $this->doUpload('manage', 'file');
        $this->jsonExit(['url' => $result['url']]);
    }

    /**
     * 社区图片上传
     * URL: index.php?r=index/File/forum
     */
    public function forum()
    {
        error_reporting(0);
        ini_set('display_errors', 0);
        
        $result = $this->doUpload('forum', 'file');
        
        if ($result['error']) {
            $this->jsonExit(['code' => '1', 'msg' => $result['message'], 'data' => ['src' => '']]);
        }

        $this->jsonExit(['code' => '0', 'msg' => '上传成功', 'data' => ['src' => $result['url']]]);
    }

    /**
     * 统一上传接口
     * URL: index.php?r=index/File/upload&type=xxx
     */
    public function upload()
    {
        // 立即关闭错误显示
        error_reporting(0);
        ini_set('display_errors', 0);
        
        $type = isset($_GET['type']) ? $_GET['type'] : 'user';
        $fieldName = isset($_GET['field']) ? $_GET['field'] : 'file';

        // 检查登录（某些类型需要）
        $needLogin = ['user', 'forum'];
        if (in_array($type, $needLogin)) {
            if (!isset($_COOKIE['ZhiCmsUser']) || empty($_COOKIE['ZhiCmsUser'])) {
                $this->jsonExit(['url' => '', 'error' => 1, 'message' => '请先登录']);
            }
        }

        $result = $this->doUpload($type, $fieldName);
        $this->jsonExit([
            'error' => $result['error'],
            'url'   => $result['url'],
        ]);
    }
}
