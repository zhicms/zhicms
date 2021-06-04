<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
error_reporting('0');
class FileController extends \app\base\controller\BaseController
{
    //图片附件上传接口
    public function Upload($obj, $originName)
    {
        $up = new \ZhiCms\ext\Upload();
         $date=date("ymd",time());
        $up->set("path", ROOT_PATH . "/upload/{$obj}/{$date}");
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
  
    /* 商品图片存储*/
    public function items(){
        $date=date("ymd",time());
        $filename = self::Upload("items", "file");
        echo json_encode(array("url" => "upload/items/{$date}/{$filename}"));
    }
        /* 后台设置前端图片存储*/
    public function manage(){
        $date=date("ymd",time());
        $filename = self::Upload("items", "file");
        echo json_encode(array("url" => "upload/zhicms/{$date}/{$filename}"));
    }
    /* 用户账户图片存储*/
    public function user(){
         $filename = self::Upload("user", "file");
         $date=date("ymd",time());
         echo json_encode(array("url" => "upload/user/{$date}/{$filename}"));
    }
     /* 管理员账户图片存储*/
    public function manageuser(){
         $filename = self::Upload("manageuser", "file");
         $date=date("ymd",time());
         echo json_encode(array("url" => "upload/manageuser/{$date}/{$filename}"));
    }

    /*编辑器图片存储*/
    public function article(){
        header('Content-type: text/html; charset=UTF-8');
        $filename = self::Upload("article", "imgFile");
        $json = new \ZhiCms\ext\Services_JSON();
        echo $json->encode(array('error' => 0, 'url' => "upload/article/{$filename}"));
    }

    /*发现封面图片存储*/
    public function articlepic(){
         $filename = self::Upload("articlepic", "file");
         $date=date("ymd",time());
         echo json_encode(array("url" => "upload/articlepic/{$date}/{$filename}"));
    }

    /*发现图标图片存储*/
    public function findtype(){
         $filename = self::Upload("findtype", "file");
         $date=date("ymd",time());
         echo json_encode(array("url" => "upload/findtype/{$date}/{$filename}"));
    }

     /*幻灯广告图片存储*/
    public function huan(){
        $date=date("ymd",time());
         $filename = self::Upload("huan", "file");
         echo json_encode(array("url" => "upload/huan/{$date}/{$filename}"));
    }

     /*单页编辑器图片存储*/
    public function page(){
        header('Content-type: text/html; charset=UTF-8');
        $filename = self::Upload("page", "imgFile");
        $json = new \ZhiCms\ext\Services_JSON();
        $date=date("ymd",time());
        echo $json->encode(array('error' => 0, 'url' => "upload/page/{$date}/{$filename}"));
    }

    /* 礼物图片存储*/
    public function gift(){
        $date=date("ymd",time());
        $filename = self::Upload("gift", "file");
        echo json_encode(array("url" => "upload/gift/{$date}/{$filename}"));
    }

    /* 攻略图片存储*/
    public function gonglue(){
        $date=date("ymd",time());
        $filename = self::Upload("gonglue", "file");
        echo json_encode(array("url" => "upload/gonglue/{$date}/{$filename}"));
    }
    
    /* 问答图片存储*/
    public function question(){
        $date=date("ymd",time());
        $filename = self::Upload("question", "file");
        echo json_encode(array("url" => "upload/question/{$date}/{$filename}"));
    }
   


  

   
}