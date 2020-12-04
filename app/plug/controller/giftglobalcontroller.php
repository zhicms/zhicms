<?php
namespace app\plug\controller;

class giftglobalController extends \app\base\controller\BaseController {

	//读取分类
	public function type($lock,$id,$table){
		if($lock=="y"){
		 if($id!='null'){
			$where[]=" `id` ={$id}";
			$ret=obj("api/Apidata")->Data_Select($table,$where);
			}else{
			$where[]="1";
			$ret=obj("api/Apidata")->Data_Select($table,$where,"`px` ASC");
			}
			
			return $ret;
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

 
}