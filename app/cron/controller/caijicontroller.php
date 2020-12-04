<?php
namespace app\cron\controller;
class caijiController extends \app\base\controller\BaseController
{

    public function caijitb(){
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        include CONFIG_PATH . 'siteconfig.php';
          for($key='1';$key<=5;$key++){
		  self::taobao($key,5);
		  if($key==5){ 
	        exit('ok');
	     }
	  }

    }

    public function taobao($page,$end){
    	  if(!$page){
	        $page='1';
	      }
	      $now=$page;
	      if($end==$now){ 
	       exit('ok');
	     }
		 include CONFIG_PATH . 'siteconfig.php';

        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.list";
	   $arr=array ( 
        'page' => $page,
        'pagesize' => 10, 
        'times' => 1,
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
		//echo json_encode($data);
		$shuju=$data['data']['list'];
		//echo json_encode($shuju);

		
		$sql = sprintf("replace into `yun_goods` (`goodsId`,`itemLink`,`title`,`content`,`cid`,`mainPic`,`originalPrice`,`actualPrice`,`discounts`,`commissionRate`,`couponTotalNum`,`couponReceiveNum`,`couponEndTime`,`couponStartTime`,`couponConditions`,`couponPrice`,`monthSales`,`shopType`) VALUES ");
		foreach($shuju as $item) {
        $itemStr = '(';
        $itemStr .= sprintf("'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'", $item['goodsId'], $item['itemLink'], $item['title'], $item['dtitle'], $item['cid'], $item['mainPic'], $item['originalPrice'], $item['actualPrice'], $item['discounts'], $item['commissionRate'], $item['couponTotalNum'],$item['couponReceiveNum'], $item['couponEndTime'], $item['couponStartTime'], $item['couponConditions'], $item['couponPrice'], $item['monthSales'], $item['shopType']);
        $itemStr .= '),';
        $sql .= $itemStr;
        }
		$sql = rtrim($sql, ',');
        $sql .= ';';
        $sql;
     obj('api/ApiData')->thisquery($sql);

    }
    
        public function caijizdm(){
        ini_set('max_execution_time', '0');
        set_time_limit(0);
          for($key='1';$key<=5;$key++){
		  self::finds($key,5);
		  //self::taobao($key,3);
		  if($key==5){ 
	        exit('ok');
	     }
	  }

    }
    
        public function finds($page,$end){
    	 
	      if(!$page){
	        $page='1';
	      }
	      $now=$page;
	      if($end==$now){ 
	        exit('ok');
	     }
		 include CONFIG_PATH . 'siteconfig.php';

        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.friends";
	   $arr=array ( 
        'page' => $page, 
        'pagesize' => 200,  
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
        $shuju=$data['data']['list'];
		$sql = sprintf("replace into `yun_article` (`goodsId`,`itemLink`,`title`,`content`,`cid`,`mainPic`,`keywords`,`dec`,`view`,`like`,`lock`,`couponEndTime`,`date`) VALUES ");
		foreach($shuju as $item) {
		    $content =urlencode($item['desc'].'<br/>[ZhiCmsUrl]'.$item['itemLink'].'[/ZhiCmsUrl]<br/>'.urldecode($item['circleText']));
        $itemStr = '(';
        $itemStr .= sprintf("'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'", $item['goodsId'], $item['itemLink'], $item['title'], $content, $item['cid'], $item['mainPic'], $item['dtitle'], $item['desc'], 0, 0, 0,$item['couponEndTime'], date("Y-m-d H:i:s",time()));
        $itemStr .= '),';
        $sql .= $itemStr;
        }
		$sql = rtrim($sql, ',');
        $sql .= ';';
		 obj('api/ApiData')->thisquery($sql);
		 
 }
	



	

}