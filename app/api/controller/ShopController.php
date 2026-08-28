<?php
namespace app\api\controller;

/**
 * 自营商城接口（miniapp 插件后端）
 *
 * 路由：index.php?r=api/shop/{action}
 * 鉴权：Bearer Token（与 UserController 同一套，解析 yun_user.uid）
 * 支付：余额支付 + 微信支付 V3 Native（WxPayV3 网关）
 *
 * 表：yun_shop_goods / yun_shop_category / yun_shop_cart / yun_shop_order / yun_shop_order_item
 */
class ShopController extends ApiBaseController
{
    /* ============ 公共 ============ */

    private function secret()
    {
        static $s = null;
        if ($s === null) {
            $cfg = \app\common\ConfigStore::load('api');
            $s = isset($cfg['secretkey']) && $cfg['secretkey'] !== '' ? $cfg['secretkey'] : 'zhangyuan';
        }
        return $s;
    }

    private function parseToken($token)
    {
        if (!$token || strpos($token, '.') === false) return null;
        list($b, $sign) = explode('.', $token, 2);
        if (md5($b . $this->secret()) !== $sign) return null;
        $payload = json_decode(base64_decode($b), true);
        if (!$payload || empty($payload['uid'])) return null;
        if (!empty($payload['exp']) && $payload['exp'] < time()) return null;
        return $payload;
    }

    /** 解析当前登录 uid，未登录返回 null */
    private function uid()
    {
        $token = $this->requestToken();
        $p = $this->parseToken($token);
        return $p ? (int)$p['uid'] : null;
    }

    private function plugCfg()
    {
        return \ZhiCms\base\PluginManager::getConfig('miniapp');
    }

    /**
     * 商城功能开关守卫：miniapp 插件未启用时，所有商城接口一律返回「功能未开启」，
     * 避免插件未安装/未开启时访问 yun_shop_* 表导致 Table doesn't exist 的 500 错误。
     * 插件表读取异常也兜底为关闭。
     */
    private function guardShop()
    {
        if (!\app\common\FeatureGate::shopEnabled()) {
            $this->json(array('code' => 0, 'message' => '商城功能未开启'), 503);
        }
    }

    /* ============ 商品 / 分类（无需登录） ============ */

    /** 分类列表 GET api/shop/categories */
    public function categories()
    {
        $this->options();
        $this->guardShop();
        $rows = obj('api/ApiData')->dataSelect('yun_shop_category', array("`status`=1"), 'sort ASC');
        $rows = $rows ?: array();
        $this->json(array('code' => 1, 'categories' => $rows));
    }

    /** 商品列表 GET api/shop/goods?cat_id=&keyword=&page=&page_size= */
    public function goods()
    {
        $this->options();
        $this->guardShop();
        $catId   = intval($this->raw('cat_id', 0));
        $keyword = trim($this->raw('keyword', ''));
        $page    = max(1, intval($this->raw('page', 1)));
        $pageSize= min(50, max(1, intval($this->raw('page_size', 20))));
        $offset  = ($page - 1) * $pageSize;

        $where = array("`status`=1");
        if ($catId > 0) $where[] = "`cat_id`={$catId}";
        if ($keyword !== '') {
            // 先转义引号（防 SQL 注入闭合），再转义 LIKE 通配符 % _ \（防全表扫描/ReDoS）
            $kw = addslashes($keyword);
            $kw = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $kw);
            $where[] = "`title` LIKE '%{$kw}%'";
        }
        $order = 'sort ASC, id DESC';
        $list  = obj('api/ApiData')->dataSelect('yun_shop_goods', $where, $order);
        $list  = $list ?: array();
        $total = obj('api/ApiData')->dataCount('yun_shop_goods', $where);
        $list  = array_slice($list, $offset, $pageSize);

