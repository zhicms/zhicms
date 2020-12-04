<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class caijiController extends \app\base\controller\BaseController
{
    public function index()
    {
    	$this->pagetext=array("宝贝采集","管理天猫淘宝宝贝");
         include CONFIG_PATH . 'siteconfig.php';
        $page=$this->arg('page');
        $cid=$this->arg('cid');
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.list";
	   $arr=array ( 
        'page' => $page, 
        'pagesize' => 50,  
        'cids' => $cid, 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
        $pages = new \ZhiCms\ext\page;

    $root="index.php?r=manage/caiji/index&cid=".$cid;
    $url = $root . "&page={page}";
    $count = $data['data']['total'];
    $list = $data['data']['list'];
    $listRows = 50;
    $array = array('list' => $list, 'count' => $count, 'pages' => $pages->show($url, $count, $listRows));
    $this->Page = $array;
    $this->todays=$data['data']['total'];

        $this->display();
    }
	
	    public function find()
    {
    	$this->pagetext=array("文章采集","采集适用于推广的文章");
    	include CONFIG_PATH . 'siteconfig.php';
        $page=$this->arg('page');
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.friends";
	   $arr=array ( 
        'page' => $page, 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
        $pages = new \ZhiCms\ext\page;
        
        
    $root="index.php?r=manage/caiji/find";
    $url = $root . "&page={page}";
    $count = $data['data']['total'];
    $list = $data['data']['list'];
    $listRows = 50;
    $array = array('list' => $list, 'count' => $count, 'pages' => $pages->show($url, $count, $listRows));
    $this->Page = $array;
    $this->todays=$data['data']['total'];
    $this->display();
    }

    
    public function dtk(){
        include CONFIG_PATH . 'siteconfig.php';
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.list";
	   $arr=array ( 
        'page' => $page, 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
    	$pagenum=ceil($data['data']['total']/50);      //获得总页数 pagenum
    	$this->Pagenum = $pagenum;
    	$this->display();

    }

	
	

    
	    public function pyq(){
include CONFIG_PATH . 'siteconfig.php';
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.friends";
		$data=obj("api/Api")->object_array(json_decode($newdata->http($host)));
    	$pagenum=ceil($data['data']['total']/10);      //获得总页数 pagenum
    	$this->Pagenum = $pagenum;

    	$this->display();

    }


    public function pyqcj(){
    	 $page=$this->arg('nowpage');
	      if(!$page){
	        $page='1';
	      }
	      $t=$this->arg('t');
	      $e=$this->arg('e');
	      $now=$page;
	      if($e==$now){ 
	        exit('ok');
	     }
		 include CONFIG_PATH . 'siteconfig.php';

        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.friends";
	   $arr=array ( 
        'page' => $page, 
        'pagesize' => 10,  
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
  //echo $sql;
		 obj('api/ApiData')->thisquery($sql);
		 
 }
	
	

	

	
	    public function dtkcaiji(){
	        $page=$this->arg('nowpage');
	      if(!$page){
	        $page='1';
	      }
	      $t=$this->arg('t');
	      $e=$this->arg('e');
	      $now=$page;
	      if($e==$now){ 
	        exit('ok');
	     }
		 include CONFIG_PATH . 'siteconfig.php';

        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.list";
	   $arr=array ( 
        'page' => $page,
        'pagesize' => 50, 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
		//echo json_encode($data);
		$shuju=$data['data']['list'];
		//echo json_encode($shuju);

		
		$sql = sprintf("replace into `yun_goods` (`goodsId`,`itemLink`,`title`,`content`,`cid`,`mainPic`,`originalPrice`,`actualPrice`,`discounts`,`commissionRate`,`couponTotalNum`,`couponReceiveNum`,`couponEndTime`,`couponStartTime`,`couponConditions`,`couponPrice`,`monthSales`,`shopType`) VALUES ");
		foreach($shuju as $item) {
        $itemStr = '(';
        $itemStr .= sprintf("'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'", $item['goodsId'], $item['itemLink'], $item['title'], $item['desc'], $item['cid'], $item['mainPic'], $item['originalPrice'], $item['actualPrice'], $item['discounts'], $item['commissionRate'], $item['couponTotalNum'],$item['couponReceiveNum'], $item['couponEndTime'], $item['couponStartTime'], $item['couponConditions'], $item['couponPrice'], $item['monthSales'], $item['shopType']);
        $itemStr .= '),';
        $sql .= $itemStr;
        }
		$sql = rtrim($sql, ',');
        $sql .= ';';
     obj('api/ApiData')->thisquery($sql);
		 
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