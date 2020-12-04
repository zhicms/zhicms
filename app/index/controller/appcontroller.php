<?php
namespace app\index\controller;


class appController extends \app\base\controller\BaseController {

	public function index(){
		$model=$this->arg("model");
	    $type=$this->arg("type");
		$mall=$this->arg("mall");
		$page=$this->arg("page");
		$keywords=$this->arg("keywords");


		if(!$page || $page<=0){
			$page="1";
		}

		//一个页面显示条数
		$pageN="30";

		if($keywords){

			$where[]="`title` LIKE  '%{$keywords}%'";
		}else{


		if($mall=="index" || !$mall){
		  $where[]="`type` LIKE  '{$model}' AND  `mall` LIKE  '{$model}'";
	    }
	    if($mall=="hot"){
	    	$where[]=" date >DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
	    }
	    if($mall=="mall"){
	    	$where[]="`mall` LIKE  '{$model}' AND  `mallb` ={$type}";
	    }
	    if($mall=="type"){
	    	$where[]="`type` LIKE  '{$model}' AND  `typeb` ={$type}";
	    }

	    }
	 
		$count=obj("api/Apidata")->Data_Count("items",$where);

		//共分几页
		$indexpage=round($count/$pageN);
		$pagesize=($page-1)*$pageN;
		if($keywords){

			$sql[]="`title` LIKE  '%{$keywords}%'";
		}else{

		if($mall=="index" || !$mall) {
		 $sql[]="`type` LIKE  '{$model}' AND  `mall` LIKE  '{$model}'";
	    }
	    if($mall=="hot"){
	     $sql[]=" date >DATE_SUB(NOW(), INTERVAL 30 MINUTE)";	
	    }
	    if($mall=="mall"){
	    	$sql[]="`mall` LIKE  '{$model}' AND  `mallb` ={$type}";
	    }
	    if($mall=="type"){

	    	$sql[]="`type` LIKE  '{$model}' AND  `typeb` ={$type}";
	    }
		
		}


		$ret=obj("api/Apidata")->Data_Select("items",$sql,"`id` DESC LIMIT {$pagesize} , {$pageN}");
		if(empty($ret)){
			exit("nodata");
		}
		foreach ($ret as $key => $value) {
	    $mall= obj("index/global", "controller")->type("y", $value['mallb'], "mall", "id");
	    $date=obj("api/Api")->mdate($value['date']);

	    if($keywords){

	    	if($value['type']=="0" && $value['mall']=="0"){
	    		$shtml='<div class="qufen guoneicss">国内</div>';
	    	}else{
	    		$shtml='<div class="qufen haitaocss">海淘</div>';
	    	}

	    }

		$html='<div class="itemsli"  onclick="openview('.$value['id'].')" >
			   <div class="itemsleftimg">'.$shtml.'<img src="'.$value['pic'].'"></div>
			   <div class="itemsright">
			      <div class="itemstitle">'.$value['title'].'</div>
			      <div class="itemstps"><span class="itemsmall haitaomall">'.$mall['name'].'</span><span class="itemstime">'.$date.' - '.$value['ly'].'</span></div>
			   </div>
			 </div>';

      echo $html;
}

	}

	public function timedata(){

	    $where_30="select * from {pre}items where date >DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        $rettime=obj('api/ApiData')->thisquery($where_30);
        if(empty($rettime)){
        	exit("nodata");
        }
        foreach ($rettime as $key => $value) {
        $key=$key+1;
	    $mall= obj("index/global", "controller")->type("y", $value['mallb'], "mall", "id");
	    $date=obj("api/Api")->mdate($value['date']);
	    $shtml='<div class="qufen xiaoshicss">No.'.$key.'</div>';
	    $html='<div class="itemsli" onclick="openview('.$value['id'].')" >
			   <div class="itemsleftimg">'.$shtml.'<img src="'.$value['pic'].'"></div>
			   <div class="itemsright">
			      <div class="itemstitle">'.$value['title'].'</div>
			      <div class="itemstps"><span class="itemsmall fengyun">'.$mall['name'].'</span><span class="itemstime">'.$date.' - '.$value['ly'].'</span></div>
			   </div>
			 </div>';

      echo $html;

	}
	}

	public function quan(){
		$page=$this->arg("page");
		$keywords=$this->arg("keywords");

		if(!$page || $page<=0){
			$page="1";
		}

		//一个页面显示条数
		$pageN="30";
	    $where[]="`title` LIKE  '%{$keywords}%'";

	    $count=obj("api/Apidata")->Data_Count("youhuiquan",$where);

		//共分几页
		$indexpage=round($count/$pageN);
		$pagesize=($page-1)*$pageN;
		$sql[]="`title` LIKE  '%{$keywords}%'";
		$ret=obj("api/Apidata")->Data_Select("youhuiquan",$sql,"`id` DESC LIMIT {$pagesize} , {$pageN}");
		if(empty($ret)){
			exit("nodata");
		}
		foreach ($ret as $key => $value) {
			$quanlink="http://uland.taobao.com/coupon/edetail?activityId={$value['Quan_id']}&pid={$Siteinfo['pid']}&itemId={$value['GoodsID']}&dx=1";
			$html='<div class="itemsli" onclick=\'openquanlink("'.$quanlink.'")\'>
   <div class="itemsleftimg"><img src="'.$value['Pic'].'"></div>
   <div class="itemsright">
      <div class="itemstitle">'.$value['D_title'].'</div>
      <div class="itemstps"><span class="itemsmall">券后￥<strong style="font-size:16px;">'.$value['Price'].'</strong></span><span class="itemstime"><div class="quanbtn">领'.$value['Quan_price'].'元券</div></span></div>
   </div>
 </div>';

			echo $html;

		}

	}

  public function checknum(){

       $maxid=$this->arg("maxid");
       $model=$this->arg("model");
       if(!is_numeric($maxid)){
        exit("erorr");
       }

        $maxidsql="SELECT MAX( id ) as maxid FROM  `{pre}items`  where `type` LIKE  '{$model}' AND  `mall` LIKE  '{$model}'";
        $mysqlmaxid=obj("api/ApiData")->thisquery($maxidsql);
        $newmaxid=$mysqlmaxid['0']['maxid']-$maxid;
        echo $newmaxid;


    }

    public function maxid(){

        $maxidsql="SELECT MAX( id ) as maxid FROM  `{pre}items`  where `type` LIKE  '0' AND  `mall` LIKE  '0'";
        $maxid=obj("api/ApiData")->thisquery($maxidsql);

        $maxidsql_haitao="SELECT MAX( id ) as maxid FROM  `{pre}items`  where `type` LIKE  '1' AND  `mall` LIKE  '1'";
        $maxid_haitao=obj("api/ApiData")->thisquery($maxidsql_haitao);

    	$html='<div id="index_maxid">'.$maxid['0']['maxid'].'</div><div id="haitao_maxid">'.$maxid_haitao['0']['maxid'].'</div>';
    	echo $html;
    }

}
