<?php
namespace app\index\controller;


class AppController extends \app\base\controller\BaseController {

	public function index(){
		$model=$this->arg("model");
	    $type=$this->arg("type");
		$mall=$this->arg("mall");
		$page=$this->arg("page");
		$keywords=$this->arg("keywords");


		if(!$page || $page<=0){
			$page="1";
		}

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
	 
		$count=obj("api/ApiData")->dataCount("items",$where);

		$indexpage=round($count/$pageN);
		$pageSize=($page-1)*$pageN;
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


		$ret=obj("api/ApiData")->dataSelect("items",$sql,"`id` DESC LIMIT {$pageSize} , {$pageN}");
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

	public function timeData(){

	    $where_30="select * from {pre}items where date >DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        $retTime=obj('api/ApiData')->thisQuery($where_30);
        if(empty($retTime)){
        	exit("nodata");
        }
        foreach ($retTime as $key => $value) {
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

		$pageN="30";
	    $where[]="`title` LIKE  '%{$keywords}%'";

	    $count=obj("api/ApiData")->dataCount("youhuiquan",$where);

		$indexpage=round($count/$pageN);
		$pageSize=($page-1)*$pageN;
		$sql[]="`title` LIKE  '%{$keywords}%'";
		$ret=obj("api/ApiData")->dataSelect("youhuiquan",$sql,"`id` DESC LIMIT {$pageSize} , {$pageN}");
		if(empty($ret)){
			exit("nodata");
		}
		foreach ($ret as $key => $value) {
			include CONFIG_PATH . 'apiset.php';
			$quanLink="http://uland.taobao.com/coupon/edetail?activityId={$value['Quan_id']}&pid={$api['tb_pid']}&itemId={$value['GoodsID']}&dx=1";
			$html='<div class="itemsli" onclick=\'openquanlink("'.$quanLink.'")\'>
   <div class="itemsleftimg"><img src="'.$value['Pic'].'"></div>
   <div class="itemsright">
      <div class="itemstitle">'.$value['D_title'].'</div>
      <div class="itemstps"><span class="itemsmall">券后￥<strong style="font-size:16px;">'.$value['Price'].'</strong></span><span class="itemstime"><div class="quanbtn">领'.$value['Quan_price'].'元券</div></span></div>
   </div>
 </div>';

			echo $html;

		}

	}

  public function checkNum(){
       $maxid = $this->arg("maxid");
       $model = $this->arg("model");

       if (!ctype_digit($maxid) || $maxid <= 0) {
        exit("error");
       }

       if (!in_array($model, array('0', '1'))) {
        exit("error");
       }

       $params = array(':type' => $model, ':mall' => $model);
       $mysqlMaxid = obj("api/ApiData")->thisQuery("SELECT MAX(id) as maxid FROM `{pre}items` WHERE `type` = :type AND `mall` = :mall", $params);
       $newMaxid = $mysqlMaxid['0']['maxid'] - $maxid;
       echo $newMaxid;
    }

    public function maxId(){
        $mysqlMaxid = obj("api/ApiData")->thisQuery("SELECT MAX(id) as maxid FROM `{pre}items` WHERE `type` = '0' AND `mall` = '0'");
        $mysqlMaxidHaitao = obj("api/ApiData")->thisQuery("SELECT MAX(id) as maxid FROM `{pre}items` WHERE `type` = '1' AND `mall` = '1'");

        $maxid = isset($mysqlMaxid['0']['maxid']) ? $mysqlMaxid['0']['maxid'] : 0;
        $maxidHaitao = isset($mysqlMaxidHaitao['0']['maxid']) ? $mysqlMaxidHaitao['0']['maxid'] : 0;

        $html = '<div id="index_maxid">'.$maxid.'</div><div id="haitao_maxid">'.$maxidHaitao.'</div>';
        echo $html;
    }

}