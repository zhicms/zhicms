<?php
namespace app\index\controller;
//error_reporting('0');
class searchController extends \app\base\controller\BaseController
{
    public function index(){
         include CONFIG_PATH . 'siteconfig.php';
        $content=urldecode($this->arg("content"));
 
		$newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.Search.zfy";
        $shuju=array ( 'content' => $content);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($host,$shuju,'POST')));
		
		if($data['data']['s']==1 && $data['data']['type']=="taobao" && $data['data']['info']!=null){
		    $url=url($route='index/view/detail/id=<id>', $params=array('id'=>$data['data']['info']['goodsId']));
		    $this->title=$data['data']['info']['title'];
		    $this->tourl=$url;
		    $this->display('app/index/view/search/to');
		    //$this->display();
		    exit();
		}
		
       if($data['data']['s']==1 && ($data['data']['type']=="pdd" ||$data['data']['type']=="jd") && $data['data']['info']!=null){
		    $url=url($route='index/view/view/id=<id>/type=<type>', $params=array('id'=>$data['data']['info']['goods_id'],'type'=>$data['data']['type']));
		    $this->title=$data['data']['info']['goods_name'];
		    $this->tourl=$url;
		   
		   //$this->display();
		   	$this->display('app/index/view/search/to');
		    exit();
		}

	if($data['data']['s']==1 && $data['data']['type']=="vip" && $data['data']['info']!=null){
		    $url=url($route='index/view/vip/id=<id>', $params=array('id'=>$data['data']['info']['goodsId']));
		    $this->title=$data['data']['info']['goodsName'];
		    $this->tourl=$url;
		    $this->display('app/index/view/search/to');
		   //$this->display();
		    exit();
	}
	
	
		if($data['data']['s']==1 && $data['data']['type']=="list" ){
		    $pages =  new \ZhiCms\ext\page_index;
             $key=urldecode($content);
		    $this->key=urldecode($content);
		       $root="so.html?content=".$key;
    $url = $root . "&page={page}";
    $count = $data['data']['total'];
    $list = $data['data']['list'];
    $listRows = 20;
    $array = array('list' => $list, 'count' => $count, 'pages' => $pages->show($url, $count, $listRows));
    $this->Page = $array;
		   $this->display('app/index/view/search/list');
		    exit();
	}
		

		
	    //var_dump($data);
        $url=url($route='index/index/index/', $params=array());
        $this->title=$data['data']['r'];
		$this->tourl=$url;
    	$this->display();
    }

	

	
	

}