        $items = array();
        foreach ($list as $g) {
            $items[] = $this->mapGoods($g);
        }
        $this->json(array('code' => 1, 'goods' => $items, 'total' => $total, 'page' => $page));
    }

    /** 商品详情 GET api/shop/detail?id= */
    public function detail()
    {
        $this->options();
        $this->guardShop();
        $id = intval($this->raw('id', 0));
        if ($id <= 0) $this->json(array('code' => 0, 'message' => '商品ID错误'), 400);
        $g = obj('api/ApiData')->dataSelect('yun_shop_goods', array("`id`={$id}", "`status`=1"));
        if (empty($g)) $this->json(array('code' => 0, 'message' => '商品不存在'));
        $g['images_arr'] = $g['images'] ? explode(',', $g['images']) : array($g['cover']);
        $this->json(array('code' => 1, 'goods' => $this->mapGoods($g, true)));
    }

    private function mapGoods($g, $full = false)
    {
        $arr = array(
            'id'            => (int)$g['id'],
            'cat_id'        => (int)$g['cat_id'],
            'title'         => $g['title'],
            'subtitle'      => $g['subtitle'] ?? '',
            'cover'         => $g['cover'],
            'price'         => (float)$g['price'],
            'original_price'=> (float)$g['original_price'],
            'stock'         => (int)$g['stock'],
            'sales'         => (int)$g['sales'],
        );
        if ($full) {
            $arr['images'] = $g['images_arr'] ?? array();
            $arr['content'] = $g['content'] ?? '';
        }
        return $arr;
    }

    /* ============ 购物车（需登录） ============ */

    private function needLogin()
    {
        $uid = $this->uid();
        if (!$uid) $this->json(array('code' => 401, 'message' => '请先登录'), 401);
        return $uid;
    }

    /** 加入购物车 POST api/shop/cartAdd?goods_id=&num= */
    public function cartAdd()
    {
        $this->options();
        $this->guardShop();
        $uid = $this->needLogin();
        $goodsId = intval($this->raw('goods_id', 0));
        $num = max(1, intval($this->raw('num', 1)));
        if ($goodsId <= 0) $this->json(array('code' => 0, 'message' => '商品错误'), 400);

        $exist = obj('api/ApiData')->dataSelect('yun_shop_cart', array('uid' => $uid, 'goods_id' => $goodsId));
        if ($exist) {
            obj('api/ApiData')->dataUpdate('yun_shop_cart', array('num' => $exist['num'] + $num), array('id' => $exist['id']));
        } else {
            obj('api/ApiData')->insertData('yun_shop_cart', array(
                'uid' => $uid, 'goods_id' => $goodsId, 'num' => $num,
            ));
        }
        $this->json(array('code' => 1, 'message' => '已加入购物车'));
    }

    /** 购物车列表 GET api/shop/cartList */
    public function cartList()
    {
        $this->options();
        $this->guardShop();
        $uid = $this->needLogin();
        $rows = obj('api/ApiData')->dataSelect('yun_shop_cart', array("`uid`={$uid}"), 'id DESC');
        $rows = $rows ?: array();
        $items = array();
        foreach ($rows as $c) {
            $g = obj('api/ApiData')->dataSelect('yun_shop_goods', array("`id`={$c['goods_id']}"));
            if (empty($g)) continue;
            $items[] = array(
                'cart_id' => (int)$c['id'],
                'num'     => (int)$c['num'],
                'goods'   => $this->mapGoods($g),
            );
        }
        $this->json(array('code' => 1, 'list' => $items));
    }

    /** 清空/删除购物车 POST api/shop/cartDel?cart_id= (0=清空) */
    public function cartDel()
    {
        $this->options();
        $this->guardShop();
        $uid = $this->needLogin();
        $cartId = intval($this->raw('cart_id', 0));
        if ($cartId > 0) {
            obj('api/ApiData')->thisQuery("DELETE FROM `{pre}shop_cart` WHERE `uid`=? AND `id`=?", array($uid, $cartId));
        } else {
            obj('api/ApiData')->thisQuery("DELETE FROM `{pre}shop_cart` WHERE `uid`=?", array($uid));
        }
        $this->json(array('code' => 1, 'message' => 'ok'));
    }

    /* ============ 下单 / 支付 ============ */

    /**
     * 下单 POST api/shop/order
     * 参数： goods_id= / num= / pay_type=1(微信)或2(余额) / address=JSON
     * 支持单品立即购买；购物车结算用 cart_ids（预留）
     */
    public function order()
    {
        $this->options();
        $this->guardShop();
        $uid = $this->needLogin();
        $goodsId = intval($this->raw('goods_id', 0));
        $num = max(1, intval($this->raw('num', 1)));
        $payType = intval($this->raw('pay_type', 1));
        $address = trim($this->raw('address', ''));

        $g = obj('api/ApiData')->dataSelect('yun_shop_goods', array("`id`={$goodsId}", "`status`=1"));
        if (empty($g)) $this->json(array('code' => 0, 'message' => '商品不存在或已下架'), 400);
        if ($g['stock'] < $num) $this->json(array('code' => 0, 'message' => '库存不足'), 400);

        $totalFee = round((float)$g['price'] * $num, 2);

        // 余额支付校验
        if ($payType == 2) {
            $cfg = $this->plugCfg();
            if (empty($cfg['balance_enable'])) $this->json(array('code' => 0, 'message' => '余额支付未开启'), 400);
            $u = obj('api/ApiData')->dataSelect('yun_user', array("`id`={$uid}"));
            if ((float)($u['balance'] ?? 0) < $totalFee) $this->json(array('code' => 0, 'message' => '余额不足'), 400);
        }

        $orderNo = $this->genOrderNo();
        $orderId = obj('api/ApiData')->insertData('yun_shop_order', array(
            'order_no'   => $orderNo,
            'uid'        => $uid,
            'total_fee'  => $totalFee,
            'pay_fee'    => $totalFee,
            'pay_type'   => $payType,
            'status'     => 0,
            'address'    => $address,
        ));
        obj('api/ApiData')->insertData('yun_shop_order_item', array(
            'order_id' => $orderId, 'goods_id' => $goodsId,
            'title' => $g['title'], 'cover' => $g['cover'],
            'price' => $g['price'], 'num' => $num,
        ));

        if ($payType == 2) {
            // 余额支付：原子扣款（WHERE 增加余额校验，避免并发超扣成负数）
            $aff = obj('api/ApiData')->thisQuery(
                "UPDATE `{pre}user` SET `balance`=`balance`-{$totalFee} WHERE `id`={$uid} AND `balance`>={$totalFee}"
            );
            if ($aff === 0 || $aff === false) {
                obj('api/ApiData')->dataUpdate('yun_shop_order', array('status' => 2, 'close_reason' => '余额不足'), array("`id`={$orderId}"));
                $this->json(array('code' => 0, 'message' => '余额不足，支付失败'), 400);
            }
            // 原子扣库存（防止超卖），仅当库存充足才更新
            obj('api/ApiData')->thisQuery(
                "UPDATE `{pre}shop_goods` SET `stock`=GREATEST(0,`stock`-{$num}), `sales`=`sales`+{$num} WHERE `id`={$goodsId} AND `stock`>={$num}"
            );
            obj('api/ApiData')->dataUpdate('yun_shop_order', array('status' => 1, 'paytime' => date('Y-m-d H:i:s')), array("`id`={$orderId}"));
            $this->json(array('code' => 1, 'message' => '支付成功', 'order_id' => $orderId, 'pay_type' => 2));
        }

        // 微信支付：native 下单
        $cfg = $this->plugCfg();
        if (empty($cfg['wx_appid']) || empty($cfg['wx_mchid']) || empty($cfg['wx_api_v3_key']) || empty($cfg['wx_serial_no'])) {
            $this->json(array('code' => 0, 'message' => '微信支付未配置'), 500);
        }
        try {
            $wxpay = new \ZhiCms\ext\WxPayV3(
                $cfg['wx_appid'], $cfg['wx_mchid'], $cfg['wx_api_v3_key'], $cfg['wx_serial_no']
            );
            $notify = $this->siteUrl() . 'index.php?r=api/shop/notify';
            $resp = $wxpay->nativeOrder(mb_substr($g['title'], 0, 32, 'UTF-8'), $orderNo, (int)round($totalFee * 100), $notify);
            obj('api/ApiData')->dataUpdate('yun_shop_order', array('wx_prepay_id' => $resp['prepay_id'] ?? ''), array("`id`={$orderId}"));
            $this->json(array(
                'code' => 1, 'message' => 'ok', 'order_id' => $orderId, 'pay_type' => 1,
                'code_url' => $resp['code_url'], 'order_no' => $orderNo,
            ));
        } catch (\Throwable $e) {
            $this->json(array('code' => 0, 'message' => '微信下单失败：' . $e->getMessage()), 500);
        }
    }

    /**
     * 微信支付回调（独立入口，V3 真实验签 + 解密）
     */
    public function notify()
    {
        // 商城未开启时，回调无需处理（直接返回 SUCCESS 给微信，避免其无限重试告警）
        if (!\app\common\FeatureGate::shopEnabled()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('code' => 'SUCCESS', 'message' => '成功'), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $body = file_get_contents('php://input');
        $headers = array();
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = $v;
            }
        }
        try {
            $cfg = $this->plugCfg();
            $wxpay = new \ZhiCms\ext\WxPayV3(
                $cfg['wx_appid'], $cfg['wx_mchid'], $cfg['wx_api_v3_key'], $cfg['wx_serial_no']
            );
            $notify = $wxpay->handleNotify($body, $headers);
            if (($notify['trade_state'] ?? '') === 'SUCCESS') {
                $orderNo = $notify['out_trade_no'] ?? '';
                $transId = $notify['transaction_id'] ?? '';
                // 安全：过滤订单号特殊字符，避免 SQL 注入
                $orderNoSafe = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$orderNo);
                if ($orderNoSafe === '') {
                    throw new \Exception('回调订单号为空或非法');
                }
                $order = obj('api/ApiData')->dataSelect('yun_shop_order', array("`order_no`='{$orderNoSafe}'"));
                if ($order && $order['status'] == 0) {
                    // 金额校验：微信回传金额（分）必须与订单应付金额一致，且商户号匹配，否则视为异常
                    $paidTotal = (int)($notify['amount']['total'] ?? -1);
                    $orderTotal = (int)round((float)$order['pay_fee'] * 100);
                    $mchidMatch = empty($cfg['wx_mchid']) || ($notify['mchid'] ?? '') === $cfg['wx_mchid'];
                    if ($paidTotal !== $orderTotal || !$mchidMatch) {
                        error_log('shop notify amount/mchid mismatch: order=' . $orderNoSafe
                            . ' paid=' . $paidTotal . ' expect=' . $orderTotal
                            . ' mchid=' . ($notify['mchid'] ?? ''));
                        throw new \Exception('金额或商户号校验失败');
                    }
                    obj('api/ApiData')->dataUpdate('yun_shop_order',
                        array('status' => 1, 'transaction_id' => $transId, 'paytime' => date('Y-m-d H:i:s')),
                        array("`id`={$order['id']}"));
                    // 扣库存
                    $items = $this->rows(obj('api/ApiData')->dataSelect('yun_shop_order_item', array("`order_id`={$order['id']}")));
                    foreach ($items as $it) {
                        $g = obj('api/ApiData')->dataSelect('yun_shop_goods', array("`id`={$it['goods_id']}"));
                        if ($g) {
                            obj('api/ApiData')->dataUpdate('yun_shop_goods',
                                array('stock' => max(0, $g['stock'] - $it['num']), 'sales' => $g['sales'] + $it['num']),
                                array("`id`={$it['goods_id']}"));
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // 记录失败但不阻断微信重试
            error_log('shop notify error: ' . $e->getMessage());
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('code' => 'SUCCESS', 'message' => '成功'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** 我的订单 GET api/shop/myOrder?status= */
    public function myOrder()
    {
        $this->options();
        $this->guardShop();
        $uid = $this->needLogin();
        $status = $this->raw('status', '');
        $where = array("`uid`={$uid}");
        if ($status !== '' && in_array((int)$status, array(0,1,2,3,4), true)) {
            $where[] = "`status`=" . intval($status);
        }
        $rows = obj('api/ApiData')->dataSelect('yun_shop_order', $where, 'id DESC');
        $rows = $rows ?: array();
        $list = array();
        foreach ($rows as $o) {
            $items = $this->rows(obj('api/ApiData')->dataSelect('yun_shop_order_item', array("`order_id`={$o['id']}")));
            $list[] = array(
                'order_id'    => (int)$o['id'],
                'order_no'    => $o['order_no'],
                'total_fee'   => (float)$o['total_fee'],
                'pay_type'    => (int)$o['pay_type'],
                'status'      => (int)$o['status'],
                'status_text' => $this->orderStatusText($o['status']),
                'express_type'=> $o['express_type'] ?? '',
                'express_no'  => $o['express_no'] ?? '',
                'addtime'     => $o['addtime'],
                'items'       => array_map(function ($it) {
                    return array('title' => $it['title'], 'cover' => $it['cover'], 'price' => (float)$it['price'], 'num' => (int)$it['num']);
                }, $items),
            );
        }
        $this->json(array('code' => 1, 'list' => $list));
    }

    /**
     * 确认收货（用户端）POST api/shop/confirm?order_id=
     */
    public function confirm()
    {
        $this->options();
        $this->guardShop();
        $uid = $this->needLogin();
        $orderId = intval($this->raw('order_id', 0));
        if ($orderId <= 0) $this->json(array('code' => 0, 'message' => '订单ID错误'), 400);
        $o = obj('api/ApiData')->dataSelect('yun_shop_order', array("`id`={$orderId}", "`uid`={$uid}"));
        if (empty($o)) $this->json(array('code' => 0, 'message' => '订单不存在'));
        // 仅「已发货」可确认收货（status=2）
        if ($o['status'] != 2) $this->json(array('code' => 0, 'message' => '当前状态不可确认收货'));
        obj('api/ApiData')->dataUpdate('yun_shop_order', array('status' => 3, 'confirm_time' => date('Y-m-d H:i:s')), array("`id`={$orderId}"));
        $this->json(array('code' => 1, 'message' => '已确认收货'));
    }

    /**
     * 物流轨迹查询 GET api/shop/logistics?order_id= （用户端，读订单内快递单号）
     * 对接快递100免费公开接口：https://www.kuaidi100.com/query
     */
    public function logistics()
    {
        $this->options();
        $this->guardShop();
        $uid = $this->needLogin();
        $orderId = intval($this->raw('order_id', 0));
        if ($orderId <= 0) $this->json(array('code' => 0, 'message' => '订单ID错误'), 400);
        $o = obj('api/ApiData')->dataSelect('yun_shop_order', array("`id`={$orderId}", "`uid`={$uid}"));
        if (empty($o)) $this->json(array('code' => 0, 'message' => '订单不存在'));
        if (empty($o['express_no']) || empty($o['express_type'])) {
            $this->json(array('code' => 0, 'message' => '暂无物流信息'));
        }
        $trace = $this->queryKuaidi100($o['express_no'], $o['express_type']);
        $this->json(array(
            'code'    => 1,
            'express' => $o['express_type'],
            'no'      => $o['express_no'],
            'trace'   => $trace,
        ));
    }

    /**
     * 调用快递100免费公开查询接口（无需 key，但有限流，建议生产接入快递鸟实名接口）
     * @return array 轨迹列表 [{time, context}]
     */
    private function queryKuaidi100($no, $com)
    {
        $url = 'https://www.kuaidi100.com/query?type=' . urlencode($com) . '&postid=' . urlencode($no);
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            $resp = curl_exec($ch);
            curl_close($ch);
            if (!$resp) return array();
            $dec = json_decode($resp, true);
            if (empty($dec) || empty($dec['data'])) return array();
            $list = array();
            foreach ($dec['data'] as $d) {
                $list[] = array(
                    'time'    => $d['ftime'] ?? ($d['time'] ?? ''),
                    'context' => $d['context'] ?? '',
                );
            }
            return $list;
        } catch (\Throwable $e) {
            return array();
        }
    }

    /**
     * 后台发货 POST api/shop/ship?order_id=&express_type=&express_no=
     * 说明：生产环境应加后台管理员鉴权，此处仅做字段写入示例
     */
    public function ship()
    {
        $this->options();
        $this->guardShop();
        // 发货属后台管理操作，必须校验管理员登录态
        if (empty($_SESSION['manage_uid'])) {
            $this->json(array('code' => 0, 'message' => '无权操作，请先登录后台'), 403);
        }
        $orderId     = intval($this->raw('order_id', 0));
        $expressType = trim($this->raw('express_type', ''));
        $expressNo   = trim($this->raw('express_no', ''));
        if ($orderId <= 0) $this->json(array('code' => 0, 'message' => '订单ID错误'), 400);
        if ($expressType === '' || $expressNo === '') {
            $this->json(array('code' => 0, 'message' => '请填写快递公司与单号'), 400);
        }
        $o = obj('api/ApiData')->dataSelect('yun_shop_order', array("`id`={$orderId}"));
        if (empty($o)) $this->json(array('code' => 0, 'message' => '订单不存在'));
        if ($o['status'] != 1) $this->json(array('code' => 0, 'message' => '仅已支付订单可发货'));
        obj('api/ApiData')->dataUpdate('yun_shop_order', array(
            'status'       => 2,
            'express_type' => $expressType,
            'express_no'   => $expressNo,
            'ship_time'    => date('Y-m-d H:i:s'),
        ), array("`id`={$orderId}"));
        $this->json(array('code' => 1, 'message' => '发货成功'));
    }

    private function orderStatusText($s)
    {
        return array('0' => '待支付', '1' => '已支付', '2' => '已发货', '3' => '已完成', '4' => '已取消')[$s] ?? '未知';
    }

    private function genOrderNo()
    {
        return date('YmdHis') . rand(1000, 9999) . substr(microtime(true) * 10000 % 10000, -4);
    }

    /**
     * 统一 dataSelect 返回值：无 order 返回单数组，有 order 返回二维数组。
     * 此处无论哪种都规范为二维数组，避免 array_map 等遍历报错。
     */
    private function rows($data)
    {
        if (empty($data)) return array();
        if (isset($data[0]) && is_array($data[0])) return $data;
        return array($data);
    }
}
