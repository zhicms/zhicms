<?php
namespace app\index\controller;


class mController extends \app\base\controller\BaseController {

	public function index(){
		include CONFIG_PATH . 'siteconfig.php';
		$dev=self::get_device_type();
		$this->dev=$dev;
	    $this->display();
	}

	public function getindexdata(){
		
		//当前页面
		$page=$this->arg("p");

		$action=$this->arg("action");

		if(!$page || $page<=0){
			$page="1";
		}

		//一个页面显示条数
		$pageN="30";

		if($action=="search"){
		 $key=urldecode($this->arg("key"));
		 $where[]="`title` LIKE  '%{$key}%'";


		}else{
		$where[]="1";
	    }
		
		$count=obj("api/Apidata")->Data_Count("article",$where);

		//共分几页
		$indexpage=round($count/$pageN);
		$pagesize=($page-1)*$pageN;
		if($action=="search"){
		 $key=urldecode($this->arg("key"));
		 $sql[]="`title` LIKE  '%{$key}%'";

		}else{
		$sql[]="1";
	    }
		$ret=obj("api/Apidata")->Data_Select("article",$sql,"`id` DESC LIMIT {$pagesize} , {$pageN}");
		foreach ($ret as $key => $value) {
		$title=mb_substr(strip_tags($value['title']),0,73,'utf-8');

		$viewlink=url($route='go/tb/itemiid/id=<id>', $params=array('id'=>$value['goodsId']));
		
		$date=obj("api/Api")->mdate($value['date']);

		$html='<div class="item">
	<div class="leftcontent">
		<a class="title" href="'.$viewlink.'" target="_blank" onmousedown="xlog(\''.$value['id'].'\',\'mindextitle\')">
			'.$value['title'].'	</a>
		<div class="clear"></div>
		<div class="waitingload" style="display:none">正在读取详情，请稍候...</div>
		<div class="fullabstract" id="fullabstract" style="display:none"></div>
		<div class="clear"></div>
		<a class="thumbnail"  isconvert="1" onmousedown="xlog(\''.$value['id'].'\',\'mpic\')">
			<img src="'.$value['mainPic'].'" alt="'.$value['title'].'" style="width:70px;height:auto;margin-left:0px;margin-top:0px;margin-bottom:0px;margin-right:0px;">
		</a>
		<div class="infoandabstract">
			<div class="abstract">'.$value['keywords'].'</div>
			<div class="clear"></div>
								<div class="mallname">'.$value['dec'].'</div>
								<div class="info"><span class="latesttime">发布于'.$date.'</span>&nbsp;&nbsp;到期时间'.$value['couponEndTime'].'</div>
			<div class="clear"></div>
		
						<div class="more"><span class="idnum" style="display:none">'.$value['id'].'</span><span class="moreword">展开全文</span></div>
					</div>
	</div>
</div>
<div class="clear"></div>
';

     echo $html;

 }
	}


