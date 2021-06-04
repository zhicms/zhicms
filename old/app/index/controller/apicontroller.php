<?php
namespace app\index\controller;

define("_key_", "zhangyuan");
class apiController extends \app\base\controller\BaseController {


  //惠惠网接口
  public function insertdata(){

  	 $key=$this->arg("pw");
  	 if($key!=_key_){
  	 	exit(json_encode(array("state"=>"key wrong","code"=>"001")));
  	 }
  	 if($_POST['country']=="国内"){
  	 $sql = "SELECT * FROM  `{pre}skuitems`  WHERE  `link` LIKE  '{$_POST['link']}'";
     $c = obj('api/ApiData')->thisquery($sql);
     if(empty($c)){

     $pic=obj("base/qiniu")->index($_POST['pic']);

  	 $data['title']=$_POST['title'];
  	 $data['pic']=$pic;
  	 $data['shop']=$_POST['shop'];
  	 $data['time']=$_POST['time'];
  	 if($_POST['country']=="国内"){
  	 	$data['country']="0";
  	 }
  	 if($_POST['country']=="海淘"){
  	 	$data['country']="1";
  	 }

  	 $data['type']=$_POST['type'];
  	 $data['body']=$_POST['body']."<img src='{$pic}' style='width:400px; height:auto'>";
  	 $data['link']=$_POST['link'];
  	 $data['date']=$_POST['date'];
  	 $data['website']="惠惠网";
  	 obj("api/Apidata")->Inserts("skuitems", $data);
  	 echo json_encode(array("state"=>"ok","code"=>"002"));
  	}else{
  		 echo json_encode(array("state"=>"重复数据","code"=>"002"));
  	}
  }
}

public function haitaobei(){

     $key=$this->arg("pw");
     if($key!=_key_){
      exit(json_encode(array("state"=>"key wrong","code"=>"001")));
     }
     $sql = "SELECT * FROM  `{pre}skuitems`  WHERE  `link` LIKE  '{$_POST['link']}'";
     $c = obj('api/ApiData')->thisquery($sql);
     if(empty($c)){


     $body= preg_replace("#<(/?a.*?)>#si",'',$_POST['body']);

      $str_replace=str_replace(array(' 海淘不孤单，海淘中有任何问题都可入“海淘超级5000人群”（群号：点击查看）咨询海淘达人管理员，钱哥或小燕！','，还可以加入ebay海淘交流群（334166322）讨论。'), array('','。'), self::plusimg("y",$body)); 
      
     $data['title']=$_POST['title'];
     $data['pic']=obj("base/qiniu")->index($_POST['pic']);
     $data['shop']=$_POST['shop'];
     $data['time']=$_POST['time'];
     $data['country']="1";
     $data['type']=$_POST['type'];
     $data['body']=$str_replace;
     $data['link']=$_POST['link'];
     $data['website']="海淘贝";
     $data['date']=$_POST['date'];
     obj("api/Apidata")->Inserts("skuitems", $data);
     echo json_encode(array("state"=>"ok","code"=>"002"));

   }

}



public function plusimg($lock,$body){

if($lock=="y"){
    
    preg_match_all("/<img([^>]*)\s*src=('|\")([^'\"]+)('|\")/", $body,$match,PREG_PATTERN_ORDER);;
     foreach($match[3] as $key=> $imgurl){
           
           $img[]=trim($imgurl);

           $fileurl[]=obj("base/qiniu")->index($imgurl);

      }
      $content = str_replace($img, $fileurl, $body);
      return $content;
   }

}





}