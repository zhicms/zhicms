<?php
namespace app\index\controller;


class hotController extends \app\base\controller\BaseController {

	public function index(){
	   //热门点击
	   $where_hot[]="1";
       $hot=obj("api/Apidata")->Data_Select("items",$where_hot,"`view` DESC LIMIT 0 , 10");
       $this->hot=$hot;
       $where[]=" date >DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
       $baseurl=url($route='index/hot/index', $params=array());
       $Page = obj('api/ApiData')->PageIndex("50", "items", $where, "`id` DESC", $baseurl);
       $this->Page = $Page;

       //今日发布+预计
       $date=date("Y-m-d",time());
       $todaysenddata[]="  `date` LIKE  '%{$date}%' and `type` LIKE  '0' AND  `mall` LIKE  '0'";
       $this->todaysenddata=obj("api/ApiData")->Data_Count("items",$todaysenddata);
       $this->country="国内";
	   $this->display();

	}


}