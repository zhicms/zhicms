<?php
namespace app\index\controller;


class goController extends \app\base\controller\BaseController {

	public function index(){
		header("Content-type: text/html; charset=utf-8"); 
		$id=$this->arg("id");
		if(!is_numeric($id)){
			exit('error');
		}
		$sql[]="  `id` ={$id}";
		$ret=obj("api/Apidata")->Data_Select("items",$sql);

		//获取商城联盟模型
		$where[] = "  `id` ={$ret['mallb']} ";
		$retmall = obj("api/Apidata")->Data_Select("mall", $where);
		if($retmall['union']==""){
			exit('该商城未指定联盟类型!');
		}
		$where_union[]="`id` ={$retmall['union']}";
		$retunion=obj("api/Apidata")->Data_Select("union", $where_union);


		//判断代码执行方式 如果是URL方式
		if($retunion['type']=="0"){
			$unioncode=base64_decode($retunion['code']);
			$str=str_replace(array("[TOLINK]"),array($ret['link']), $unioncode);
	        header("location:{$str}");
	        exit;


		}

		//if($retunion['type']=="1"){
		//	$unioncode=base64_decode($retunion['code']);
		//	$str=str_replace(array("[TOLINK]"),array($ret['link']), $unioncode);
			//print_r($str);

	//	}

		//$this->mall=$mall;
		//$this->link=$ret['link'];
		//$this->display();
		
	}

}
