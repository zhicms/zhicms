<?php
namespace app\index\controller;


class pageController extends \app\base\controller\BaseController {

	public function index(){
	  $id=$this->arg("id");
      if(!is_numeric($id)){
            self::e_404();
            exit;
        }
        $where_view[] = "`id` ={$id}";
        $viewret = obj("api/Apidata")->Data_Select("page", $where_view);
        if (empty($viewret)) {
            exit('404');
        }
		$this->display('app/index/view/page/'.$viewret['display']);
	}
		public function app(){
  
		$this->display('app/index/view/page/app');
	}
			public function side(){

		$this->display('app/index/view/page/side');
	}

}