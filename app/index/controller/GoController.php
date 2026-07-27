<?php
namespace app\index\controller;


class GoController extends \app\base\controller\BaseController {

	public function index(){
		header("Content-type: text/html; charset=utf-8"); 
		$id=$this->arg("id");
		if(!is_numeric($id)){
			exit('error');
		}
		$sql[]="  `id` ={$id}";
		$ret=obj("api/ApiData")->dataSelect("yun_skuitems",$sql);

		$where[] = "  `id` ={$ret['shop']} ";
		$retMall = obj("api/ApiData")->dataSelect("yun_mall", $where);
		if($retMall['union']==""){
			exit('该商城未指定联盟类型!');
		}
		$whereUnion[]="`id` ={$retMall['union']}";
		$retUnion=obj("api/ApiData")->dataSelect("yun_union", $whereUnion);


		if($retUnion['type']=="0"){
			$unionCode=base64_decode($retUnion['code']);
			$str=str_replace(array("[TOLINK]"),array($ret['link']), $unionCode);
			// 验证重定向URL合法性，防止开放重定向攻击
			$parsedUrl = parse_url($str);
			$allowedHosts = ['taobao.com', 'tmall.com', 'jd.com', 'pinduoduo.com', 'vip.com'];
			$isAllowed = false;
			if(isset($parsedUrl['host'])){
				foreach($allowedHosts as $host){
					if(strpos($parsedUrl['host'], $host) !== false){
						$isAllowed = true;
						break;
					}
				}
			}
			if($isAllowed){
				header("location:{$str}");
			} else {
				// 记录安全日志
				error_log('Blocked potentially unsafe redirect to: ' . $str);
				exit('invalid redirect');
			}
	        exit;


		}


	}

}