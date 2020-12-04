<?php
namespace app\go\controller;


class tbController extends \app\base\controller\BaseController {
	


  public function itemiid(){
  	  $id=$this->arg('id');
      if(!is_numeric($id)){ exit('error');}
	  include CONFIG_PATH . 'siteconfig.php';
	   $zhuan= new \ZhiCms\ext\Zhuan;
	   $agent=self::agent("y");
	   $result=$zhuan->zhicms($Siteinfo['apiurl'],$Siteinfo['code'],$Siteinfo['pid'],$id);
		$url=$result['data']["url"];

	   if($agent==2){  
		$this->biaoti=$result['data']["title"];   
		$this->taokouling=$result['data']["model"];  
		$this->display(); 
       exit;		
	   }
	  $this->redirect($url);  
  }
      //判断浏览器
    public function agent($lock){
      if($lock=='y'){
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'MicroMessenger') === false) {
             $agent="1"; //非微信
        } else {
            $agent="2"; //微信
        }

        return $agent;
      }
    }

}