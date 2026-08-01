<?php
namespace ZhiCms\ext;

class Upload { 
    private $path = "./uploads";
    private $allowtype = array('jpg','gif','png','jpeg','bmp','webp');
    private $allowMime = array('image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/webp');
    private $maxsize = 5242880;
    private $israndname = true;
    private $convertToWebp = true;
    private $webpQuality = 80;

    private $originName;
    private $tmpFileName;
    private $fileType;
    private $fileSize;
    private $newFileName;
    private $errorNum = 0;
    private $errorMess = "";

    function set($key, $val){
        $key = strtolower($key); 
        if( array_key_exists( $key, get_class_vars(get_class($this) ) ) ){
            $this->setOption($key, $val);
        }
        return $this;
    }

    function upload($fileField) {
        $return = true;
        if( !$this->checkFilePath() ) {       
            $this->errorMess = $this->getError();
            return false;
        }
        $name = $_FILES[$fileField]['name'];
        $tmp_name = $_FILES[$fileField]['tmp_name'];
        $size = $_FILES[$fileField]['size'];
        $error = $_FILES[$fileField]['error'];

        if(is_Array($name)){    
            $errors = array();
            for($i = 0; $i < count($name); $i++){ 
                if($this->setFiles($name[$i],$tmp_name[$i],$size[$i],$error[$i] )) {
                    // 校验 MIME 类型
                    if(!$this->checkMime()) {
                        $errors[] = $this->getError();
                        $return = false; 
                        continue;
                    }
                    if(!$this->checkFileSize() || !$this->checkFileType()){
                        $errors[] = $this->getError();
                        $return = false; 
                    }
                }else{
                    $errors[] = $this->getError();
                    $return = false;
                }
                if(!$return)          
                    $this->setFiles();
            }

            if($return){
                $fileNames = array();      
                for($i = 0; $i < count($name); $i++){ 
                    if($this->setFiles($name[$i], $tmp_name[$i], $size[$i], $error[$i] )) {
                        $this->setNewFileName(); 
                        if(!$this->copyFile()){
                            $errors[] = $this->getError();
                            $return = false;
                        }
                        $fileNames[] = $this->newFileName;  
                    }          
                }
                $this->newFileName = $fileNames;
            }
            $this->errorMess = $errors;
            return $return;
        } else {
            if($this->setFiles($name,$tmp_name,$size,$error)) {
                // 校验 MIME 类型
                if(!$this->checkMime()) {
                    $return = false;
                    $this->errorMess = $this->getError();
                    return false;
                }
                if($this->checkFileSize() && $this->checkFileType()){ 
                    $this->setNewFileName(); 
                    if($this->copyFile()){ 
                        return true;
                    }else{
                        $return = false;
                    }
                }else{
                    $return = false;
                }
            } else {
                $return = false; 
            }
            if(!$return)
                $this->errorMess = $this->getError();  

            return $return;
        }
    }

    public function getFileName(){
        return $this->newFileName;
    }

    public function getErrorMsg(){
        return $this->errorMess;
    }

    private function getError() {
        $str = "上传文件<font color='red'>{$this->originName}</font>时出错 : ";
        switch ($this->errorNum) {
            case 4: $str .= "没有文件被上传"; break;
            case 3: $str .= "文件只有部分被上传"; break;
            case 2: $str .= "上传文件的大小超过了HTML表单中MAX_FILE_SIZE选项指定的值"; break;
            case 1: $str .= "上传的文件超过了php.ini中upload_max_filesize选项限制的值"; break;
            case -1: $str .= "未允许类型"; break;
            case -2: $str .= "文件过大,上传的文件不能超过{$this->maxsize}个字节"; break;
            case -3: $str .= "上传失败"; break;
            case -4: $str .= "建立存放上传文件目录失败，请重新指定上传目录"; break;
            case -5: $str .= "必须指定上传文件的路径"; break;
            case -6: $str .= "服务器不支持WebP图片转换"; break;
            case -7: $str .= "上传路径包含非法字符，可能存在路径遍历攻击风险"; break;
            default: $str .= "未知错误";
        }
        return $str;
    }

    private function setFiles($name="", $tmp_name="", $size=0, $error=0) {
        $this->setOption('errorNum', $error);
        if($error)
            return false;
        $this->setOption('originName', $name);
        $this->setOption('tmpFileName', $tmp_name);
        $aryStr = explode(".", $name);
        $this->setOption('fileType', strtolower($aryStr[count($aryStr)-1]));
        $this->setOption('fileSize', $size);
        return true;
    }

