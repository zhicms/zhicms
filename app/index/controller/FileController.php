<?php
namespace app\index\controller;
obj('api/Api')->isCookies('ZhiCmsUser','index.php?r=index/login/index');
error_reporting(0);
class FileController extends \app\base\controller\BaseController 
{
    public function upload($obj, $originName)
    {
        $up = new \ZhiCms\ext\Upload();
        $up->set("path", ROOT_PATH . "/upload/{$obj}");
        $up->set("maxsize", 10000000);
        $up->set("allowtype", array("gif", "png", "jpg", "jpeg","mp3"));
        $up->set("israndname", true);
        $up->set("originName", $originName);
        if ($up->upload($originName)) {
            $fileName = $up->getFileName();
        } else {
            $fileName = $up->getErrorMsg();
        }
        return $fileName;
    }
  
    public function manage(){
        $fileName = self::upload("manage", "file");
        echo json_encode(array("url" => "upload/manage/{$fileName}"));
    }
     public function user(){
         $fileName = self::upload("user", "file");
         $mobile=$_COOKIE['ZhiCmsUser'];
         $where[]="  `mobile` LIKE  '{$mobile}'";
         $data['pic']="upload/user/".$fileName;
         obj('api/ApiData')->dataUpdate('yun_user', $data, $where);
         echo json_encode(array("url" => "upload/user/{$fileName}"));
    }
    public function forum(){
        $fileName = self::upload("forum", "file");
         exit(json_encode(array("code"=>"0","msg"=>"上传成功","data"=>array("src"=>"upload/forum/{$fileName}"))));
    }

   
}