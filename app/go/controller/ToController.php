<?php
namespace app\go\controller;


class ToController extends \app\base\controller\BaseController {	

  
    public function wjp(){
  	  $id=$this->arg('id');
  	  $type=$this->arg('type');
      if(!is_numeric($id)){ exit('error');}
	  $api = \app\common\ConfigStore::load('api');
	   $Siteinfo = \app\common\ConfigStore::load('site');
	   $newData= new \ZhiCms\ext\Weixin;
	 $host=$api['apiurl']."?s=App.getunionurl.hjk";
	 $arr=array ( 
        'apikey' => '', 
        'goodsid' => $id,  
        'chantag' => $type, 
        );
	   $rootUrl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->objectArray(json_decode($newData->http($rootUrl)));
		$url= $data['data']['data'];
		if($type=='vip'){
		 $url= $data['data']['url'];  
		}
    $this->redirect($url); 
	  

  }
  
    public function url(){
  	  $url=$this->arg('url');
	 $api = \app\common\ConfigStore::load('api');
	   $Siteinfo = \app\common\ConfigStore::load('site');
	   $newData= new \ZhiCms\ext\Weixin;
	   $host=$api['apiurl']."?s=App.getunionurl.duomai";
	   $arr=array ( 
        'appkey' => '', 
        'appsecret' => '',  
        'url' => $url, 
        );
	   $rootUrl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->objectArray(json_decode($newData->http($rootUrl)));
	   $toUrl=$data['data']['url'];
	   $this->redirect($url); 
	   
	  

  }

}