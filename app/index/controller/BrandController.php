<?php
namespace app\index\controller;
class BrandController extends \app\base\controller\BaseController
{
    /**
     * 品牌中心（品牌栏目列表）
     * 对接大淘客 brand/get-column-list 接口（文档 id=44）
     */
    public function index(){
        $page = intval($this->arg('page'));
        $cid = $this->arg('cid');
        $page = $page < 1 ? 1 : $page;

        $tjk = new \ZhiCms\ext\Tjk();
        $result = $tjk->getBrandList(20, (string)$page, $cid);

        // 接口未配置或返回异常时给出友好提示，避免空列表无说明
        if (empty($result) || $result['code'] != 1) {
            $this->tips = $result['message'] ?? '品牌数据获取失败';
        }

        $pages = new \ZhiCms\ext\PageIndex;
        $root = 'brand.html';
        // 修正分页链接：无 cid 时用 ?page=，有 cid 时用 ?cid=x&page=
        $url = $root . '?page={page}';
        if (!empty($cid)) {
            $url = $root . '?cid=' . $cid . '&page={page}';
        }

        $count = $result['total'] ?? 0;
        $list = $result['brands'] ?? [];
        $listRows = 20;
        $array = array('list' => $list, 'count' => $count, 'pages' => $pages->show($url, $count, $listRows));
        $this->Page = $array;

        $this->cid = $cid;
        $this->display();
    }

    /**
     * 品牌详情（单个品牌下的商品列表）
     * 对接大淘客 brand/get-goods-list 接口（文档 id=45）
     */
    public function view(){
        $page = intval($this->arg('page'));
        $brandId = $this->arg('id');
        $page = $page < 1 ? 1 : $page;

        if (empty($brandId)) {
            $this->tips = '缺少品牌ID';
            $this->Page = array('list' => [], 'count' => 0, 'pages' => '');
            $this->brandInfo = [];
            $this->display();
            return;
        }

        $tjk = new \ZhiCms\ext\Tjk();
        $result = $tjk->getBrandGoods($brandId, 48, (string)$page);

        if (empty($result) || $result['code'] != 1) {
            $this->tips = $result['message'] ?? '品牌商品获取失败';
        }

        $pages = new \ZhiCms\ext\PageIndex;
        $root = 'brand-' . $brandId . '.html';
        $url = $root . '?page={page}';

        $count = $result['total'] ?? 0;
        $list = $result['goods'] ?? [];
        $brandInfo = $result['brandInfo'] ?? [];
        $listRows = 48;
        $array = array('list' => $list, 'count' => $count, 'pages' => $pages->show($url, $count, $listRows));
        $this->Page = $array;

        $this->fromSearch = $result['fromSearch'] ?? false;
        $this->title = $brandInfo['brandName'] ?? '品牌商品';
        $this->desc = $brandInfo['brandDesc'] ?? '';
        $this->brandInfo = $brandInfo;

        $this->display();
    }
}
