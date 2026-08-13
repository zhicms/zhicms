<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class ItemsController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /**
     * 选品库(本地库) 首页
     * 仅读取数据库选品库 yun_items，提供本地商品管理（列表/编辑/删除/置顶/清理过期）。
     * API 接口选品与采集入库已拆分到 UnionController（联盟库）。
     */
    public function index(){

        $this->checkManageSession();

        $this->pageText = array("电商宝库", "本地库(选品库)");

        // 全站分类下拉
        $this->categories = $this->getGoodsCategories();

        $baseUrl = "index.php?r=manage/items/index";

        $page = intval($this->arg("page", 1));
        $pageSize = intval($this->arg("pageSize", 50));

        $keyword = $this->arg("keyword", '');
        if($keyword){
            $baseUrl .= "&keyword=" . urlencode($keyword);
        }

        $cid = $this->arg("cid", '');
        if($cid){
            $baseUrl .= "&cid={$cid}";
        }

        // 本地库查询
        $where = [];
        if ($keyword) {
            $where[] = "`title` LIKE '%" . addslashes($keyword) . "%'";
        }
        if ($cid) {
            $where[] = "`cid` = " . intval($cid);
        }
        if (empty($where)) {
            $where[] = "1";
        }
        $pageData = obj('api/ApiData')->page($pageSize, "yun_items", $where, "`id` DESC", $baseUrl);
        $this->page = $pageData;
        $this->Page = $pageData;
        $this->display();
    }

    public function edit(){

        $this->checkManageSession();

        $id = intval($this->arg("id"));
        $where['id'] = $id;
        $ret = obj("api/ApiData")->dataSelect("yun_items", $where);
        $this->ret = $ret;
        $this->display();
        exit;
    }

    public function del(){

        $this->checkManageSession();
        $this->checkCsrfToken();

        error_reporting(0);
        $id = intval($this->arg("id"));
        $whereC = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_items', $whereC, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    public function addItems(){

        $this->checkManageSession();

        $Siteinfo = \app\common\ConfigStore::load('site');
        if(!\IS_POST){
            $this->pageText = array("宝贝管理", "新增宝贝");
            $goodsId = $this->arg("goodsid");
            if($goodsId != ''){
                $newData = new \ZhiCms\ext\Weixin;
                $host = $Siteinfo['apiurl'] . "?s=App.taobao.info";
                $arr = array (
                    'goodsid' => $goodsId,
                );
                $rootUrl = $host . '&' . http_build_query($arr);
                $data = obj("api/Api")->objectArray(json_decode($newData->http($rootUrl)));
                $this->ret = $data['data'];
            }
            $this->display();
            exit;
        }else{

            $url = $this->arg("link");
            $title = $this->arg("title");
            $content = $this->arg("content");
            $cid = $this->arg("cid");
            $pic = $this->arg("mainPic");

            $itemsUrl = urldecode($url);
            if (!preg_match('/id=([1-9]\d*)/', $itemsUrl, $itemIid) || !isset($itemIid[1])) {
                exit(json_encode(['status' => 'n', 'info' => '链接中未识别到商品ID，请检查链接格式']));
            }
            $goodsNumericId = $itemIid[1];

            $newData = new \ZhiCms\ext\Weixin;
            $host = $Siteinfo['apiurl'] . "?s=App.taobao.info";
            $arr = array (
                'goodsid' => $goodsNumericId,
            );
            $rootUrl = $host . '&' . http_build_query($arr);
            $datas = obj("api/Api")->objectArray(json_decode($newData->http($rootUrl)));

            $data['goodsId'] = $goodsNumericId;
            $data['itemLink'] = $url;
            $data['title'] = $title;
            $data['content'] = $content;
            $data['cid'] = $cid;
            $data['mainPic'] = $pic;

            $data['originalPrice'] = $datas['data']['originalPrice'];
            $data['actualPrice'] = $datas['data']['actualPrice'];
            $data['discounts'] = $datas['data']['discounts'];
            $data['commissionRate'] = $datas['data']['commissionRate'];
            $data['couponTotalNum'] = $datas['data']['couponTotalNum'];
            $data['couponReceiveNum'] = $datas['data']['couponReceiveNum'];
            $data['couponEndTime'] = $datas['data']['couponEndTime'];
            $data['couponStartTime'] = $datas['data']['couponStartTime'];
            $data['couponConditions'] = $datas['data']['couponConditions'];
            $data['couponPrice'] = $datas['data']['couponPrice'];
            $data['monthSales'] = $datas['data']['monthSales'];
            $data['shopType'] = $datas['data']['shopType'];
            obj("api/ApiData")->insertData("yun_items", $data);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));
        }
    }

    // 置顶处理
    public function top(){

        $this->checkManageSession();
        $this->checkCsrfToken();

        $id = intval($this->arg("id"));

        //当天开始时间
        $startTime = strtotime(date("Y-m-d",time()));
        //当天结束之间
        $endTime = $startTime + 60*60*24*7;

        $where['id'] = $id;

        $data['top'] = "1";
        $data['top_stime'] = date("Y-m-d H:i:s",$startTime);
        $data['top_etime'] = date("Y-m-d H:i:s",$endTime);
        obj("api/ApiData")->dataUpdate("yun_items", $data, $where);
        exit(json_encode(array("info" => "操作置顶成功", "status" => "y")));

    }
    // 置顶处理
    public function editorTop(){

        $this->checkManageSession();
        $this->checkCsrfToken();

        $id = intval($this->arg("id"));
        $lock = $this->arg("lock");
        $where['id'] = $id;
        if($lock == "1"){
            $ret = obj("api/ApiData")->dataSelect("yun_items", $where);
            // 安全解析 top_etime：可能为 '0' 或空，strtotime 返回 false 时用当前时间
            $topEtime = isset($ret['top_etime']) ? strtotime($ret['top_etime']) : false;
            if ($topEtime === false || $topEtime <= 0) $topEtime = time();
            $endTime = $topEtime + 60*60*24*7;
            $data['top'] = 1;
            $data['top_etime'] = date("Y-m-d H:i:s",$endTime);
            obj("api/ApiData")->dataUpdate("yun_items", $data, $where);
            exit(json_encode(array("info" => "延期置顶成功", "status" => "y")));
        }elseif($lock == "2"){
            $data['top'] = 0;
            $data['top_stime'] = 0;
            $data['top_etime'] = 0;
            obj("api/ApiData")->dataUpdate("yun_items", $data, $where);
            exit(json_encode(array("info" => "取消置顶成功", "status" => "y")));

        }

    }

    /**
     * 清理优惠券已过期的商品（已结束日期早于今天）
     */
    public function overdue(){

        $this->checkManageSession();
        $this->checkCsrfToken();

        $del = "DELETE FROM `{pre}items` WHERE `couponEndTime` != '' AND `couponEndTime` != '0' AND DATE(`couponEndTime`) < CURDATE()";
        obj('api/ApiData')->thisQuery($del);

        exit(json_encode(array("info" => "更新成功", "status" => "y")));
    }
}
