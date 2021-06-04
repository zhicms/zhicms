<?php
namespace app\index\controller;
//error_reporting('0');
class indexController extends \app\base\controller\BaseController
{
    public function index(){
if(!file_exists(CONFIG_PATH.'install.lock')){
	$this->redirect("index.php?r=install");
}

    include CONFIG_PATH . 'siteconfig.php';

        //半个小时热门
        $where_30m[]="`date`> DATE_SUB(NOW(),INTERVAL  30 MINUTE)";
        $minute=obj("api/Apidata")->Data_Select("article",$where_30m,"`date` DESC LIMIT 0 , 9");
        $this->minute=$minute;


        //热门点击
        $where_hot[]="1";
        $hot=obj("api/Apidata")->Data_Select("article",$where_hot,"`id` DESC LIMIT 0 , 10");
        $this->hot=$hot;

        //7天收录
        $tenitems[]=" date_sub(curdate(), INTERVAL 7 DAY) <= date(`date`) ";
        $this->tenitems=obj("api/ApiData")->Data_Count("article",$tenitems);

        //今日发布+预计
        $date=date("Y-m-d",time());
        $todaysenddata[]=" `date` LIKE  '%{$date}%'";
        $this->todaysenddata=obj("api/ApiData")->Data_Count("article",$todaysenddata);
        $this->country="国内";

        $where[]=" 1";
        $baseurl=url($route='index/index/index', $params=array());
       

        if($this->arg("list")){
			$listid=$this->arg('list');

		//$lists=urldecode($this->arg("list"));
        $where[]="`cid` ='{$listid}'";
        $baseurl=url($route='index/index/index/list=<list>', $params=array("list"=>$this->arg("list")));

        }


        $Page = obj('api/ApiData')->PageIndex("10", "article", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;

        //max id
        $maxidsql="SELECT MAX( id ) as maxid FROM  `{pre}article`";
        $maxid=obj("api/ApiData")->thisquery($maxidsql);
        $this->maxid=$maxid['0']['maxid'];


        


    	$this->display();
    }	


    public  function welcomecookie(){

    	setcookie("welcome", "zhicms", time()+6600);
    }

    public function log(){

        echo "callback({ data:\"ok\"})";
    }

    public function checknum(){

       $maxid=$this->arg("maxid");
       if(!is_numeric($maxid)){
        exit("erorr");
       }

        $maxidsql="SELECT MAX( id ) as maxid FROM  `{pre}article`";
        $mysqlmaxid=obj("api/ApiData")->thisquery($maxidsql);
        $newmaxid=$mysqlmaxid['0']['maxid']-$maxid;
        echo $newmaxid;


    }


    //预估数据计算
    public function estimate(){

       

        //昨日此时发布了多少数据
        $lastdaysql="SELECT count(*) as lastdaycount FROM {pre}article WHERE  TO_DAYS( NOW( ) ) - TO_DAYS( date) <= 1";
        $lastret=obj("api/ApiData")->thisquery($lastdaysql);

        //今日数据
        $todaysql="select count(*) as todaycount from {pre}article where  to_days(date) = to_days(now())";
        $todayret=obj("api/ApiData")->thisquery($todaysql);
        $newcount=$lastret['0']['lastdaycount']+$todayret['0']['todaycount'];

        if($newcount<=0){
            $newcount="0";
            $bfb="0";
        }else{
            $newcount=$newcount+$todaycount;
            $bfb=round($todayret['0']['todaycount']/$lastret['0']['lastdaycount']*100);
        }

        
        return array("bfb"=>$bfb,"count"=>$newcount);

    }
        public function view(){
       $id=$this->arg("id");
       if(!is_numeric($id)){
        exit("error");
       }

       $where[] = "  `id` ={$id} ";
       $view = obj("api/Apidata")->Data_Select("article", $where);
	/*if($view['content']=="" || $view['content']==null){
	   $url=url($route='go/to/url/id=<id>', $params=array('id'=>$id));	
	   $this->redirect($url);
      exit;	   
	 }*/
	 //$strSubject = "abc【111】abc【222】abc【333】abc";
 
       $newbody=preg_replace_callback('/\[ZhiCmsUrl](.+?)\[\/ZhiCmsUrl]/','self::find_items',urldecode($view['content']));
	  // $newbody=preg_replace_callback('/[ZhiCmsUrl]*([\s\S]*?)[\/ZhiCmsUrl]/i','11',urldecode($view['content']));
        $this->newbody=$newbody;
       $this->view=$view;
	   
      

       //判断商品是国内还是海淘
       if($view['mallType']=="0" ){
       	

       	 //国内最热点击榜
        $where_hot[]="1";
        $hot=obj("api/Apidata")->Data_Select("article",$where_hot,"`view` DESC LIMIT 0 , 10");
        $this->hot=$hot;

        //推荐相关分类的商品
        $twhere[]=" 1";

         //商城推荐
        $malltwhere[]="  1";

       }else{
       

        //海淘最热点击榜
        $where_hot[]="1";
        $hot=obj("api/Apidata")->Data_Select("article",$where_hot,"`view` DESC LIMIT 0 , 10");
        $this->hot=$hot;

        //推荐相关分类的商品
        $twhere[]=" 1 ";
        
         //商城推荐
         $malltwhere[]="  1";
       }
      
        $this->tret= obj("api/Apidata")->Data_Select("article", $twhere,"rand() LIMIT 0 , 5");

       $this->malltret= obj("api/Apidata")->Data_Select("article", $malltwhere,"rand() LIMIT 0 , 5");

       $this->display();

    }
    
    
       public function find_items($id){
        error_reporting('0');
        if(!$id){
          // self::e_404();
           exit;
        }
    	include CONFIG_PATH . 'siteconfig.php';
		$newdata= new \ZhiCms\ext\weixin;
        $content=urldecode($this->arg("content"));
        foreach ($id as $value) {
        preg_match_all('/http[s]{0,1}:\/\/([\w.]+\/?)\S*/', $value, $itemsid);
        $itemsurl= $itemsid['0']['0'];
        $itemsurl=preg_replace('/\[\/ZhiCmsUrl]/','',$itemsurl);
        $content=urldecode($itemsurl);
        $host=$Siteinfo['apiurl']."?s=App.Search.zfy";
        $shuju=array ( 'content' => $content);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($host,$shuju,'POST')));
		if($data['data']['s']==1 && $data['data']['type']=="taobao" && $data['data']['info']!=null){//如果返回的是淘宝产品
		$url= url($route='go/tb/itemiid/id=<id>', $params=array('id'=>$data['data']['info']['goodsId']));
		$html = '<section style="padding:5px;color: #333;float: left;width:100%">
<section style="box-shadow: 0px 0px 6px rgb(211,211,211);border:1px solid #e5e5e5 ;border-radius:20px ;">
<section style="padding-top: 2em;"><section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;margin:0.5em auto;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section></section>
<section style="text-align: center;margin-top: -1.5em;">
<section style="text-align: center;padding:0px 15px;font-size: 20px;font-weight: bold;display: inline-block;background: rgb(255,255,255);">
'.$data['data']['info']['title'].'</section></section><section style="padding:2em 1em 2em 1em;">
<section style="border-radius:20px ;">
<img src="'.$data['data']['info']['mainPic'].'" width="100%" height=auto />
</section><p>原价：'.$data['data']['info']['originalPrice'].'元&nbsp;&nbsp;&nbsp;券后价：<font style="color:red;">'.$data['data']['info']['actualPrice'].'</font>元</p>销量：'.$data['data']['info']['monthSales'].'  &nbsp;&nbsp;&nbsp;优惠券金额：'.$data['data']['info']['couponPrice'].'元<br>
   <a href="'.$url.'" target="_blank" style=" width: 120px;
    height: 36px;
    line-height: 36px;
    text-align: center;
    background: #ff2e54;
    border-radius: 2px;
    display: inline-block;
    color: #fff;
    font-size: 14px;">立即去看看</a>
</section></section></section>';
 return $html;
	}
	
	if($data['data']['s']==1 && ($data['data']['type']=="pdd" ||$data['data']['type']=="jd") && $data['data']['info']!=null){
	$url= url($route='go/to/wjp/id=<id>/type=<type>', $params=array('id'=>$data['data']['info']['goods_id'],'type'=>$data['data']['type']));
	$type = $data['data']['type']=='pdd'?'拼多多':'京东';
		$html = '<section style="padding:5px;color: #333;float: left;width:100%">
<section style="box-shadow: 0px 0px 6px rgb(211,211,211);border:1px solid #e5e5e5 ;border-radius:20px ;">
<section style="padding-top: 2em;"><section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;margin:0.5em auto;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section></section>
<section style="text-align: center;margin-top: -1.5em;">
<section style="text-align: center;padding:0px 15px;font-size: 20px;font-weight: bold;display: inline-block;background: rgb(255,255,255);">
'.$data['data']['info']['goods_name'].'</section></section><section style="padding:2em 1em 2em 1em;">
<section style="border-radius:20px ;">
<img src="'.$data['data']['info']['picurl'].'" width="100%" height=auto />
</section><p>原价：'.$data['data']['info']['price'].'元&nbsp;&nbsp;&nbsp;券后价：<font style="color:red;">'.$data['data']['info']['price_after'].'</font>元</p>销量：'.$data['data']['info']['sales'].'  &nbsp;&nbsp;&nbsp;优惠券金额：'.$data['data']['info']['discount'].'元<br>
   <a href="'.$url.'" target="_blank" style=" width: 120px;
    height: 36px;
    line-height: 36px;
    text-align: center;
    background: #ff2e54;
    border-radius: 2px;
    display: inline-block;
    color: #fff;
    font-size: 14px;">立即'.$type.'去看看</a>
</section></section></section>';
 return $html;
		}
			if($data['data']['s']==1 && $data['data']['type']=="vip" && $data['data']['info']!=null){
	$url= url($route='go/to/wjp/id=<id>/type=<type>', $params=array('id'=>$data['data']['info']['goodsId'],'type'=>'vip'));
		$html = '<section style="padding:5px;color: #333;float: left;width:100%">
<section style="box-shadow: 0px 0px 6px rgb(211,211,211);border:1px solid #e5e5e5 ;border-radius:20px ;">
<section style="padding-top: 2em;"><section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;margin:0.5em auto;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section></section>
<section style="text-align: center;margin-top: -1.5em;">
<section style="text-align: center;padding:0px 15px;font-size: 20px;font-weight: bold;display: inline-block;background: rgb(255,255,255);">
'.$data['data']['info']['goodsName'].'</section></section><section style="padding:2em 1em 2em 1em;">
<section style="border-radius:20px ;">
<img src="'.$data['data']['info']['goodsMainPicture'].'" width="100%" height=auto />
</section><p>市场价：'.$data['data']['info']['marketPrice'].'元&nbsp;&nbsp;&nbsp;唯品价：<font style="color:red;">'.$data['data']['info']['vipPrice'].'</font>元</p>品牌名称：'.$data['data']['info']['brandName'].'  &nbsp;&nbsp;&nbsp;折扣力度：'.$data['data']['info']['discount'].'折<br>
   <a href="'.$url.'" target="_blank" style=" width: 120px;
    height: 36px;
    line-height: 36px;
    text-align: center;
    background: #ff2e54;
    border-radius: 2px;
    display: inline-block;
    color: #fff;
    font-size: 14px;">去唯品会看看</a>
</section></section></section>';
 return $html;
	}

  
			

            
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