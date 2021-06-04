<?php
namespace app\index\controller;


class errorController extends \app\base\controller\BaseController {

	public function index(){
	   $this->display();
	   exit;
	}


}