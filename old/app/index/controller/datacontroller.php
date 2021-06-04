<?php
namespace app\index\controller;


class dataController extends \app\base\controller\BaseController {

    public function index(){
    	header("Content-type: text/html; charset=utf-8");
    	include CONFIG_PATH . 'siteconfig.php';
    	$token= new \ZhiCms\ext\weixin;


        if($this->arg("key")!=$Siteinfo['key']){
            exit("key error!");
        }

    	$ret=obj("api/Api")->object_array(json_decode($token->http($url)));

    	foreach (array_reverse($ret) as $key => $value) {
    	  $sql = "SELECT * FROM  `{pre}items`  WHERE  `link` LIKE  '{$value['link']}'";
           $c = obj('api/ApiData')->thisquery($sql);
           if(empty($c)){
    		$data['title']=$value['title'];
    		$data['type']=$value['country'];
    		$data['typeb']=$value['type'];
    		$data['mall']=$value['country'];
    		$data['mallb']=$value['shop'];
    		$data['ly']=$value['website'];
    		$data['link']=$value['link'];
    		$data['pic']=$value['pic'];
    		$data['mybody']=$value['body'];
    		$data['date']=$value['date'];
    		obj("api/Apidata")->Inserts("items", $data);
    		echo 'ok'."<br>";
    	}

    	}

    	

    }
}