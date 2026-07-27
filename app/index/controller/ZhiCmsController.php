<?php
namespace app\index\controller;
define("__CDNIMG__","http://cdn.miyueyun.com/");
class ZhiCmsController extends \app\base\controller\BaseController {
     
     public function index(){
     	 header("Content-type: text/html; charset=utf-8"); 
     	 
     	 $where[]="1";
     	 $baseUrl="index.php?r=index/zhicms/index";
     	 $page = obj('api/ApiData')->page("100", "yun_skuitems", $where, "`id` DESC", $baseUrl);


     	 foreach ($page['list'] as $key => $value) {

     	 	if($value['country']=="0"){

     	 		 if(strpos($value['type'],"囤货")!==false){  $type="1"; }
     	 		 if(strpos($value['type'],"母婴")!==false){  $type="3"; }
     	 		 if(strpos($value['type'],"日用")!==false){  $type="4"; }
     	 		 if(strpos($value['type'],"数码")!==false){  $type="5"; }
     	 		 if(strpos($value['type'],"家电")!==false){  $type="6"; }
     	 		 if(strpos($value['type'],"食品")!==false){  $type="7"; }
     	 		 if(strpos($value['type'],"服饰")!==false){  $type="8"; }
     	 		 if(strpos($value['type'],"旅行")!==false){  $type="9"; }
     	 		 if(strpos($value['type'],"美妆")!==false){  $type="10"; }
     	 		 if(strpos($value['type'],"运动")!==false){  $type="11"; }
     	 		 if(strpos($value['type'],"汽车")!==false){  $type="12"; }
     	 		 if(strpos($value['type'],"促销")!==false){  $type="13"; }
     	 		 if(strpos($value['type'],"其他")!==false){  $type="1"; }
     	         
     	         if(strpos($value['shop'],"京东")!==false){  $mall="1"; }	
     	         if(strpos($value['shop'],"亚马逊")!==false){  $mall="2"; } 
     	         if(strpos($value['shop'],"苏宁")!==false){  $mall="3"; } 
     	         if(strpos($value['shop'],"国美")!==false){  $mall="4"; }
     	         if(strpos($value['shop'],"当当")!==false){  $mall="5"; }
     	         if(strpos($value['shop'],"考拉")!==false){  $mall="6"; }
     	         if(strpos($value['shop'],"严选")!==false){  $mall="7"; }
     	         if(strpos($value['shop'],"天猫")!==false){  $mall="8"; }
     	         if(strpos($value['shop'],"乐天")!==false){  $mall="18"; }

     	         if($mall==""){
     	         	$mall="19";
     	         }

     	 	}
     	 	if($value['country']=="1"){

     	 		 if(strpos($value['type'],"直邮中国")!==false){  $type="2"; }
     	 		 if(strpos($value['type'],"鞋帽")!==false){  $type="14"; }
     	 		 if(strpos($value['type'],"婴")!==false){  $type="15"; }
     	 		 if(strpos($value['type'],"日用")!==false){  $type="16"; }
     	 		 if(strpos($value['type'],"码")!==false){  $type="17"; }
     	 		 if(strpos($value['type'],"电")!==false){  $type="18"; }
     	 		 if(strpos($value['type'],"食")!==false){  $type="19"; }
     	 		 if(strpos($value['type'],"装")!==false){  $type="20"; }
     	 		 if(strpos($value['type'],"妆")!==false){  $type="21"; }
     	 		 if(strpos($value['type'],"动")!==false){  $type="22"; }
     	 		 if(strpos($value['type'],"车")!==false){  $type="23"; }
     	 		 if(strpos($value['type'],"促销")!==false){  $type="24"; }
     	 		 if(strpos($value['type'],"其他")!==false){  $type="14"; }

     	 		  if(strpos($value['shop'],"Amazon")!==false){  $mall="9"; }	
     	         if(strpos($value['shop'],"6PM")!==false){  $mall="10"; } 
     	         if(strpos($value['shop'],"eBay")!==false){  $mall="11"; } 
     	         if(strpos($value['shop'],"amazonjp")!==false){  $mall="12"; }
     	         if(strpos($value['shop'],"amazon")!==false){  $mall="13"; }
     	         if(strpos($value['shop'],"德国亚马逊")!==false){  $mall="14"; }
     	         if(strpos($value['shop'],"ashford")!==false){  $mall="15"; }
     	         if(strpos($value['shop'],"RUELALA")!==false){  $mall="16"; }
     	         if(strpos($value['shop'],"macys")!==false){  $mall="17"; }

     	         if($mall==""){
     	         	$mall="19";
     	         }

     	 	}

     	   
     	     $clickUrl=self::clickUrl($value['link']);




     	 	$data['title']=$value['title'];
     	 	$data['pic']=$value['pic'];
     	 	$data['shop']=$mall;
     	 	$data['time']=$value['time'];
     	 	$data['country']=$value['country'];
     	 	$data['type']=$type;
     	 	$data['body']=$value['body'];
     	 	$data['link']=$clickUrl;
     	 	$data['website']=$value['website'];
     	 	$data['date']=$value['date'];
     	 	$restData[]=$data;
     	 }

     	 print_r(json_encode($restData));
     }




  
     public function clickUrl($clickUrl){
  	
      preg_match_all('/https:\/\/([a-zA-Z0-9-=_.\/]+){1,16}.html/', $clickUrl, $itemsHtml);
      preg_match_all('/http:\/\/([a-zA-Z0-9-=_.\/]+){1,16}.html/', $clickUrl, $itemsHtmlHttp);
      preg_match_all('/https:\/\/detail.tmall.com\/item.htm\?id=[1-9]\d*/', $clickUrl, $tmall);
      preg_match_all('/https:\/\/www.amazon.cn\/mn\/detailApp\?asin=([a-zA-Z0-9-=_.\/]+){1,16}/', $clickUrl, $amazonDetailApp);
       preg_match_all('/http:\/\/item.gome.com.cn\/A([a-zA-Z0-9-=_.\/]+){1,16}.html/', $clickUrl, $gome);


       preg_match_all('/https:\/\/www.6pm.com\/([a-zA-Z0-9-=_.\/]+){1,16}/', $clickUrl, $sixpm);
       preg_match_all('/https:\/\/zh.ashford.com\/([a-zA-Z0-9-=_.\/]+){1,16}/', $clickUrl, $ashford);

       preg_match_all('/https:\/\/([a-zA-Z0-9-=_.\/]+){1,16}/', $clickUrl, $ebay);
    
      if($itemsHtml['0']['0']!=''){
        return $itemsHtml['0']['0'];
      }
       if($itemsHtmlHttp['0']['0']!=''){
        return $itemsHtmlHttp['0']['0'];
      }
  
      if($tmall['0']['0']!=''){
        return $tmall['0']['0'];
      }
       if($amazonDetailApp['0']['0']!=''){
        return $amazonDetailApp['0']['0'];
      }
       if($gome['0']['0']!=''){
        return $gome['0']['0'];
      }
     
      if($sixpm['0']['0']!=''){
        return $sixpm['0']['0'];
      }
      if($ashford['0']['0']!=''){
        return $ashford['0']['0'];
      }
      if($ebay['0']['0']!=''){
        return $ebay['0']['0'];
      }

     
  }
}