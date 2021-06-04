<?php
namespace ZhiCms\ext;

class Zhuan
{
   public function zhicms($apiurl,$token,$pid,$itemiid){
	require_once(__DIR__.'/zhuan/Zhicms.php');
    $zhicms = new \Zhicms();
  $result =$zhicms->zhuanlian($apiurl,$token,$pid,$itemiid);
   return $result;
  }

}