  //读取详情
  public function loadview(){
  	$id=$this->arg("id");
  	if(!is_numeric($id)){
  		exit('error');
  	}
   $where[] = "  `id` ={$id} ";
    $view = obj("api/Apidata")->Data_Select("article", $where);
	$viewlink=url($route='go/to/url/id=<id>', $params=array('id'=>$value['id']));
	$date=obj("api/Api")->mdate($view['date']);
	 $newbody=preg_replace_callback('/\[ZhiCmsUrl](.+?)\[\/ZhiCmsUrl]/','self::find_items',urldecode($view['content']));
  	$html='
<div class="mallname">'.$view['keywords'].'</div>
<div class="info" style="float:left;margin-left:3%;"><span class="latesttime">'.$date.'</span></div>
<div class="clear"></div>
<span class="remoteabstract">'.$newbody.'</span>
';

echo $html;

  }





//风云榜
public function rank(){
	header("Content-type: text/html; charset=utf-8"); 
    //获取30分钟内热榜
   include CONFIG_PATH . 'siteconfig.php';
		$newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.times";
		$data=obj("api/Api")->object_array(json_decode($newdata->http($host)));
		$time=$data['data']['time'];
	$html.='<div class="rankhead">
	<div class="rankheadtitle">天猫淘宝出单风云榜&nbsp;·&nbsp;今日'.$time.'点档）</div>
   </div>';
  $ret=$data['data']['list'];
  foreach ($ret as $key => $value) {
  $key=$key+1;
  $viewlink=url($route='go/tb/itemiid/id=<id>', $params=array('id'=>$value['goodsId']));
  $html.='<div class="rankitem">
	<div class="rankleftcontent">
		<div class="clear"></div>
		<a class="rankthumbnail" href="'.$viewlink.'" target="_blank">
			<img src="'.$value['mainPic'].'" style="width:70px;height:auto;margin-left:0px;margin-top:0px;margin-bottom:0px;margin-right:0px;">
		</a>
		<div class="rankinfoandabstract">
			<div class="rankinfo">No.'.$key.'</div>
			<div class="clear"></div>
			<a class="ranktitle" href="'.$viewlink.'" target="_blank">
				'.$value['title'].'			</a>
			<div class="clear"></div>
			<div class="rankmore rankmoreclick" style="float:left;"><a href="'.$viewlink.'" target="_blank"><span class="moreword" >立刻查看</span></a></div>
		</div>
	</div>
</div>
<div class="clear"></div>
';

}
echo $html;

}

//九块九
public function cheaps(){
		include CONFIG_PATH . 'siteconfig.php';
        //当前页面
		$page=$this->arg("p");

		if(!$page || $page<=0){
			$page="1";
		}

		//一个页面显示条数
		$pageN="30";

		$where[]="1";
		
		$count=obj("api/Apidata")->Data_Count("goods",$where);

		//共分几页
		$indexpage=round($count/$pageN);
		$pagesize=($page-1)*$pageN;

		$sql[]="1";
		$ret=obj("api/Apidata")->Data_Select("goods",$sql,"`id` DESC LIMIT {$pagesize} , {$pageN}");
		foreach ($ret as $key => $value) {

		//$quanlink="http://uland.taobao.com/coupon/edetail?activityId={$value['Quan_id']}&pid={$Siteinfo['pid']}&itemId={$value['GoodsID']}&dx=1";
		$quanlink=url($route='go/tb/itemiid/id=<id>', $params=array("id"=>$value['goodsId']));
	$html='<div class="cheapitem">
	<div class="cheapleftcontent">
		<div class="clear"></div>
		<a class="cheapthumbnail" isconvert=1 href="'.$quanlink.'" target="_blank">
			<img src="'.$value['mainPic'].'" alt="'.$value['dtitle'].'" style="width:70px;height:70px;margin-left:0px;margin-top:0px;margin-bottom:0px;margin-right:0px;">
		</a>
		<div class="cheapinfoandabstract">
			<div class="clear"></div>
			<a class="cheaptitle" isconvert=1 href="'.$quanlink.'" target="_blank">
				'.$value['title'].'			</a>
			<div class="clear"></div>
			<div class="cheapinfo"><span style="font-size:10px">券后&nbsp;&yen;</span>'.$value['actualPrice'].'</div>
			<a class="buy" isconvert=1 href="'.$quanlink.'" target="_blank"><span class="buyword">购买&gt;</span></a>
						<a class="coupon" href="'.$quanlink.'" target="_blank"><span class="couponword">领'.$value['couponPrice'].'元券</span></a>
					</div>
	</div>
</div>
<div class="clear"></div>
';

echo $html;

}
}


public function search(){

	$this->display('app/index/view/m/index');
}

public function get_device_type()
{
 //全部变成小写字母
 $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
 $type = 'other';
 //分别进行判断
 if(strpos($agent, 'iphone') || strpos($agent, 'ipad'))
{
 $type = 'ios';
 } 
  
 if(strpos($agent, 'android'))
{
 $type = 'android';
 }
 return $type;
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
</section><p>原价：'.$data['data']['info']['originalPrice'].'元&nbsp;&nbsp;&nbsp;券后价：<font style="color:red;">'.$data['data']['info']['actualPrice'].'</font>元</p><p>销量：'.$data['data']['info']['monthSales'].'  &nbsp;&nbsp;&nbsp;优惠券金额：'.$data['data']['info']['couponPrice'].'元</p>
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
</section><p>原价：'.$data['data']['info']['price'].'元&nbsp;&nbsp;&nbsp;券后价：<font style="color:red;">'.$data['data']['info']['price_after'].'</font>元</p><p>销量：'.$data['data']['info']['sales'].'  &nbsp;&nbsp;&nbsp;优惠券金额：'.$data['data']['info']['discount'].'元</p>
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
</section><p>市场价：'.$data['data']['info']['marketPrice'].'元&nbsp;&nbsp;&nbsp;唯品价：<font style="color:red;">'.$data['data']['info']['vipPrice'].'</font>元</p><p>品牌名称：'.$data['data']['info']['brandName'].'  &nbsp;&nbsp;&nbsp;折扣力度：'.$data['data']['info']['discount'].'折</p>
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



}