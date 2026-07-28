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
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $this->pageTitle = '手机APP下载 - ' . $siteName;
        $this->pageDescription = '下载' . $siteName . '手机APP，随时随地查优惠、比价格、找好物。';
        $this->loadCommonSidebar();
		$this->display('app/index/view/page/app');
	}
			public function side(){
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $this->pageTitle = '浏览器插件 - ' . $siteName;
        $this->pageDescription = $siteName . '浏览器插件，智能识别商品页面，一键查看全网最低价。';
        $this->loadCommonSidebar();
		$this->display('app/index/view/page/side');
	}

}