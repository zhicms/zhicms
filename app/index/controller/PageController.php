<?php
namespace app\index\controller;


class PageController extends \app\base\controller\BaseController {

	public function index(){
	  $id=$this->arg("id");
      if(!is_numeric($id)){
            self::e_404();
            exit;
        }
        $whereView[] = "`id` ={$id}";
        $viewRet = obj("api/ApiData")->dataSelect("yun_page", $whereView);
        if (empty($viewRet)) {
            exit('404');
        }
		$this->display('app/index/view/page/'.$viewRet['display']);
	}
		public function app(){
  
		$this->display('app/index/view/page/app');
	}
			public function side(){

		$this->display('app/index/view/page/side');
	}

}