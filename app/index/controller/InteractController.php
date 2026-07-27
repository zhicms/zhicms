<?php
namespace app\index\controller;

/**
 * 互动控制器：文章评论、点赞（支持登录/未登录）
 * - 登录用户：使用用户名
 * - 未登录用户：昵称+邮箱+cookie 登记
 */
class InteractController extends \app\base\controller\BaseController {

    /**
     * 获取站点配置开关
     */
    private function getSwitch($key, $default = '1') {
        $row = obj("api/ApiData")->thisQuery(
            "SELECT `value` FROM `{pre}config` WHERE `key` = ?",
            array($key)
        );
        return !empty($row[0]['value']) ? $row[0]['value'] : $default;
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp() {
        $ip = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return substr($ip, 0, 64);
    }

    /**
     * 获取当前登录用户（未登录返回 null）
     */
    private function getLoginUser() {
        if (empty($_COOKIE['ZhiCmsUser'])) {
            return null;
        }
        return obj("index/global", "controller")->findUser("y", $_COOKIE['ZhiCmsUser'], "cookie");
    }

    /**
     * 获取/生成未登录访客标识 cookie
     */
    private function getVisitorCookie() {
        if (!empty($_COOKIE['zhicms_visitor'])) {
            return $_COOKIE['zhicms_visitor'];
        }
        $token = 'v_' . substr(md5(uniqid('', true) . microtime()), 0, 24);
        setcookie('zhicms_visitor', $token, time() + 86400 * 30, '/');
        return $token;
    }

    /**
     * XSS 过滤：去除 script 标签并转义 HTML
     */
    private function cleanContent($body) {
        $preg = "/<script[\s\S]*?<\/script>/i";
        $body = preg_replace($preg, "", $body);
        return htmlspecialchars(trim($body), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 检查评论间隔（防刷）
     */
    private function isTooFast($ip) {
        $interval = (int)$this->getSwitch('comment_interval', '60');
        if ($interval <= 0) return false;
        $recent = obj("api/ApiData")->thisQuery(
            "SELECT `id` FROM `{pre}comment` WHERE `ip` = ? AND `date` > DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1",
            array($ip, $interval)
        );
        return !empty($recent);
    }

    /**
     * 评论提交（支持登录/未登录）
     * POST: mybody, model(article/index/forum), id, pid(可选), poster(未登录), mail(未登录)
     */
    public function comSend() {
        // 1. 开关检查
        if ($this->getSwitch('comment_on', '1') !== '1') {
            exit(json_encode(array("info" => "评论功能已关闭", "status" => "n")));
        }

        // 2. 参数校验
        $rawBody = isset($_POST['mybody']) ? $_POST['mybody'] : '';
        $newBody = $this->cleanContent($rawBody);
        if ($newBody === '') {
            exit(json_encode(array("info" => "请填写评论", "status" => "n")));
        }
        if (mb_strlen($newBody, 'UTF-8') > 1000) {
            exit(json_encode(array("info" => "评论内容过长", "status" => "n")));
        }

        // 3. 模型映射
        $modelInput = $this->arg("model");
        $modelMap = array('index' => '1', 'article' => '2', 'forum' => '3');
        if (!isset($modelMap[$modelInput])) {
            exit(json_encode(array("info" => "请勿修改模型", "status" => "n")));
        }
        $model = $modelMap[$modelInput];

        // 4. ID 校验
        $id = $this->arg("id");
        if (!is_numeric($id) || $id <= 0) {
            exit(json_encode(array("info" => "参数错误", "status" => "n")));
        }
        $id = (int)$id;

        // 5. pid 回复校验
        $pid = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;
        if ($pid < 0) $pid = 0;

        // 6. 登录/未登录用户处理
        $uInfo = $this->getLoginUser();
        $ip = $this->getClientIp();

        // 防刷检查
        if ($this->isTooFast($ip)) {
            exit(json_encode(array("info" => "评论太快，请稍后再试", "status" => "n")));
        }

        $uid = 0;
        $poster = '';
        $mail = '';
        $avatar = '';
        $hide = 'n';

        if (!empty($uInfo)) {
            // 登录用户
            $uid = (int)$uInfo['id'];
            $poster = $uInfo['username'];
            $mail = isset($uInfo['email']) ? $uInfo['email'] : '';
        } else {
            // 未登录用户
            if ($this->getSwitch('comment_anonymous', '1') !== '1') {
                exit(json_encode(array("info" => "请先登录后再评论", "status" => "n")));
            }
            $poster = isset($_POST['poster']) ? trim($_POST['poster']) : '';
            $mail = isset($_POST['mail']) ? trim($_POST['mail']) : '';
            if (mb_strlen($poster, 'UTF-8') < 1 || mb_strlen($poster, 'UTF-8') > 30) {
                exit(json_encode(array("info" => "请填写昵称（1-30字）", "status" => "n")));
            }
            if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                exit(json_encode(array("info" => "邮箱格式不正确", "status" => "n")));
            }
            $poster = htmlspecialchars($poster, ENT_QUOTES, 'UTF-8');
            // 设置 cookie 记住访客信息（30天）
            setcookie('zhicms_comment_name', $poster, time() + 86400 * 30, '/');
            setcookie('zhicms_comment_mail', $mail, time() + 86400 * 30, '/');
            // 登记访客标识
            $this->getVisitorCookie();
        }

        // 7. 审核开关
        if ($this->getSwitch('comment_check', '0') === '1') {
            $hide = 'y';
        }

        // 8. 如果是回复，拼接 @ 用户名
        if ($pid > 0) {
            $parent = obj("api/ApiData")->thisQuery(
                "SELECT `poster` FROM `{pre}comment` WHERE `id` = ? AND `pid` >= 0 LIMIT 1",
                array($pid)
            );
            if (!empty($parent[0]['poster'])) {
                $newBody = '@' . $parent[0]['poster'] . '：' . $newBody;
            }
        }

        // 9. 入库
        $data = array(
            'uid' => $uid,
            'pid' => $pid,
            'poster' => $poster,
            'mail' => $mail,
            'avatar' => $avatar,
            'mid' => $id,
            'model' => $model,
            'content' => $newBody,
            'hide' => $hide,
            'ip' => $ip,
            'agent' => substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 255),
            'top' => 'n',
            'like_count' => 0,
            'date' => date("Y-m-d H:i:s", time())
        );
        $newId = obj("api/ApiData")->insertData("yun_comment", $data);

        // 10. 更新文章评论数缓存（如果 model=2 文章）
        if ($model === '2') {
            obj("api/ApiData")->executeQuery(
                "UPDATE `{pre}article` SET `view` = `view` WHERE `id` = ?",
                array($id)
            );
        }

        $displayName = $poster ?: '访客';
        $initial = mb_substr($displayName, 0, 1, 'UTF-8');

        exit(json_encode(array(
            "info" => $hide === 'y' ? "评论已提交，审核后显示" : "评论成功",
            "status" => "y",
            "cid" => $newId,
            "username" => $displayName,
            "initial" => $initial,
            "content" => $newBody,
            "date" => $data['date'],
            "hide" => $hide
        )));
    }

    /**
     * 文章点赞
     * GET/POST: id(文章ID)
     */
    public function likeArticle() {
        $id = $this->arg("id");
        if (!is_numeric($id) || $id <= 0) {
            exit(json_encode(array("info" => "参数错误", "status" => "n")));
        }
        $id = (int)$id;

        $uInfo = $this->getLoginUser();
        $ip = $this->getClientIp();
        $cookie = $this->getVisitorCookie();

        $uid = !empty($uInfo) ? (int)$uInfo['id'] : 0;

        // 防重复：按 uid 或 ip+cookie 检查
        if ($uid > 0) {
            $exists = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'article' AND `uid` = ? LIMIT 1",
                array($id, $uid)
            );
        } else {
            $exists = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'article' AND `ip` = ? AND `cookie` = ? LIMIT 1",
                array($id, $ip, $cookie)
            );
        }
        if (!empty($exists)) {
            exit(json_encode(array("info" => "已点过赞", "status" => "n", "liked" => true)));
        }

