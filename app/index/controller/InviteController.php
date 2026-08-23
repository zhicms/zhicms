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
}
