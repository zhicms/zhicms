<?php
namespace app\manage\controller;
error_reporting(0);
use \app\base\controller\ManageControllerTrait;
class FileController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;
    //图片附件上传接口
    public function Upload($obj, $originName){

    $this->checkManageSession();

        $up = new \ZhiCms\ext\Upload();
         $date=date("ymd",time());
        $up->set("path", ROOT_PATH . "/upload/{$obj}/{$date}");
        $up->set("maxsize", 10000000);
        $up->set("allowtype", array("gif", "png", "jpg", "jpeg","mp3"));
        $up->set("israndname", true);
        if ($up->upload($originName)) {
            $filename = $up->getFileName();
        } else {
            $filename = $up->getErrorMsg();
            //self::kindalert($filename);
        }
        return $filename;
    }

    /* editor.md 编辑器图片上传（返回 editor.md 标准 JSON: {success, message, url}） */
    public function editor(){
        $this->checkManageSession();
        header('Content-type: application/json; charset=UTF-8');

        $type = isset($_GET['type']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['type']) : 'article';
        $date = date("ymd", time());

        $up = new \ZhiCms\ext\Upload();
        $up->set("path", ROOT_PATH . "/upload/{$type}/{$date}");
        $up->set("maxsize", 10000000);
        $up->set("allowtype", array("gif", "png", "jpg", "jpeg", "mp3"));
        $up->set("israndname", true);

        if ($up->upload("editormd-image-file")) {
            $filename = $up->getFileName();
            echo json_encode(array('success' => 1, 'message' => '上传成功', 'url' => "upload/{$type}/{$date}/{$filename}"));
        } else {
            echo json_encode(array('success' => 0, 'message' => $up->getErrorMsg()));
        }
    }

    /* 商品图片存储*/
    public function items(){

    $this->checkManageSession();

        $date=date("ymd",time());
        $filename = self::Upload("items", "file");
        echo json_encode(array("url" => "upload/items/{$date}/{$filename}"));
    }
        /* 后台设置前端图片存储*/
    public function manage(){

    $this->checkManageSession();

        $date=date("ymd",time());
        $filename = self::Upload("manage", "file");
        echo json_encode(array("url" => "upload/manage/{$date}/{$filename}"));
    }
    /* 用户账户图片存储*/
    public function user(){

    $this->checkManageSession();

         $date=date("ymd",time());
         $filename = self::Upload("user", "file");
         echo json_encode(array("url" => "upload/user/{$date}/{$filename}"));
    }
     /* 管理员账户图片存储*/
    public function manageuser(){

    $this->checkManageSession();

         $date=date("ymd",time());
         $filename = self::Upload("manageuser", "file");
         echo json_encode(array("url" => "upload/manageuser/{$date}/{$filename}"));
    }

    /*编辑器图片存储*/
    public function article(){

    $this->checkManageSession();

        header('Content-type: text/html; charset=UTF-8');
        $filename = self::Upload("article", "imgFile");
        $json = new \ZhiCms\ext\Services_JSON();
        echo $json->encode(array('error' => 0, 'url' => "upload/article/{$filename}"));
    }

    /*发现封面图片存储*/
    public function articlepic(){

    $this->checkManageSession();

         $date=date("ymd",time());
         $filename = self::Upload("articlepic", "file");
         echo json_encode(array("url" => "upload/articlepic/{$date}/{$filename}"));
    }

    /*发现图标图片存储*/
    public function findtype(){

    $this->checkManageSession();

         $date=date("ymd",time());
         $filename = self::Upload("findtype", "file");
         echo json_encode(array("url" => "upload/findtype/{$date}/{$filename}"));
    }

     /*幻灯广告图片存储*/
    public function huan(){

    $this->checkManageSession();

        $date=date("ymd",time());
         $filename = self::Upload("huan", "file");
         echo json_encode(array("url" => "upload/huan/{$date}/{$filename}"));
    }

     /*单页编辑器图片存储*/
    public function page(){

    $this->checkManageSession();

        header('Content-type: text/html; charset=UTF-8');
        $date=date("ymd",time());
        $filename = self::Upload("page", "imgFile");
        $json = new \ZhiCms\ext\Services_JSON();
        echo $json->encode(array('error' => 0, 'url' => "upload/page/{$date}/{$filename}"));
    }

}