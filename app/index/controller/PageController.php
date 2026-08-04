<?php
namespace app\index\controller;


class PageController extends \app\base\controller\BaseController {

	public function index(){
        $alias = trim($this->arg("alias"));
        $id    = $this->arg("id");

        $whereView = array();
        if ($alias !== '') {
            // 优先按别名查询；别名为纯数字时兼容按 id 兜底
            if (is_numeric($alias)) {
                $whereView[] = "`id` = " . intval($alias);
            } else {
                $safeAlias = str_replace(['%', '_', '\\'], ['\%', '\_', '\\\\'], $alias);
                $whereView[] = "`alias` LIKE '{$safeAlias}'";
            }
        } elseif (is_numeric($id)) {
            $whereView[] = "`id` = " . intval($id);
        } else {
            $this->e_404();
        }

        $viewRet = obj("api/ApiData")->dataSelect("yun_page", $whereView);
        if (empty($viewRet)) {
            $this->e_404();
        }

        $title = $viewRet['title'] ?? '';
        $this->title = $title;
        $this->body  = $viewRet['body'] ?? '';

        // SEO：单页标题优先，拼接站点名；关键词/描述取页面字段
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $this->pageTitle       = $title ? $title . ' - ' . $siteName : $siteName;
        $this->pageKeywords    = !empty($viewRet['keywords']) ? $viewRet['keywords'] : '';
        $this->pageDescription = !empty($viewRet['dec']) ? $viewRet['dec'] : '';

        $display = !empty($viewRet['display']) ? $viewRet['display'] : 'page';
		$this->display('app/index/view/page/'.$display);
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