<?php
namespace app\index\controller;
//error_reporting('0');
class brandController extends \app\base\controller\BaseController
{
    public function index(){
    include CONFIG_PATH . 'siteconfig.php';
        $page=$this->arg('page');
        $cid=$this->arg('cid');
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.brandlist";
	   $arr=array ( 
        'page' => $page, 
        'pagesize' => 10,  
        'cids' => $cid, 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
      $pages =  new \ZhiCms\ext\page_index;

    $root="brand.html?cid=".$cid;
    $url = $root . "&page={page}";
    $count = $data['data']['total'];
    $list = $data['data']['list'];
    $listRows = 50;
    $array = array('list' => $list, 'count' => $count, 'pages' => $pages->show($url, $count, $listRows));
    $this->Page = $array;

    	$this->display();
    }	
        public function view(){
    include CONFIG_PATH . 'siteconfig.php';
        $page=$this->arg('page');
        $brandid=$this->arg('id');
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.brandview";
	   $arr=array ( 
        'page' => $page, 
        'pagesize' => 100,  
        'brandid' => $brandid, 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
      $pages =  new \ZhiCms\ext\page_index;

    $root="brand-.'$brandid'.html";
    $url = $root . "?page={page}";
    $count = count($data['data']['list']);
    $list = $data['data']['list'];
    $listRows = 48;
    $array = array('list' => $list, 'count' => $count, 'pages' => $pages->show($url, $count, $listRows));
    $this->title = $data['data']['brandname'];
    $this->desc = $data['data']['branddesc'];
    $this->Page = $array;

    	$this->display();
    }

  }