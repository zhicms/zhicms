<?php
namespace app\index\controller;

class viewController extends \app\base\controller\BaseController
{

    
    
        public function view(){
              $goodsid=$this->arg('id');
              $type=$this->arg('type');
        include CONFIG_PATH . 'siteconfig.php';
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.Wjp.info";
	   $arr=array ( 
        'goodsid' => $goodsid, 
        'type' => $type,
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));

		$newbody=$data['data']['goods_desc'];
		if($data['data']['goodsCarouselPictures']!=null || $data['data']['goodsCarouselPictures']!=''){
		    
		 foreach ($data['data']['goodsCarouselPictures'] as $v) {
            $re[] = '<div class="row img"><img src="' . $v . '" /></div>';
        }
        $newbody=implode($re);
        
		}
       $this->ret = $data['data'];
       $this->newbody = $newbody;
       $this->Siteinfo=$Siteinfo;
      
		$this->display();

    }
    
        public function vip(){
              $goodsid=$this->arg('id');
        include CONFIG_PATH . 'siteconfig.php';
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.Wjp.vip";
	   $arr=array ( 
        'goodsid' => $goodsid, 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
       foreach ($data['data']['goodsCarouselPictures'] as $v) {
            $re[] = '<div class="row img"><img src="' . $v . '" /></div>';
        }
        $body =  implode($re);
       $this->ret = $data['data'];
       $this->body =$body;
       $this->Siteinfo=$Siteinfo;
      
		$this->display();

    }
    
    
    
    
    	public function detail(){
        $goodsid=$this->arg('id');
        include CONFIG_PATH . 'siteconfig.php';
        $newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.info";
	   $arr=array ( 
        'goodsid' => $goodsid, 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
		
		$hosts=$Siteinfo['apiurl']."?s=App.taobao.desc";
		$arrs=array ( 
        'id' => $goodsid, 
        );
		$rooturls = $hosts . '&' . http_build_query($arrs);
		$datas=obj("api/Api")->object_array(json_decode($newdata->http($rooturls)));
		
       $this->ret = $data['data'];
       $this->newbody = $datas['data'];
       $this->Siteinfo=$Siteinfo;
      
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
        foreach ($id as $value) {
           preg_match_all('/[1-9]\d*/', $value, $itemsid);
            $items= $itemsid['0']['0'];
        $host=$Siteinfo['apiurl']."?s=App.taobao.info";
              $arr=array ( 
              'goodsid' => $items, 
              );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
		$url=url($route='go/tb/itemiid/id=<id>', $params=array('id'=>$data['data']['goodsId']));
        $html = '<section style="padding:5px;color: #333;float: left;width:100%">
<section style="box-shadow: 0px 0px 6px rgb(211,211,211);border:1px solid #e5e5e5 ;border-radius:20px ;">
<section style="padding-top: 2em;"><section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;margin:0.5em auto;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section></section>
<section style="text-align: center;margin-top: -1.5em;">
<section style="text-align: center;padding:0px 15px;font-size: 20px;font-weight: bold;display: inline-block;background: rgb(255,255,255);">
'.$data['data']['title'].'</section></section><section style="padding:2em 1em 2em 1em;">
<section style="border-radius:20px ;">
<img src="'.$data['data']['mainPic'].'" width="100%" height=auto />
</section><p>原价：'.$data['data']['originalPrice'].'元&nbsp;&nbsp;&nbsp;券后价：<font style="color:red;">'.$data['data']['actualPrice'].'</font>元</p>销量：'.$data['data']['monthSales'].'  &nbsp;&nbsp;&nbsp;优惠券金额：'.$data['data']['couponPrice'].'元<br>
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
            //return $data;
			

            
        }
    }
    

       
   


}