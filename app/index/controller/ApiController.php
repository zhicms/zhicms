<?php
namespace app\index\controller;

class ApiController extends \app\base\controller\BaseController {

  private function getApiKey() {
    static $key = null;
    if ($key === null) {
      $apiConfig = include CONFIG_PATH . 'apiset.php';
      $key = isset($apiConfig['secretkey']) && !empty($apiConfig['secretkey']) ? $apiConfig['secretkey'] : 'zhangyuan';
    }
    return $key;
  }

  public function insertData(){
    $key = $this->arg("pw");
    if ($key != $this->getApiKey()) {
      exit(json_encode(array("state"=>"key wrong","code"=>"001")));
    }

    $link = isset($_POST['link']) ? trim($_POST['link']) : '';
    if (empty($link)) {
      exit(json_encode(array("state"=>"link empty","code"=>"003")));
    }

    $where[] = "`link` = :link";
    $params = array(':link' => $link);
    $c = obj('api/ApiData')->thisQuery("SELECT * FROM `{pre}skuitems` WHERE `link` = :link", $params);

    if (empty($c)) {
      $pic = obj("base/qiniu")->index($_POST['pic']);

      $data['title'] = $_POST['title'];
      $data['pic'] = $pic;
      $data['shop'] = $_POST['shop'];
      $data['time'] = $_POST['time'];
      $data['country'] = $_POST['country'] == "国内" ? "0" : "1";
      $data['type'] = $_POST['type'];
      $data['body'] = $_POST['body'] . "<img src='{$pic}' style='width:400px; height:auto'>";
      $data['link'] = $link;
      $data['date'] = $_POST['date'];
      $data['website'] = "惠惠网";

      obj("api/ApiData")->insertData("yun_skuitems", $data);
      echo json_encode(array("state"=>"ok","code"=>"002"));
    } else {
      echo json_encode(array("state"=>"重复数据","code"=>"002"));
    }
  }

  public function haitaoBei(){
    $key = $this->arg("pw");
    if ($key != $this->getApiKey()) {
      exit(json_encode(array("state"=>"key wrong","code"=>"001")));
    }

    $link = isset($_POST['link']) ? trim($_POST['link']) : '';
    if (empty($link)) {
      exit(json_encode(array("state"=>"link empty","code"=>"003")));
    }

    $params = array(':link' => $link);
    $c = obj('api/ApiData')->thisQuery("SELECT * FROM `{pre}skuitems` WHERE `link` = :link", $params);

    if (empty($c)) {
      $body = preg_replace("#<(/?a.*?)>#si", '', $_POST['body']);
      $strReplace = str_replace(
        array(' 海淘不孤单，海淘中有任何问题都可入"海淘超级5000人群"（群号：点击查看）咨询海淘达人管理员，钱哥或小燕！', '，还可以加入ebay海淘交流群（334166322）讨论。'),
        array('', '。'),
        self::plusImg("y", $body)
      );

      $data['title'] = $_POST['title'];
      $data['pic'] = obj("base/qiniu")->index($_POST['pic']);
      $data['shop'] = $_POST['shop'];
      $data['time'] = $_POST['time'];
      $data['country'] = "1";
      $data['type'] = $_POST['type'];
      $data['body'] = $strReplace;
      $data['link'] = $link;
      $data['website'] = "海淘贝";
      $data['date'] = $_POST['date'];

      obj("api/ApiData")->insertData("yun_skuitems", $data);
      echo json_encode(array("state"=>"ok","code"=>"002"));
    }
  }

  public function plusImg($lock, $body){
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