    private function checkMime() {
        // 使用 fileinfo 扩展获取真实 MIME 类型（优先）
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = @finfo_file($finfo, $this->tmpFileName);
                @finfo_close($finfo);
                if ($mime && in_array($mime, $this->allowMime)) {
                    return true;
                }
            }
        }
        // 回退到 getimagesize 检查
        $info = @getimagesize($this->tmpFileName);
        if ($info !== false) {
            $mime = $info['mime'];
            if (in_array($mime, $this->allowMime)) {
                return true;
            }
        }
        $this->setOption('errorNum', -1);
        return false;
    }

    /**
     * 过滤文件名中的非法字符，防止路径遍历攻击
     */
    private function sanitizeFilename($filename) {
        // 移除路径遍历字符
        $filename = str_replace(array('../', '..\\', '\\'), '_', $filename);
        // 移除 Windows 不允许的字符
        $filename = str_replace(array(':', '*', '?', '"', '<', '>', '|'), '_', $filename);
        // 移除空白字符和控制字符
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename);
        // 移除连续的点和空格
        $filename = preg_replace('/[\. ]+/', '_', $filename);
        // 确保不以点或空格开头结尾
        $filename = trim($filename, '. _');
        // 如果过滤后为空，使用时间戳
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        return $filename;
    }

    private function setOption($key, $val) {
        $this->$key = $val;
    }

    private function setNewFileName() {
        if ($this->israndname) {
            $fileName = date('YmdHis') . "_" . rand(100, 999);
            if ($this->convertToWebp && $this->isImageType($this->fileType)) {
                $this->setOption('newFileName', $fileName . '.webp');
            } else {
                $this->setOption('newFileName', $fileName . '.' . $this->fileType);
            }
        } else {
            if ($this->convertToWebp && $this->isImageType($this->fileType)) {
                $aryStr = explode(".", $this->originName);
                array_pop($aryStr);
                $baseName = implode(".", $aryStr);
                // 过滤文件名中的非法字符
                $baseName = $this->sanitizeFilename($baseName);
                $this->setOption('newFileName', $baseName . '.webp');
            } else {
                // 过滤文件名中的非法字符
                $cleanName = $this->sanitizeFilename($this->originName);
                $this->setOption('newFileName', $cleanName);
            }
        } 
    }

    private function isImageType($type) {
        $imageTypes = array('jpg', 'jpeg', 'png', 'gif', 'bmp');
        return in_array(strtolower($type), $imageTypes);
    }

    private function checkFileType() {
        if (in_array(strtolower($this->fileType), $this->allowtype)) {
            return true;
        } else {
            $this->setOption('errorNum', -1);
            return false;
        }
    }

    private function checkFileSize() {
        if ($this->fileSize > $this->maxsize) {
            $this->setOption('errorNum', -2);
            return false;
        } else {
            return true;
        }
    }

    private function checkFilePath() {
        if(empty($this->path)){
            $this->setOption('errorNum', -5);
            return false;
        }
        // 检查路径是否包含路径遍历字符
        if (preg_match('/[\.\/\\]+/', $this->path)) {
            $this->setOption('errorNum', -7);
            return false;
        }
        if (!file_exists($this->path) || !is_writable($this->path)) {
            if (!@mkdir($this->path, 0755, true)) {
                $this->setOption('errorNum', -4);
                return false;
            }
        }
        return true;
    }

    private function convertToWebp($sourcePath, $targetPath) {
        if (!function_exists('imagewebp')) {
            $this->setOption('errorNum', -6);
            return false;
        }

        $info = getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $src = imagecreatefrompng($sourcePath);
                imagealphablending($src, true);
                imagesavealpha($src, true);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($sourcePath);
                break;
            case 'image/bmp':
                $src = imagecreatefrombmp($sourcePath);
                break;
            case 'image/webp':
                $src = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if ($src === false) {
            return false;
        }

        $result = imagewebp($src, $targetPath, $this->webpQuality);
        imagedestroy($src);
        return $result;
    }

    private function copyFile() {
        if(!$this->errorNum) {
            $path = rtrim($this->path, '/') . '/';
            $targetPath = $path . $this->newFileName;

            if ($this->convertToWebp && $this->isImageType($this->fileType)) {
                $tempPath = $path . 'temp_' . date('YmdHis') . '_' . rand(100, 999) . '.' . $this->fileType;
                
                if (!@move_uploaded_file($this->tmpFileName, $tempPath)) {
                    $this->setOption('errorNum', -3);
                    return false;
                }

                if (!$this->convertToWebp($tempPath, $targetPath)) {
                    if ($this->errorNum == -6) {
                        // 转换函数不可用（如缺 imagewebp 扩展），回退为直接保留原图
                        if (@rename($tempPath, $targetPath)) {
                            $this->setOption('errorNum', 0);
                            return true;
                        }
                    }
                    @unlink($tempPath);
                    return false;
                }

                @unlink($tempPath);
                return true;
            } else {
                if (@move_uploaded_file($this->tmpFileName, $targetPath)) {
                    return true;
                } else {
                    $this->setOption('errorNum', -3);
                    return false;
                }
            }
        } else {
            return false;
        }
    }
}