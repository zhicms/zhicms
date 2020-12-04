<?php
namespace app\index\controller;


class cheapsController extends \app\base\controller\BaseController {

	public function index(){
	  	
	   include CONFIG_PATH . 'siteconfig.php';
	   
	   if($this->arg("id")&&$this->arg("key")){
	   	
		$key=urldecode(urldecode($this->arg("key")));
	   	$where[]="`id` ={$this->arg('id')} and `title` LIKE  '%{$key}%'";
	   	$baseurl=url($route='index/cheaps/index/id=<id>', $params=array("id"=>$this->arg("id"),"key"=>$key));
	   	$f="&";
	   }elseif($this->arg("id")){
		
	   	$where[]="`cid` ={$this->arg('id')}";
	   	$baseurl=url($route='index/cheaps/index/id=<id>', $params=array("id"=>$this->arg("id")));
	   	$f="&";		   
	   }elseif($this->arg("key")){
        $key=urldecode(urldecode($this->arg("key")));	   
	   	$where[]="`title` LIKE  '%{$key}%'";
	   	$baseurl=url($route='index/cheaps/index/id=<id>', $params=array("key"=>$key));
	   	$f="&";   
	   }else{
	   	$where[]="`del` =0";
	   	$baseurl=url($route='index/cheaps/index', $params=array());
	   	$f="?";
	   }
	   
       
       $Page = obj('api/ApiData')->PageIndex("48", "goods", $where, "`id` DESC", $baseurl,$f);
       $this->Page = $Page;
       $this->tenitems=$Page['count'];
       $this->Siteinfo=$Siteinfo;
      
       

		$this->display();
	  }
	  

	  



	  public function lists($cid,$lock){
	 	if($lock=="y"){
	 		 	if($cid=="1"){
    	 	return  "女装,1";
    	 }
    	  if($cid=="2"){
    	 	return "母婴,2";
    	 }
    	  if($cid=="3"){
    	 	return "化妆品,3";
    	 }
    	  if($cid=="4"){
    	 	return "居家,4";
    	 }
    	  if($cid=="5"){
    	 	return "鞋包配饰,5";
    	 }
    	  if($cid=="6"){
    	 	return "美食,6";
    	 }
    	  if($cid=="7"){
    	 	return "文体车品,7";
    	 }
    	  if($cid=="8"){
    	 	return "数码家电,8";
    	 }
    	  if($cid=="9"){
    	 	return "男装,9";
    	 }
    	   if($cid=="10"){
    	 	return "内衣,10";
    	 }
         if($cid=="12"){
            return "配饰,12";
         }
         if($cid=="11"){
            return " 箱包,11";
         }
         if($cid=="14"){
            return '家装家纺,14';
         }
          if($cid=="13"){
            return '户外运动,13';
         }
	 	}
	  }


	}