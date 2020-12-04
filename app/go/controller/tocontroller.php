<?php
namespace app\go\controller;


class toController extends \app\base\controller\BaseController {
	



  
  
    public function wjp(){
  	  $id=$this->arg('id');
  	  $type=$this->arg('type');
      if(!is_numeric($id)){ exit('error');}
	  include CONFIG_PATH . 'apiset.php';
	   include CONFIG_PATH . 'siteconfig.php';
	   $newdata= new \ZhiCms\ext\weixin;
	   //$zhuan= new \framework\ext\Zhuan;
	 $host=$Siteinfo['apiurl']."?s=App.getunionurl.hjk";
	 $arr=array ( 
        'apikey' => $api['Apikey'], 
        'goodsid' => $id,  
        'chantag' => $type, 
        );
	   $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
		$url= $data['data']['data'];
		if($type=='vip'){
		 $url= $data['data']['url'];  
		}
    $this->redirect($url); 
	  

  }
  
    public function url(){
  	  $url=$this->arg('url');
	 include CONFIG_PATH . 'apiset.php';
	   include CONFIG_PATH . 'siteconfig.php';
	   $newdata= new \ZhiCms\ext\weixin;
	   $host=$Siteinfo['apiurl']."?s=App.getunionurl.duomai";
	   $arr=array ( 
        'appkey' => $api['siteid'], 
        'appsecret' => $api['hash'],  
        'url' => $url, 
        );
	   $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
	   $tourl=$data['data']['url'];
	   $this->redirect($url); 
	   
	   
	  

  }

}