<?php
namespace app\index\controller;

/**
 * 数据导入控制器（合并原 ApiController + DataController）
 * 用途：接收外部系统的数据推送，写入 yun_skuitems 表
 * 调用方式：URL 直接访问（index.php?r=index/import/xxx&pw=xxx）
 */
class ImportController extends \app\base\controller\BaseController {

    private function getApiKey() {
        static $key = null;
        if ($key === null) {
            // 统一使用后台「网站设置 → 安全 Key」（site.security_key），兼容旧版 api.secretkey
            $siteConfig = \app\common\ConfigStore::load('site');
            $key = (isset($siteConfig['security_key']) && !empty($siteConfig['security_key']))
                ? $siteConfig['security_key']
                : (\app\common\ConfigStore::load('api')['secretkey'] ?? 'zhangyuan');
        }
        return $key;
    }

    /* ---- 原 ApiController::insertData ---- */
    public function insertData() {
        $key = $this->arg("pw");
        if ($key != $this->getApiKey()) {
            exit(json_encode(array("state" => "key wrong", "code" => "001")));
        }

        $link = isset($_POST['link']) ? trim($_POST['link']) : '';
        if (empty($link)) {
            exit(json_encode(array("state" => "link empty", "code" => "003")));
        }

        $params = array(':link' => $link);
        $c = obj('api/ApiData')->thisQuery("SELECT * FROM `{pre}skuitems` WHERE `link` = :link", $params);

        if (empty($c)) {
            $pic = obj("base/qiniu")->index($_POST['pic']);

            $data['title']   = $_POST['title'];
            $data['pic']     = $pic;
            $data['shop']    = $_POST['shop'];
            $data['time']    = $_POST['time'];
            $data['country'] = $_POST['country'] == "国内" ? "0" : "1";
            $data['type']    = $_POST['type'];
            $data['body']    = $_POST['body'] . "<img src='{$pic}' style='width:400px; height:auto'>";
            $data['link']    = $link;
            $data['date']    = $_POST['date'];
            $data['website'] = "惠惠网";

            obj("api/ApiData")->insertData("yun_skuitems", $data);
            echo json_encode(array("state" => "ok", "code" => "002"));
        } else {
            echo json_encode(array("state" => "重复数据", "code" => "002"));
        }
    }

    /* ---- 原 ApiController::haitaoBei ---- */
    public function haitaoBei() {
        $key = $this->arg("pw");
        if ($key != $this->getApiKey()) {
            exit(json_encode(array("state" => "key wrong", "code" => "001")));
        }

        $link = isset($_POST['link']) ? trim($_POST['link']) : '';
        if (empty($link)) {
            exit(json_encode(array("state" => "link empty", "code" => "003")));
        }

        $params = array(':link' => $link);
        $c = obj('api/ApiData')->thisQuery("SELECT * FROM `{pre}skuitems` WHERE `link` = :link", $params);

        if (empty($c)) {
            $body = preg_replace("#<(/?a.*?)>#si", '', $_POST['body']);
            $strReplace = str_replace(
                array(' 海淘不孤单，海淘中有任何问题都可入"海淘超级5000人群"（群号：点击查看）咨询海淘达人管理员，钱哥或小燕！', '，还可以加入ebay海淘交流群（334166322）讨论。'),
                array('', '。'),
                $this->plusImg("y", $body)
            );

            $data['title']   = $_POST['title'];
            $data['pic']     = obj("base/qiniu")->index($_POST['pic']);
            $data['shop']    = $_POST['shop'];
            $data['time']    = $_POST['time'];
            $data['country'] = "1";
            $data['type']    = $_POST['type'];
            $data['body']    = $strReplace;
            $data['link']    = $link;
            $data['website'] = "海淘贝";
            $data['date']    = $_POST['date'];

            obj("api/ApiData")->insertData("yun_skuitems", $data);
            echo json_encode(array("state" => "ok", "code" => "002"));
        }
    }

    /* ---- 原 DataController::index ---- */
    public function index() {
        header("Content-type: text/html; charset=utf-8");
        $Siteinfo = \app\common\ConfigStore::load('site');
        $token = new \ZhiCms\ext\Weixin;

        // 统一使用后台「安全 Key」（site.security_key），兼容旧版 site.key
        $securityKey = (isset($Siteinfo['security_key']) && !empty($Siteinfo['security_key']))
            ? $Siteinfo['security_key']
            : (isset($Siteinfo['key']) ? $Siteinfo['key'] : 'zhicms');
        if ($this->arg("key") != $securityKey) {
            exit("key error!");
        }

        $ret = obj("api/Api")->objectArray(json_decode($token->http($url)));

        foreach (array_reverse($ret) as $key => $value) {
            $link = isset($value['link']) ? trim($value['link']) : '';
            if (empty($link)) {
                continue;
            }

            $params = array(':link' => $link);
            $c = obj('api/ApiData')->thisQuery("SELECT * FROM `{pre}skuitems` WHERE `link` = :link", $params);

            if (empty($c)) {
                $data['title']   = $value['title'];
                $data['pic']     = $value['pic'];
                $data['shop']    = $value['shop'];
                $data['country'] = $value['country'];
                $data['type']    = $value['type'];
                $data['body']    = $value['body'];
                $data['link']    = $link;
                $data['date']    = $value['date'];
                $data['time']    = $value['date'];
                $data['website'] = $value['website'];

                obj("api/ApiData")->insertData("yun_skuitems", $data);
                echo 'ok' . "<br>";
            }
        }
    }

    private function plusImg($lock, $body) {
        if ($lock != "y") {
            return $body;
        }

        preg_match_all("/<img([^>]*)\s*src=('|\")([^'\"]+)('|\")/", $body, $match, PREG_PATTERN_ORDER);
        $img = array();
        $fileUrl = array();

        foreach ($match[3] as $key => $imgUrl) {
            $img[] = trim($imgUrl);
            $fileUrl[] = obj("base/qiniu")->index(trim($imgUrl));
        }

        return str_replace($img, $fileUrl, $body);
    }
}
