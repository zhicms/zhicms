<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
error_reporting('0');
class plugController extends \app\base\controller\BaseController
{

	public function index(){
		$this->pagetext=array("云控中心","应用插件");
		$where[]="1";
		$ret=obj('api/ApiData')->Data_Select('plug', $where,' `id` DESC');
		$this->ret=$ret;
		$this->display();
	}

	public function lists(){
		$this->pagetext=array("云控中心","应用插件");
		$where[]="1";
		$ret=obj('api/ApiData')->Data_Select('plug', $where,' `id` DESC');
		$this->ret=$ret;
		$this->display();
	}

}