        // 文章 like 字段 +1
        obj("api/ApiData")->executeQuery(
            "UPDATE `{pre}article` SET `like` = `like` + 1 WHERE `id` = ?",
            array($id)
        );

        // 记录点赞
        obj("api/ApiData")->executeQuery(
            "INSERT INTO `{pre}like` (`fid`, `uid`, `model`, `ip`, `cookie`, `date`) VALUES (?, ?, 'article', ?, ?, ?)",
            array($id, $uid, $ip, $cookie, date("Y-m-d H:i:s", time()))
        );

        // 返回最新点赞数
        $row = obj("api/ApiData")->thisQuery(
            "SELECT `like` FROM `{pre}article` WHERE `id` = ? LIMIT 1",
            array($id)
        );
        $count = !empty($row[0]['like']) ? (int)$row[0]['like'] : 0;

        exit(json_encode(array("info" => "点赞成功", "status" => "y", "count" => $count)));
    }

    /**
     * 评论点赞
     * GET/POST: id(评论ID)
     */
    public function likeComment() {
        $id = $this->arg("id");
        if (!is_numeric($id) || $id <= 0) {
            exit(json_encode(array("info" => "参数错误", "status" => "n")));
        }
        $id = (int)$id;

        $uInfo = $this->getLoginUser();
        $ip = $this->getClientIp();
        $cookie = $this->getVisitorCookie();
        $uid = !empty($uInfo) ? (int)$uInfo['id'] : 0;

        // 防重复
        if ($uid > 0) {
            $exists = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'comment' AND `uid` = ? LIMIT 1",
                array($id, $uid)
            );
        } else {
            $exists = obj("api/ApiData")->thisQuery(
                "SELECT `id` FROM `{pre}like` WHERE `fid` = ? AND `model` = 'comment' AND `ip` = ? AND `cookie` = ? LIMIT 1",
                array($id, $ip, $cookie)
            );
        }
        if (!empty($exists)) {
            exit(json_encode(array("info" => "已点过赞", "status" => "n", "liked" => true)));
        }

        // 评论 like_count +1
        obj("api/ApiData")->executeQuery(
            "UPDATE `{pre}comment` SET `like_count` = `like_count` + 1 WHERE `id` = ?",
            array($id)
        );

        // 记录点赞
        obj("api/ApiData")->executeQuery(
            "INSERT INTO `{pre}like` (`fid`, `uid`, `model`, `ip`, `cookie`, `date`) VALUES (?, ?, 'comment', ?, ?, ?)",
            array($id, $uid, $ip, $cookie, date("Y-m-d H:i:s", time()))
        );

        // 返回最新点赞数
        $row = obj("api/ApiData")->thisQuery(
            "SELECT `like_count` FROM `{pre}comment` WHERE `id` = ? LIMIT 1",
            array($id)
        );
        $count = !empty($row[0]['like_count']) ? (int)$row[0]['like_count'] : 0;

        exit(json_encode(array("info" => "点赞成功", "status" => "y", "count" => $count)));
    }
}
