<?php
namespace app\index\controller;

class ViewController extends \app\base\controller\BaseController
{

    
        public function view(){
              $goodsId=$this->arg('id');
              $type=$this->arg('type') ?: 'taobao';
        $this->redirect(url('index/view/detail', array('id' => $goodsId, 'type' => $type)));

    }
    
        public function vip(){
              $goodsId=$this->arg('id');
        $this->redirect(url('index/view/detail', array('id' => $goodsId, 'type' => 'vip')));

    }
    
    
    	public function detail(){
        $goodsId=$this->arg('id');
        $type=$this->arg('type');
        $valid=array('tb','jd','pdd','vip','taobao');
        $platform = '';

        if($type && in_array($type,$valid)){
            $platform = $type;
            // 大淘客淘宝标记 dtk 以及全拼 taobao 统一规范为 tb
            if($platform == 'taobao' || $platform == 'dtk') $platform = 'tb';
        } else {
            $platform = 'tb';
        }
        
        $item = null;
        $itemFromDb = null;
        
        if($goodsId){
            // 先转义引号（防 SQL 注入闭合），再转义 LIKE 通配符 % _ \（防全表扫描/ReDoS）
            $goodsId = addslashes($goodsId);
            $goodsId = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $goodsId);
            $itemFromDb = obj('api/ApiData')->thisQuery("SELECT * FROM yun_items WHERE goodsId = '{$goodsId}' AND del = 0 LIMIT 1");
            if(!empty($itemFromDb)){
                $itemFromDb = $itemFromDb[0];
                if(!$platform || $platform == 'taobao'){
                    $dbFrom = $itemFromDb['item_from'] ?? ($itemFromDb['laiyuan'] == 1 ? 'taobao' : ($itemFromDb['laiyuan'] == 4 ? 'jd' : ($itemFromDb['laiyuan'] == 2 ? 'pdd' : ($itemFromDb['laiyuan'] == 3 ? 'vip' : 'taobao'))));
                    // 大淘客淘宝标记 dtk 统一规范为 tb
                    if($dbFrom == 'dtk' || $dbFrom == 'taobao') $dbFrom = 'tb';
                    $platform = $dbFrom;
                }
            }
        }
        
        if($platform && $goodsId){
            $tjk=new \ZhiCms\ext\Tjk();
            $res=$tjk->getGoodsDetail($goodsId,$platform);
            // getGoodsDetail 返回键为 'data'（Dtk/Hdk::GetGoodsDetails 输出），兼容旧 'item' 键
            $resData = !empty($res['data']) ? $res['data'] : (!empty($res['item']) ? $res['item'] : null);
            if($res['code']==1 && !empty($resData)){
                $item = $resData;
                if(!empty($itemFromDb)){
                    $item = array_merge($itemFromDb, $item);
                }
                $this->ret=$item;
                $this->platform=$platform;
                $this->setSeoData($item, $platform);
                $this->loadCommonSidebar();
                $this->display('app/index/view/view/smzdm_detail');
                return;
            }
        }
        
        if(!empty($itemFromDb)){
            $this->ret=$itemFromDb;
            $this->platform=$platform;
            $this->setSeoData($itemFromDb, $platform);
            $this->loadCommonSidebar();
            $this->display('app/index/view/view/smzdm_detail');
            return;
        }
        
        $this->ret=null;
        $this->platform=$platform;
        $platformName = ['taobao' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会'][$platform] ?? '商城';
        $platformIcon = ['taobao' => '🛒', 'jd' => '🔴', 'pdd' => '🟠', 'vip' => '💝'][$platform] ?? '🛒';
        $platformClass = ['taobao' => 'tb', 'jd' => 'jd', 'pdd' => 'pdd', 'vip' => 'vip'][$platform] ?? 'tb';
        $this->platformName = $platformName;
        $this->platformIcon = $platformIcon;
        $this->platformClass = $platformClass;
        $this->errmsg='商品信息获取失败，请稍后重试';
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $this->pageTitle = '商品详情 - ' . (obj('base/Base')->SEO('view_title') ?: $siteName);
        $this->pageKeywords = obj('base/Base')->SEO('view_keywords') ?: obj('base/Base')->SiteConfig('sitekeywords');
        $this->pageDescription = obj('base/Base')->SEO('view_dec') ?: obj('base/Base')->SiteConfig('sitedescription');
        $this->canonicalUrl = obj('base/Base')->SiteConfig('hosturl') . 'detail-' . $goodsId . '.html';
        $this->loadCommonSidebar();
        $this->display('app/index/view/view/smzdm_detail');
    }
    
