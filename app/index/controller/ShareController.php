<?php
namespace app\index\controller;

/**
 * 分享中转页（统一 H5 分享入口）
 *
 * 路由：
 *   index.php?r=index/share/index&type=shop&id=123&inviter=邀请码
 *
 * 行为：
 *   1. 读取 inviter 写入 Cookie（30 天），供后续注册/登录归因
 *   2. 按 type 跳转到真实的网页版详情页：
 *        article-> /index.php?r=index/index/view&id=<id>               (网页版文章详情)
 *        tb     -> /index.php?r=index/invite/goods&id=<id>&type=tb
 *        jd/pdd/vip -> 同 invite/goods（按对应平台）
 *        shop   -> /index.php?r=index/shop/index                        (自营暂无详情页，兜底列表)
 *        app    -> /index.php?r=index/invite/index&inviter=<inviter>
 *      其余（post 等暂未实现）-> 兜底首页，避免 404
 */
class ShareController extends \app\base\controller\BaseController
{
    const INVITER_COOKIE = 'inviter_code';
    const COOKIE_EXPIRE  = 2592000; // 30 天

    public function index()
    {
        $type    = isset($_GET['type'])    ? trim($_GET['type'])    : '';
        $id      = isset($_GET['id'])      ? trim($_GET['id'])      : '';
        $inviter = isset($_GET['inviter']) ? trim($_GET['inviter']) : '';

        if ($inviter !== '' && preg_match('/^[A-Za-z0-9]+$/', $inviter)) {
            setcookie(self::INVITER_COOKIE, $inviter, time() + self::COOKIE_EXPIRE, '/');
            $_COOKIE[self::INVITER_COOKIE] = $inviter;
        }

        // 兼容旧分享链接格式：/share?inviter=xxx&id=商品id（未带 type）
        //   有 id 视为淘客商品分享（默认淘宝），无 id 视为纯邀请
        if ($type === '') {
            $type = $id !== '' ? 'tb' : 'app';
        }

        $target = $this->resolveTarget($type, $id, $inviter);

        // 服务端跳转
        header('Location: ' . $target, true, 302);
        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES) . '">';
        exit;
    }

    /**
     * 按 type 计算落地地址
     *   tb/jd/pdd/vip  -> 网页版淘客商品详情（cheaps/detail，已存在）
     *   shop           -> 自营商城商品，网页版暂无详情页，兜底商城列表
     *   article/post   -> 网页版暂无对应详情页，兜底首页
     *   app            -> 邀请落地页
     */
    private function resolveTarget($type, $id, $inviter)
    {
        $base = '/index.php';
        switch ($type) {
            case 'article':
                // 文章分享：网页版文章详情页（yun_article.id 与 App 端一致），避免打开错页
                return $base . '?r=index/index/view&id=' . rawurlencode($id);
            case 'tb':
            case 'jd':
            case 'pdd':
            case 'vip':
                // 淘客商品分享：跳到"邀请注册 + 商品"落地页，同时展示商品信息和邀请入口
                return $base . '?r=index/invite/goods&id=' . rawurlencode($id) . '&type=' . rawurlencode($type)
                    . ($inviter ? '&inviter=' . rawurlencode($inviter) : '');
            case 'shop':
                // 自营商城商品网页版暂无详情页，兜底到商城列表（不 404）
                return $base . '?r=index/shop/index';
            case 'app':
                return $base . '?r=index/invite/index' . ($inviter ? '&inviter=' . rawurlencode($inviter) : '');
            default:
                // article / post 等网页版详情页暂未实现：兜底首页
                return '/';
        }
    }
}
