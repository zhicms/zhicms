<?php
namespace app\index\controller;


class rankController extends \app\base\controller\BaseController {

	public function index(){



        include CONFIG_PATH . 'siteconfig.php';
		$newdata= new \ZhiCms\ext\weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.times";
		$data=obj("api/Api")->object_array(json_decode($newdata->http($host)));
        $this->ret=$data['data']['list'];
        $this->times=$data['data']['time'];
         $this->display();

	}


}