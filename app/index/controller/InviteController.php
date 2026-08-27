<?php
namespace app\index\controller;

/**
 * 邀请落地页（分享 H5 的统一入口之一）
 *
 * 路由：
 *   index.php?r=index/invite/index&inviter=邀请码或uid
 *
 * 行为：
 *   1. 读取 URL 上的 inviter 参数（邀请人 invite_code 或 uid）
 *   2. 写入 Cookie（inviter_code，30 天），供后续注册/登录接口归因
 *   3. 渲染落地页，展示“XXX 邀请你加入”，引导去注册页 / 下载小程序
 */
class InviteController extends \app\base\controller\BaseController
{
    const INVITER_COOKIE = 'inviter_code';
    const COOKIE_EXPIRE  = 2592000; // 30 天

    public function index()
    {
        $inviter = isset($_GET['inviter']) ? trim($_GET['inviter']) : '';

        // 校验：邀请码为字母数字，uid 为纯数字
        if ($inviter !== '' && !preg_match('/^[A-Za-z0-9]+$/', $inviter)) {
            $inviter = '';
        }

        if ($inviter !== '') {
            setcookie(self::INVITER_COOKIE, $inviter, time() + self::COOKIE_EXPIRE, '/');
            $_COOKIE[self::INVITER_COOKIE] = $inviter;
        } else {
            // 已访问过则沿用已存的邀请关系
            $inviter = isset($_COOKIE[self::INVITER_COOKIE]) ? $_COOKIE[self::INVITER_COOKIE] : '';
        }

        // 解析邀请人昵称（用于展示“XXX 邀请你”）
        $inviterName = $this->resolveInviterName($inviter);

        $this->inviter     = $inviter;
        $this->inviterName = $inviterName;
        $this->regUrl      = '/index.php?r=index/login/register';
        $this->display();
    }

    /**
     * 根据 invite_code 或 uid 反查邀请人昵称，失败返回空
     */
    private function resolveInviterName($raw)
    {
        if ($raw === '') return '';
        $uid = null;
        if (ctype_digit($raw)) {
            $uid = intval($raw);
        } else {
            $row = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}user` WHERE `invite_code` = ? LIMIT 1",
                array($raw)
            );
            if (isset($row[0]['id'])) $uid = intval($row[0]['id']);
        }
        if (!$uid) return '';
        $u = obj("index/global", "controller")->findUser("u", $uid, "uid");
        if (!$u) return '';
        return isset($u['username']) && $u['username'] !== '' ? $u['username'] : ('用户#' . $uid);
    }

    /**
     * 邀请注册 + 商品 落地页（淘客商品分享）
     *
     * 路由：index.php?r=index/invite/goods&id=<goodsId>&type=tb&inviter=邀请码
     *
     * 行为：
     *   1. 处理 inviter（写 Cookie + 反查邀请人昵称）
     *   2. 根据 id 调详情接口拉取商品参数（与 cheaps/detail 同源）
     *   3. 渲染落地页：展示商品信息 + 邀请注册入口
     */
    public function goods()
    {
        $inviter = isset($_GET['inviter']) ? trim($_GET['inviter']) : '';
        $goodsId = trim($this->arg('id'));
        $type    = $this->arg('type');
        $valid   = array('tb', 'jd', 'pdd', 'vip', 'taobao');
        $platform = ($type && in_array($type, $valid)) ? $type : 'tb';
        if ($platform == 'taobao' || $platform == 'dtk') {
            $platform = 'tb';
        }

        // 校验并写入邀请关系 Cookie（30 天）
        if ($inviter !== '' && preg_match('/^[A-Za-z0-9]+$/', $inviter)) {
            setcookie(self::INVITER_COOKIE, $inviter, time() + self::COOKIE_EXPIRE, '/');
            $_COOKIE[self::INVITER_COOKIE] = $inviter;
        } else {
            $inviter = isset($_COOKIE[self::INVITER_COOKIE]) ? $_COOKIE[self::INVITER_COOKIE] : '';
        }

        $inviterName = $this->resolveInviterName($inviter);

        // 拉取商品详情（与 cheaps/detail 同源）
        $item = null;
        $platformName = array('taobao' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会')[$platform] ?? '商城';
        if ($goodsId) {
            $goodsId = addslashes($goodsId);
            try {
                $tjk = new \ZhiCms\ext\Tjk();
                $res = $tjk->getGoodsDetail($goodsId, $platform);
                $resData = !empty($res['data']) ? $res['data'] : (!empty($res['item']) ? $res['item'] : null);
                if (($res['code'] ?? 0) == 1 && !empty($resData)) {
                    // 用 standardizeItem 归一化字段（getGoodsDetail 返回的是原始接口数据，字段名不统一）
                    $item = \ZhiCms\ext\Tjk::standardizeItem((array)$resData, $platform);
                    // 强制补全主键：详情接口返回的 goodsId 可能为空或不一致，用请求的 id 兜底
                    if (empty($item['goodsId'])) $item['goodsId'] = $goodsId;
                    // 价格兜底：详情接口个别字段缺失时，尝试从原始数据取别名
                    if (empty($item['actualPrice'])) $item['actualPrice'] = $resData['actualPrice'] ?? $resData['price'] ?? $resData['itemprice'] ?? 0;
                }
            } catch (\Exception $e) {
                $item = null;
            }
            // 详情接口失败时兜底查数据库 yun_items
            if (empty($item)) {
                try {
                    // 先转义引号（防 SQL 注入闭合），再转义 LIKE 通配符 % _ \（防全表扫描/ReDoS）
                $safeGoodsId = addslashes($goodsId);
                $safeGoodsId = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $safeGoodsId);
                $db = obj('api/ApiData')->thisQuery("SELECT * FROM yun_items WHERE goodsId = '{$safeGoodsId}' AND del = 0 LIMIT 1");
                    if (!empty($db)) $item = $db[0];
                } catch (\Exception $e) {
                    $item = null;
                }
            }
        }

        // 组装展示字段
        $price = $item['actualPrice'] ?? $item['price'] ?? '';
        $originalPrice = $item['originalPrice'] ?? 0;
        $couponPrice = $item['couponPrice'] ?? 0;
        $savedAmount = number_format(floatval($originalPrice) - floatval($price), 2);
        $mainPic = $item['mainPic'] ?? $item['pic'] ?? (($item['images'] && is_array($item['images'])) ? $item['images'][0] : '');

        // 详情切图
        $detailPics = $item['detailPics'] ?? '';
        if (is_string($detailPics)) $detailPics = json_decode($detailPics, true);
        $detailPicsHtml = '';
        if (is_array($detailPics) && !empty($detailPics)) {
            foreach ($detailPics as $img) {
                if (!empty($img)) {
                    $detailPicsHtml .= '<img src="' . htmlspecialchars($img) . '" alt="商品详情" style="width:100%;height:auto;margin-bottom:12px;border-radius:8px;">';
                }
            }
        }

        $this->ret = $item;
        $this->item = $item;
        $this->platform = $platform;
        $this->platformName = $platformName;
        $this->price = $price;
        $this->originalPrice = $originalPrice;
        $this->couponPrice = $couponPrice;
        $this->savedAmount = $savedAmount;
        $this->mainPic = $mainPic;
        $this->detailPicsHtml = $detailPicsHtml;
        $this->inviter = $inviter;
        $this->inviterName = $inviterName;
        $this->regUrl = '/index.php?r=index/login/register';
        $this->display('app/index/view/invite/goods');
    }
}
