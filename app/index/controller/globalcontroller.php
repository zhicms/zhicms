<?php
namespace app\index\controller;

class globalController extends \app\base\controller\BaseController {

	//读取分类
	public function type($lock,$id,$table){
		if($lock=="y"){
		 if($id!='null'){
			$where[]=" `id` ={$id}";
			$ret=obj("api/Apidata")->Data_Select($table,$where);
			}else{
			$where[]="1";
			$ret=obj("api/Apidata")->Data_Select($table,$where,"`id` ASC");
			}
			
			return $ret;
		}
	}
	//读取分类
	public function malltype($lock,$id,$table,$type){
		if($lock=="y"){
		 if($type!='null'){
			$where[]=" `mallType` ={$id}";
			$ret=obj("api/Apidata")->Data_Select($table,$where);
			}else{
			$where[]=" `mallType` ={$id}";
			$ret=obj("api/Apidata")->Data_Select($table,$where,"`id` ASC");
			}
			
			return $ret;
		}
	}
	
		//读取分类
	public function yqftype($lock,$id,$table,$type){
		if($lock=="y"){
		 if($type!='null'){
			$where[]=" `mallType` ={$id}";
			$ret=obj("api/Apidata")->Data_Select($table,$where);
			}else{
			$where[]=" `mallType` ={$id}";
			$ret=obj("api/Apidata")->Data_Select($table,$where,"`id` ASC");
			}
			
			return $ret;
		}
	}
	
			//读取分类
	public function yqfmalltype($lock,$id,$table,$type){
		if($lock=="y"){
		 if($type!='null'){
			$where[]=" `id` ={$id}";
			$ret=obj("api/Apidata")->Data_Select($table,$where);
			}else{
			$where[]=" `id` ={$id}";
			$ret=obj("api/Apidata")->Data_Select($table,$where,"`id` ASC");
			}
			
			return $ret;
		}
	}
	
	//读取分类
	public function duomaitype($lock,$id,$table,$type){
		if($lock=="y"){
		 if($type!='null'){
			$where[]=" `country` !='".$id."'";
			//$ret=obj("api/Apidata")->Data_Select($table,$where,"`id` ASC");
			$ret=obj("api/Apidata")->Data_Select($table,$where," RAND() DESC LIMIT 20");
			}else{
			$where[]="`country` ='".$id."'";
			//$ret=obj("api/Apidata")->Data_Select($table,$where,"`id` ASC");
			$ret=obj("api/Apidata")->Data_Select($table,$where," RAND() DESC LIMIT 20");
			}
			
			return $ret;
		}
	}
	//判断联盟类型
	public  function union($lock,$mallid){

		if($lock=="y"){
			$where[] = "  `id` ={$mallid} ";
		    $retmall = obj("api/Apidata")->Data_Select("mall", $where);
		    if($retmall['union']==""){
			exit('该商城未指定联盟类型!');
		    }
		    $where_union[]="`id` ={$retmall['union']}";
		    $retunion=obj("api/Apidata")->Data_Select("union", $where_union);
		    return $retunion["type"];

		}
	}
    
	
	  //加载首页商城
  public function loadmall($lock,$type){

  	  if($lock=="y"){

  	  	$where[]="`yun_home_mall`.union =  `yun_union`.id and `view` ={$type}";
  	  	$ret=obj("api/Apidata")->Data_Select("home_mall`,`yun_union",$where,"`px` ASC");

  	  	foreach ($ret as $key => $value) {

  	  		$newlink=str_replace(array("[TOLINK]"),array($value['link']),base64_decode($value['code']));
  	  		
  	  		$data['link']=$value['link'];
  	  		$data['pic']=$value['pic'];
  	  		$data['code']=$newlink;

  	  		$newdata[]=$data;


  	  	}

  	  	return $newdata;
  	  	
  	  }
  }
  

  
	//读取文章分类
	public function atype($lock,$id){
		if($lock=="y"){
			if($id!='null'){
				$where[]=" `id` ={$id}";
				$nav = obj("api/Apidata")->Data_Select("nav", $where);
			}else{

				$where[]=" 1";
				$nav = obj("api/Apidata")->Data_Select("nav", $where,"`id` DESC ");
			}
			 
             
             return $nav;
		}
	}

	//获取马甲列表
	public function vestlist($lock){
		if($lock=="y"){
		$where[]="`vest` =1";
		$ret=obj("api/Apidata")->Data_Select("user",$where,"`id` DESC");
		return $ret;
		}
	}

	//获取用户信息 
	public function finduser($lock,$uid,$model='null'){
		if($lock=="y"){
		  //获取用户信息
		   if($model=='cookie'){
		   	 $where[]="  `mobile` LIKE  '{$uid}'";
		   	}else{
		   	  $where[]=" `id` ={$uid}";
		   	}
		   $ret=obj("api/Apidata")->Data_Select("user",$where);
		   return $ret;
		}
	}

	//获取全部分类
   public function getlist($table,$lock,$pid){

   	  if($lock=="y"){
   	  	$where[]="`pid` ={$pid}";
		$send = obj("api/Apidata")->Data_Select($table, $where,"`id` ASC  ");

	    return $send;
   	  }
   }

   //获取攻略全部分类
   public function getgllist($table,$lock){
   	 if($lock=="y"){
   	  	$where[]="1";
		$send = obj("api/Apidata")->Data_Select($table, $where,"`id` ASC  ");

	    return $send;
   	  }
   }
      public function cid($cid){

    	 if($cid=="1"){
    	 	return "女装";
    	 }
    	  if($cid=="2"){
    	 	return "母婴";
    	 }
    	  if($cid=="3"){
    	 	return "化妆品";
    	 }
    	  if($cid=="4"){
    	 	return "居家";
    	 }
    	  if($cid=="5"){
    	 	return "鞋包配饰";
    	 }
    	  if($cid=="6"){
    	 	return "美食";
    	 }
    	  if($cid=="7"){
    	 	return "文体车品";
    	 }
    	  if($cid=="8"){
    	 	return "数码家电";
    	 }
    	  if($cid=="9"){
    	 	return "男装";
    	 }
    	   if($cid=="10"){
    	 	return "内衣";
    	 }
		 if($cid=="12"){
            return "配饰";
         }
         if($cid=="11"){
            return " 箱包";
         }
         if($cid=="14"){
            return '家装家纺';
         }
          if($cid=="13"){
            return '户外运动';
         }
    }

 
}