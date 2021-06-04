<?php

namespace app\api\model;

class ApiModel extends \app\base\model\BaseModel {
    

    //验证后台是否登录
    public function isSession($Session,$url){
    	if(!$_SESSION[$Session]){
            header("location:{$url}");
          	exit;
        }
    }

    //验证前台是否登录
    public function isCookies($key,$url){
      if(!$_COOKIE[$key]){
            header("location:{$url}");
            exit;
        }
    }


   
   public function unsetSession($Session){
   		$_SESSION[$Session];
   		session_unset();
   }

   //表单接收
   public function Form($str){
	  foreach ($str as $key => $value) {
	     $data[$key]=$value;
	  }
	   return $data;
	}

//小数点两位不四舍五入
 public function moneytype($num){
    return sprintf("%.2f",substr(sprintf("%.3f", $num), 0, -2));

 }
public function GetLastMoth(){
     $date=date("Y-m-d",time());
     $timestamp=strtotime($date);
     $firstday=date('Y-m',strtotime(date('Y',$timestamp).'-'.(date('m',$timestamp)-1).'-01'));
     return $firstday;
}
  
 public function ZhiCmsCurlGet($url)
    {   
        $cu = curl_init();
        curl_setopt($cu, CURLOPT_URL, $url);
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($cu);
        curl_close($cu);
        return $ret;
    }

  //获取完整url
   public function curPageURL() {
    $pageURL = 'http';
 
     if ($_SERVER["HTTPS"] == "on")  {
         $pageURL .= "s";
     }
     $pageURL .= "://";
 
     if ($_SERVER["SERVER_PORT"] != "80") {
       $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
      }else{
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
      }
      return $pageURL;
  }

  //数组转换
  public function object_array($array){
  
  if(is_object($array)){
    $array = (array)$array;
  }
  if(is_array($array)){
    foreach($array as $key=>$value){
      $array[$key] = self::object_array($value);
    }
  }
  return $array;
}

public function randFloat($min=0, $max=1){
  return number_format($min + mt_rand()/mt_getrandmax() * ($max-$min),2);
}

  /* 时间转换*/
  public function mdate($date) {
    date_default_timezone_set('PRC'); //设置成中国的时区
    $time=strtotime($date);
    $time = (int) substr($time, 0, 10);
        $int = time() - $time;
        $str = '';
        if ($int <= 2){
            $str = sprintf('刚刚发布', $int);
        }elseif ($int < 60){
            $str = sprintf('%d秒前发布', $int);
        }elseif ($int < 3600){
            $str = sprintf('%d分钟前', floor($int / 60));
        }elseif ($int < 86400){
            $str = sprintf('%d小时前', floor($int / 3600));
        }elseif ($int < 2592000){
            $str = sprintf('%d天前发布', floor($int / 86400));
        }else{
            $str = date('一个月前', $time);
        }
        return $str;

      }
	  
	  /* 时间转换*/
  public function format_date($time) {
    date_default_timezone_set('PRC'); //设置成中国的时区
	$time=strtotime($time);
     $nowtime = time();
    $difference = $nowtime - $time;
    switch ($difference) {
        case $difference <= '60' :
            $msg = '刚刚';
            break;
        case $difference > '60' && $difference <= '3600' :
            $msg = floor($difference / 60) . '分钟前';
            break;
        case $difference > '3600' && $difference <= '86400' :
            $msg = floor($difference / 3600) . '小时前';
            break;
        case $difference > '86400' && $difference <= '2592000' :
            $msg = floor($difference / 86400) . '天前';
            break;
        case $difference > '2592000' &&  $difference <= '7776000':
            $msg = floor($difference / 2592000) . '个月前';
            break;
        case $difference > '7776000':
            $msg = '很久以前';
            break;
    }
    return $msg;

      }

 public function moneymum($str){
     return  number_format($str,2);
   // return $str;
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
    }
}