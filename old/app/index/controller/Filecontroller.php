<?php
namespace app\index\controller;
obj('api/Api')->isCookies('ZhiCmsUser','index.php?r=index/login/index');
error_reporting('0');
class FileController extends \app\base\controller\BaseController 
{
    //图片附件上传接口
    public function Upload($obj, $originName)
    {
        $up = new \ZhiCms\ext\Upload();
        $up->set("path", ROOT_PATH . "/upload/{$obj}");
        $up->set("maxsize", 10000000);
        $up->set("allowtype", array("gif", "png", "jpg", "jpeg","mp3"));
        $up->set("israndname", true);
        $up->set("originName", $originName);
        if ($up->upload($originName)) {
            $filename = $up->getFileName();
        } else {
            $filename = $up->getErrorMsg();
            //self::kindalert($filename);
        }
        return $filename;
    }
  
    /* 管理员账户图片存储*/
    public function manage(){
        $filename = self::Upload("manage", "file");
        echo json_encode(array("url" => "upload/manage/{$filename}"));
    }
     /* 用户账户图片存储*/
    public function user(){
         $filename = self::Upload("user", "file");
         $mobile=$_COOKIE['ZhiCmsUser'];
         $where[]="  `mobile` LIKE  '{$mobile}'";
         $data['pic']="upload/user/".$filename;
         obj('api/ApiData')->Data_Updata('user', $data, $where);
         echo json_encode(array("url" => "upload/user/{$filename}"));
    }
    /* 圈子图片存储*/
    public function forum(){
        $filename = self::Upload("forum", "file");
        //echo json_encode(array("url" => "upload/forum/{$filename}"));
         exit(json_encode(array("code"=>"0","msg"=>"上传成功","data"=>array("src"=>"upload/forum/{$filename}"))));
    }

   
}