    private function setSeoData($item, $platform){
        $title = $item['title'] ?? '';
        $platformName = ['taobao' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会'][$platform] ?? '商城';
        $platformIcon = ['taobao' => '🛒', 'jd' => '🔴', 'pdd' => '🟠', 'vip' => '💝'][$platform] ?? '🛒';
        $platformClass = ['taobao' => 'tb', 'jd' => 'jd', 'pdd' => 'pdd', 'vip' => 'vip'][$platform] ?? 'tb';
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $this->pageTitle = $title . ' - ' . $platformName . '优惠券 - ' . $siteName;
        $this->pageKeywords = ($item['dtitle'] ?? '') . ',' . obj('base/Base')->SEO('view_keywords') . ',' . $platformName;
        $this->pageDescription = mb_substr(strip_tags($item['content'] ?? $item['dtitle'] ?? $title), 0, 180, 'UTF-8') ?: ($title . '|' . $platformName . '优惠券');
        $this->canonicalUrl = obj('base/Base')->SiteConfig('hosturl') . 'detail-' . ($item['goodsId'] ?? '') . '.html';
        // Open Graph 图片
        if (!empty($item['mainPic'])) {
            $this->ogImage = $item['mainPic'];
        }
        $this->platformName = $platformName;
        $this->platformIcon = $platformIcon;
        $this->platformClass = $platformClass;
        $this->detailPicsHtml = $this->parseDetailPics($item);
        $this->shopTypeText = !empty($item['shopType']) && $item['shopType'] == 1 ? '天猫' : '淘宝';
        $originalPrice = floatval($item['originalPrice'] ?? 0);
        $actualPrice = floatval($item['actualPrice'] ?? 0);
        $this->saveAmount = $originalPrice > $actualPrice ? number_format($originalPrice - $actualPrice, 2) : '';
        $this->showOriginalPrice = $originalPrice > 0 && $originalPrice > $actualPrice;
        $this->showCouponEndTime = !empty($item['couponEndTime']) && $item['couponEndTime'] != '0';
        $this->showCouponStartTime = !empty($item['couponStartTime']) && $item['couponStartTime'] != '0';
        $this->buyUrl = url('index/redirect/jump', ['platform' => $platformClass, 'id' => $item['goodsId']]);
    }
    
    private function parseDetailPics($item){
        $detailPics = $item['detailPics'] ?? '';
        if (is_string($detailPics)) {
            $detailPics = json_decode($detailPics, true);
        }
        if (!is_array($detailPics) || empty($detailPics)) {
            return '';
        }
        $html = '';
        foreach ($detailPics as $img) {
            if (!empty($img)) {
                $html .= '<img src="' . htmlspecialchars($img) . '" alt="商品详情" style="width:100%;height:auto;margin-bottom:12px;border-radius:8px;">';
            }
        }
        return $html;
    }
	  

       public function findItems($id){
        error_reporting(0);
        if(!$id){
           exit;
        }
    	$Siteinfo = \app\common\ConfigStore::load('site');
		$newData= new \ZhiCms\ext\Weixin;
        foreach ($id as $value) {
           preg_match_all('/[1-9]\d*/', $value, $itemsId);
            $items= $itemsId['0']['0'];
        $host=$Siteinfo['apiurl']."?s=App.taobao.info";
              $arr=array ( 
              'goodsid' => $items, 
              );
	    $rootUrl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->objectArray(json_decode($newData->http($rootUrl)));
		$url=url('index/redirect/jump', array('platform'=>'tb', 'id'=>$data['data']['goodsId']));
        $html = '<section style="padding:5px;color: #333;float: left;width:100%">
<section style="box-shadow: 0px 0px 6px rgb(211,211,211);border:1px solid #e5e5e5 ;border-radius:20px ;">
<section style="padding-top: 2em;"><section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;margin:0.5em auto;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section></section>
<section style="text-align: center;margin-top: -1.5em;">
<section style="text-align: center;padding:0px 15px;font-size: 20px;font-weight: bold;display: inline-block;background: rgb(255,255,255);">
'.$data['data']['title'].'</section></section><section style="padding:2em 1em 2em 1em;">
<section style="border-radius:20px ;">
<img src="'.$data['data']['mainPic'].'" width="100%" height=auto />
</section><p>原价：'.$data['data']['originalPrice'].'元&nbsp;&nbsp;&nbsp;券后价：<font style="color:red;">'.$data['data']['actualPrice'].'</font>元</p>销量：'.$data['data']['monthSales'].'  &nbsp;&nbsp;&nbsp;优惠券金额：'.$data['data']['couponPrice'].'元<br>
   <a href="'.$url.'" target="_blank" style=" width: 120px;
    height: 36px;
    line-height: 36px;
    text-align: center;
    background: #ff2e54;
    border-radius: 2px;
    display: inline-block;
    color: #fff;
    font-size: 14px;">立即去看看</a>
</section></section></section>';
            return $html;
            
        }
    }
    
    
}