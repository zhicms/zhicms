<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

/**
 * 自营商城后台管理（独立于淘客体系：yun_shop_category / yun_shop_goods / yun_shop_order / yun_shop_order_item）
 *
 * 路由：index.php?r=manage/shop/{action}
 * 与淘客（选品库 yun_items / 联盟库）完全分开，分类、商品、订单均为自营商城自有数据。
 */
class ShopController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /* ============ 公共 ============ */

    private function statusText($s)
    {
        return array('0' => '待支付', '1' => '已支付', '2' => '已发货', '3' => '已完成', '4' => '已取消')[$s] ?? '未知';
    }

    /** 解析订单 address(JSON：addressee/mobile/address/remark) */
    private function parseAddress($json)
    {
        $a = array('addressee' => '', 'mobile' => '', 'address' => '', 'remark' => '');
        if ($json) {
            $d = json_decode($json, true);
            if (is_array($d)) $a = array_merge($a, $d);
        }
        return $a;
    }

    /* ============ 分类管理 ============ */

    /** 分类列表 GET manage/shop/category */
    public function category()
    {
        $this->checkManageSession();
        $this->pageText = array('自营商城', '商品分类');

        $rows = obj('api/ApiData')->dataSelect('yun_shop_category', array(), 'sort ASC, id DESC');
        $rows = $rows ?: array();
        foreach ($rows as &$r) {
            $r['status_text'] = $r['status'] == 1 ? '启用' : '禁用';
        }
        $this->categories = $rows;
        $this->display();
    }

    /** 保存分类 POST manage/shop/categorySave */
    public function categorySave()
    {
        $this->checkManageSession();
        $this->checkCsrfToken();
        $id = intval($this->arg('id', 0));
        $name = trim($this->arg('name', ''));
        $sort = intval($this->arg('sort', 0));
        $status = $this->arg('status') == '1' ? 1 : 0;
        if ($name === '') {
            exit(json_encode(array('status' => 'n', 'info' => '请填写分类名称')));
        }
        $data = array('name' => $name, 'sort' => $sort, 'status' => $status);
        if ($id > 0) {
            obj('api/ApiData')->dataUpdate('yun_shop_category', $data, array('id' => $id));
        } else {
            $data['addtime'] = date('Y-m-d H:i:s');
            obj('api/ApiData')->insertData('yun_shop_category', $data);
        }
        exit(json_encode(array('status' => 'y', 'info' => '保存成功')));
    }

    /** 删除分类 POST manage/shop/categoryDel?id= */
    public function categoryDel()
    {
        $this->checkManageSession();
        $this->checkCsrfToken();
        $id = intval($this->arg('id', 0));
        if ($id <= 0) {
            exit(json_encode(array('status' => 'n', 'info' => '参数错误')));
        }
        $used = obj('api/ApiData')->dataSelect('yun_shop_goods', array('cat_id' => $id));
        if (!empty($used)) {
            exit(json_encode(array('status' => 'n', 'info' => '该分类下还有商品，无法删除')));
        }
        obj('api/ApiData')->thisQuery("DELETE FROM `{pre}shop_category` WHERE `id`=?", array($id));
        exit(json_encode(array('status' => 'y', 'info' => '已删除')));
    }

    /* ============ 商品管理 ============ */

    /** 商品列表 GET manage/shop/goods */
    public function goods()
    {
        $this->checkManageSession();
        $this->pageText = array('自营商城', '商品管理');

        $catId = intval($this->arg('cat_id', 0));
        $keyword = trim($this->arg('keyword', ''));
        $status = $this->arg('status', '');
        $page = max(1, intval($this->arg('page', 1)));
        $pageSize = 20;
        $offset = ($page - 1) * $pageSize;

        $where = array();
        if ($catId > 0) $where[] = "`cat_id`={$catId}";
        if ($keyword !== '') $where[] = "`title` LIKE '%" . addslashes($keyword) . "%'";
        if ($status !== '' && in_array($status, array('0', '1'), true)) {
            $where[] = "`status`=" . intval($status);
        }
        $list = obj('api/ApiData')->dataSelect('yun_shop_goods', $where, 'sort ASC, id DESC');
        $list = $list ?: array();
        $total = count($list);
        $list = array_slice($list, $offset, $pageSize);

        // 分类名映射
        $cats = obj('api/ApiData')->dataSelect('yun_shop_category', array(), 'sort ASC');
        $catMap = array();
        foreach (($cats ?: array()) as $c) {
            $catMap[$c['id']] = $c['name'];
        }
        foreach ($list as &$g) {
            $g['cat_name'] = isset($catMap[$g['cat_id']]) ? $catMap[$g['cat_id']] : '未分类';
            $g['status_text'] = $g['status'] == 1 ? '上架' : '下架';
        }

        $this->goodsList = $list;
        $this->categories = $cats ?: array();
        $this->total = $total;
        $this->pages = $this->buildPages($page, $pageSize, $total);
        $this->display();
    }

    /** 发布/编辑商品页 GET manage/shop/goodsEdit?id= */
    public function goodsEdit()
    {
        $this->checkManageSession();
        $this->pageText = array('自营商城', '商品发布');

        $id = intval($this->arg('id', 0));
        $goods = array();
        if ($id > 0) {
            $goods = obj('api/ApiData')->dataSelect('yun_shop_goods', array('id' => $id));
            $goods = $goods ?: array();
        }
        $this->goods = $goods;
        $this->categories = obj('api/ApiData')->dataSelect('yun_shop_category', array("`status`=1"), 'sort ASC') ?: array();
        $this->display();
    }

    /** 保存商品 POST manage/shop/goodsSave */
    public function goodsSave()
    {
        $this->checkManageSession();
        $this->checkCsrfToken();
        $id = intval($this->arg('id', 0));
        $catId = intval($this->arg('cat_id', 0));
        $title = trim($this->arg('title', ''));
        $subtitle = trim($this->arg('subtitle', ''));
        $cover = trim($this->arg('cover', ''));
        $images = trim($this->arg('images', ''));
        $price = round(floatval($this->arg('price', 0)), 2);
        $originalPrice = round(floatval($this->arg('original_price', 0)), 2);
        $stock = intval($this->arg('stock', 0));
        $sort = intval($this->arg('sort', 0));
        $status = $this->arg('status') == '1' ? 1 : 0;
        $content = $this->arg('content', '');

        if ($title === '') {
            exit(json_encode(array('status' => 'n', 'info' => '请填写商品标题')));
        }
        if ($price <= 0) {
            exit(json_encode(array('status' => 'n', 'info' => '售价必须大于 0')));
        }

        $data = array(
            'cat_id'        => $catId,
            'title'         => $title,
            'subtitle'      => $subtitle,
            'cover'         => $cover,
            'images'        => $images,
            'price'         => $price,
            'original_price'=> $originalPrice,
            'stock'         => $stock,
            'sort'          => $sort,
            'status'        => $status,
            'content'       => $content,
        );
        if ($id > 0) {
            obj('api/ApiData')->dataUpdate('yun_shop_goods', $data, "`id`={$id}");
        } else {
            $data['sales'] = 0;
            $data['addtime'] = date('Y-m-d H:i:s');
            obj('api/ApiData')->insertData('yun_shop_goods', $data);
        }
        exit(json_encode(array('status' => 'y', 'info' => '保存成功')));
    }

    /** 上下架 POST manage/shop/goodsToggle?id=&status= */
    public function goodsToggle()
    {
        $this->checkManageSession();
        $this->checkCsrfToken();
        $id = intval($this->arg('id', 0));
        $status = $this->arg('status') == '1' ? 1 : 0;
        if ($id <= 0) {
            exit(json_encode(array('status' => 'n', 'info' => '参数错误')));
        }
        obj('api/ApiData')->dataUpdate('yun_shop_goods', array('status' => $status), "`id`={$id}");
        exit(json_encode(array('status' => 'y', 'info' => '操作成功')));
    }

    /** 删除商品 POST manage/shop/goodsDel?id= */
    public function goodsDel()
    {
        $this->checkManageSession();
        $this->checkCsrfToken();
        $id = intval($this->arg('id', 0));
        if ($id <= 0) {
            exit(json_encode(array('status' => 'n', 'info' => '参数错误')));
        }
        obj('api/ApiData')->thisQuery("DELETE FROM `{pre}shop_goods` WHERE `id`=?", array($id));
        exit(json_encode(array('status' => 'y', 'info' => '已删除')));
    }

    /* ============ 订单管理 ============ */

    /** 订单列表 GET manage/shop/order */
    public function order()
    {
        $this->checkManageSession();
        $this->pageText = array('自营商城', '订单管理');

        $status = $this->arg('status', '');
        $keyword = trim($this->arg('keyword', ''));
        $page = max(1, intval($this->arg('page', 1)));
        $pageSize = 20;
        $offset = ($page - 1) * $pageSize;

        $where = array();
        if ($status !== '' && in_array(intval($status), array(0,1,2,3,4), true)) {
            $where[] = "`status`=" . intval($status);
        }
        if ($keyword !== '') {
            // 按订单号或用户名模糊
            $where[] = "(`order_no` LIKE '%" . addslashes($keyword) . "%' OR `uid` IN (SELECT `id` FROM `{pre}user` WHERE `nickname` LIKE '%" . addslashes($keyword) . "%'))";
        }
        $list = obj('api/ApiData')->dataSelect('yun_shop_order', $where, 'id DESC');
        $list = $list ?: array();
        $total = count($list);
        $list = array_slice($list, $offset, $pageSize);

        foreach ($list as &$o) {
            $o['status_text'] = $this->statusText($o['status']);
            $addr = $this->parseAddress($o['address']);
            $o['addressee'] = $addr['addressee'];
            $o['mobile'] = $addr['mobile'];
            $u = obj('api/ApiData')->dataSelect('yun_user', array("`id`={$o['uid']}"));
            $o['nickname'] = $u ? ($u['nickname'] ?? '用户' . $o['uid']) : '用户' . $o['uid'];
        }

        $this->orderList = $list;
        $this->total = $total;
        $this->pages = $this->buildPages($page, $pageSize, $total);
        $this->display();
    }

    /** 订单详情 GET manage/shop/orderDetail?id= */
    public function orderDetail()
    {
        $this->checkManageSession();
        $this->pageText = array('自营商城', '订单详情');

        $id = intval($this->arg('id', 0));
        $o = obj('api/ApiData')->dataSelect('yun_shop_order', array('id' => $id));
        if (empty($o)) {
            $this->errorMsg = '订单不存在';
            $this->display();
            return;
        }
        $o['status_text'] = $this->statusText($o['status']);
        $o['addr'] = $this->parseAddress($o['address']);
        $items = obj('api/ApiData')->dataSelect('yun_shop_order_item', array('order_id' => $id));
            $o['items'] = $items ?: array();
        $u = obj('api/ApiData')->dataSelect('yun_user', array('id' => $o['uid']));
        $o['nickname'] = $u ? ($u['nickname'] ?? '用户' . $o['uid']) : '用户' . $o['uid'];

        $this->order = $o;
        $this->display();
    }

    /** 发货 POST manage/shop/orderShip */
    public function orderShip()
    {
        $this->checkManageSession();
        $this->checkCsrfToken();
        $id = intval($this->arg('id', 0));
        $expressType = trim($this->arg('express_type', ''));
        $expressNo = trim($this->arg('express_no', ''));
        if ($id <= 0) {
            exit(json_encode(array('status' => 'n', 'info' => '参数错误')));
        }
        if ($expressType === '' || $expressNo === '') {
            exit(json_encode(array('status' => 'n', 'info' => '请填写快递公司与单号')));
        }
        $o = obj('api/ApiData')->dataSelect('yun_shop_order', array("`id`={$id}"));
        if (empty($o)) {
            exit(json_encode(array('status' => 'n', 'info' => '订单不存在')));
        }
        if ($o['status'] != 1) {
            exit(json_encode(array('status' => 'n', 'info' => '仅已支付订单可发货')));
        }
        obj('api/ApiData')->dataUpdate('yun_shop_order', array(
            'status'       => 2,
            'express_type' => $expressType,
            'express_no'   => $expressNo,
            'ship_time'    => date('Y-m-d H:i:s'),
        ), "`id`={$id}");
        exit(json_encode(array('status' => 'y', 'info' => '发货成功')));
    }

    /** 取消订单 POST manage/shop/orderCancel */
    public function orderCancel()
    {
        $this->checkManageSession();
        $this->checkCsrfToken();
        $id = intval($this->arg('id', 0));
        if ($id <= 0) {
            exit(json_encode(array('status' => 'n', 'info' => '参数错误')));
        }
        $o = obj('api/ApiData')->dataSelect('yun_shop_order', array("`id`={$id}"));
        if (empty($o)) {
            exit(json_encode(array('status' => 'n', 'info' => '订单不存在')));
        }
        if ($o['status'] != 0 && $o['status'] != 1) {
            exit(json_encode(array('status' => 'n', 'info' => '当前状态不可取消')));
        }
        // 已支付取消：回退库存与余额（如为余额支付）
        if ($o['status'] == 1) {
            $items = obj('api/ApiData')->dataSelect('yun_shop_order_item', array('order_id' => $id), '`id` ASC');
            foreach (($items ?: array()) as $it) {
                $goodsId = intval($it['goods_id']);
                $num = intval($it['num']);
                obj('api/ApiData')->thisQuery("UPDATE `{pre}shop_goods` SET `stock`=`stock`+?, `sales`=GREATEST(0,`sales`-?) WHERE `id`=?", array($num, $num, $goodsId));
            }
            if ($o['pay_type'] == 2 && $o['pay_fee'] > 0) {
                $uid = intval($o['uid']);
                obj('api/ApiData')->thisQuery("UPDATE `{pre}user` SET `balance`=`balance`+? WHERE `id`=?", array($o['pay_fee'], $uid));
            }
        }
        obj('api/ApiData')->dataUpdate('yun_shop_order', array('status' => 4), "`id`={$id}");
        exit(json_encode(array('status' => 'y', 'info' => '已取消订单')));
    }

    /** 删除订单（软删标记，此处直接物理删除，谨慎使用）POST manage/shop/orderDel */
    public function orderDel()
    {
        $this->checkManageSession();
        $this->checkCsrfToken();
        $id = intval($this->arg('id', 0));
        if ($id <= 0) {
            exit(json_encode(array('status' => 'n', 'info' => '参数错误')));
        }
        obj('api/ApiData')->thisQuery("DELETE FROM `{pre}shop_order` WHERE `id`=?", array($id));
        obj('api/ApiData')->thisQuery("DELETE FROM `{pre}shop_order_item` WHERE `order_id`=?", array($id));
        exit(json_encode(array('status' => 'y', 'info' => '已删除')));
    }

    /* ============ 分页辅助 ============ */

    private function buildPages($page, $pageSize, $total)
    {
        $totalPage = max(1, ceil($total / $pageSize));
        if ($totalPage <= 1) return '';
        $html = '';
        if ($page > 1) {
            $html .= '<li><a href="' . $this->pageUrl($page - 1) . '">&laquo;</a></li>';
        }
        for ($i = 1; $i <= $totalPage; $i++) {
            $cur = $i == $page ? ' class="current"' : '';
            $html .= '<li><a href="' . $this->pageUrl($i) . '"' . $cur . '>' . $i . '</a></li>';
        }
        if ($page < $totalPage) {
            $html .= '<li><a href="' . $this->pageUrl($page + 1) . '">&raquo;</a></li>';
        }
        return $html;
    }

    private function pageUrl($p)
    {
        $params = $_GET;
        $params['page'] = $p;
        return 'index.php?' . http_build_query($params);
    }
}
