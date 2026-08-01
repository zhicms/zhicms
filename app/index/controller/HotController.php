<?php
namespace app\index\controller;

/**
 * 线报
 * 对接大淘客「线报」接口 list-tip-off（文档 id=62）。
 * 原 HotController 的「热榜」逻辑已合并进 RankController（风云榜），
 * 此处复用 hot.html / index/hot/index 这一导航入口，改造为「线报」栏目。
 */
class HotController extends \app\base\controller\BaseController {

    public function index(){
        $tjk = new \ZhiCms\ext\Tjk();
        $result = $tjk->getTipOff('1', 20, '', 0);

        if (empty($result) || $result['code'] != 1) {
            $this->tips = $result['message'] ?? '线报数据获取失败';
        }

        $this->list   = $result['list'] ?? [];
        $this->total  = $result['total'] ?? 0;
        $this->title  = '线报';

        // SEO：线报页
        $this->pageTitle = '线报 - 实时商品优惠情报 - ' . obj('base/Base')->SiteConfig('sitename');
        $this->pageKeywords = '线报,优惠线报,实时优惠,商品情报';
        $this->pageDescription = '最新实时线报，涵盖淘宝、京东、拼多多等平台的商品优惠情报，及时掌握全网折扣信息。';
        $this->canonicalUrl = url($route='index/hot/index', $params=array());

        // 加载公共侧边栏（分类 + 热门文章等）
        $this->loadCommonSidebar();

        $this->display('app/index/view/tipoff/index');
    }
}
