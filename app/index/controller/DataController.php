<?php
namespace app\index\controller;

class DataController extends \app\base\controller\BaseController {

    public function index(){
        header("Content-type: text/html; charset=utf-8");
        include CONFIG_PATH . 'siteconfig.php';
        $token = new \ZhiCms\ext\Weixin;

        if ($this->arg("key") != $Siteinfo['key']) {
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
            $data['title'] = $value['title'];
            $data['pic'] = $value['pic'];
            $data['shop'] = $value['shop'];
            $data['country'] = $value['country'];
            $data['type'] = $value['type'];
            $data['body'] = $value['body'];
            $data['link'] = $link;
            $data['date'] = $value['date'];
            $data['time'] = $value['date'];
            $data['website'] = $value['website'];

            obj("api/ApiData")->insertData("yun_skuitems", $data);
                echo 'ok'."<br>";
            }
        }
    }
}