<?php
namespace app\index\controller;


class ErrorController extends \app\base\controller\BaseController {

	public function index(){
	   $this->display();
	   exit;
	}


}