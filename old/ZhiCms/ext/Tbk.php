<?php
namespace ZhiCms\ext;
error_reporting('0');
class Tbk
{
  //获取单个产品数据
  public function getinfo($appkey,$secretKey,$itemiid){
     require_once(__DIR__.'/taobao/TopSdk.php');
    $c = new \TopClient;
$c->appkey = $appkey;
$c->secretKey = $secretKey;
$req = new \TbkItemInfoGetRequest;
$req->setNumIids($itemiid);
$req->setPlatform("1");
$resp = $c->execute($req);
$content = json_encode($resp); //解析参数获得数组
$json = json_decode($content, true);//再次解析参数获得数组
 return $json['results']['n_tbk_item'];//数据输出
  }
    //获取产品淘口令
public function getinfotkl($appkey,$secretKey,$itemiid,$url){
require_once(__DIR__.'/taobao/TopSdk.php');
$c = new \TopClient;
$c->appkey = $appkey;
$c->secretKey = $secretKey;
$req = new \TbkItemInfoGetRequest;
$req->setNumIids($itemiid);
$req->setPlatform("2");
$resp = $c->execute($req);
$content = json_encode($resp); //解析参数获得数组
$json = json_decode($content, true);//再次解析参数获得数组
$info=$json['results']['n_tbk_item'];//数据输出
$reqb = new \TbkTpwdCreateRequest;
$reqb->setText($info["title"]);
$reqb->setUrl($url);
$reqb->setLogo($info["pict_url"]);
$respb = $c->execute($reqb);
$contentb = json_encode($respb); //解析参数获得数组
$jsonb = json_decode($contentb, true);//再次解析参数获得数组
$kouling=$jsonb['data']['model'];//数据输出
return array('bt'=>$info["title"],'tkl'=>$kouling);
}

    //获取搜索产品列表数据
public function getlist($appkey,$secretKey,$page,$pricemin,$pricemax,$tm,$sx,$by,$keyword,$yhq,$imei){
require_once(__DIR__.'/taobao/TopSdk.php');
$c = new \TopClient;
$c->appkey = $appkey;
$c->secretKey = $secretKey;
$req = new \TbkDgMaterialOptionalRequest;
//$req->setStartDsr("10");
$req->setPageSize("10");
$req->setPageNo($page);//当前页码
$req->setPlatform("1");
$req->setStartPrice($pricemin);//折扣范围下限
$req->setEndPrice($pricemax);//折扣访问上限
//$req->setIp(get_client_ip());
$req->setIsTmall($tm);// 判断是否天猫
$req->setSort($sx);//筛选排序 排序_des（降序），排序_asc（升序），销量（total_sales），淘客佣金比率（tk_rate）， 累计推广量（tk_total_sales），总支出佣金（tk_total_commi），价格（price）
$req->setNeedFreeShipment($by);//是否包邮
$req->setQ($keyword);
$req->setMaterialId("2836");
$req->setHasCoupon($yhq);//优惠券设置 当为true表示优惠券，false表示综合
$req->setDeviceEncrypt("MD5");
$req->setDeviceValue(md5($imei));
$req->setDeviceType("IMEI");
$req->setAdzoneId("105582450025");
$req->setNpxLevel("2");

$resp = $c->execute($req);

$content = json_encode($resp); //解析参数获得数组
$json = json_decode($content, true);//再次解析参数获得数组
$list=$json['result_list']['map_data'];//数据输出
return array('list'=>$list);
}
}