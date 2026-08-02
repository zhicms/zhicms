<?php
namespace app\index\controller;


class MController extends \app\base\controller\BaseController {

	public function index(){
		if ($this->arg('act') === 'tab') {
			$this->tab();
			return;
		}
		$Siteinfo = \app\common\ConfigStore::load('site');
		$dev=self::getDeviceType();
		$this->dev=$dev;
	    $this->_renderMobile();
	}

	/**
	 * 移动端前端：直接输出已移植 super_search 前端的视图文件（m/index.html）
	 * 采用 readfile 原样输出，避免模板引擎解析 Vue 的 {{ }} 大括号导致语法错误
	 */
	private function _renderMobile(){
		$Siteinfo = \app\common\ConfigStore::load('site');
		$style = isset($Siteinfo['mobile_style']) ? trim($Siteinfo['mobile_style']) : 'super_search';
		$allowed = array('super_search', 'tb_minishop', 'welfare_listing', 'rt_xb');
		if (!in_array($style, $allowed, true)) { $style = 'super_search'; }
		$viewFile = \ROOT_PATH . 'app/index/view/m/' . $style . '.html';
		header('Content-Type: text/html; charset=utf-8');
		if (file_exists($viewFile)) {
			readfile($viewFile);
		} else {
			$this->display();
		}
		exit;
	}

	public function getIndexData(){
		
		$page=$this->arg("p");

		$action=$this->arg("action");

		if(!$page || $page<=0){
			$page="1";
		}

		$pageN="30";

		if($action=="search"){
		 $key=urldecode($this->arg("key"));
		 $where[]="`title` LIKE  '%{$key}%'";


		}else{
		$where[]="1";
	    }
		
		$count=obj("api/ApiData")->dataCount("yun_article",$where);

		$indexpage=round($count/$pageN);
		$pageSize=($page-1)*$pageN;
		if($action=="search"){
		 $key=urldecode($this->arg("key"));
		 $sql[]="`title` LIKE  '%{$key}%'";

		}else{
		$sql[]="1";
	    }
		$ret=obj("api/ApiData")->dataSelect("yun_article",$sql,"`id` DESC LIMIT {$pageSize} , {$pageN}");
		foreach ($ret as $key => $value) {
		$title=mb_substr(strip_tags($value['title']),0,73,'utf-8');

		$viewLink=url($route='index/redirect/go/platform/<platform>/id/<id>', $params=array('platform'=>'tb', 'id'=>$value['goodsId']));
		
		$date=obj("api/Api")->mdate($value['date']);

		$html='<div class="item">
	<div class="leftcontent">
		<a class="title" href="'.$viewLink.'" target="_blank" onmousedown="xlog(\''.$value['id'].'\',\'mindextitle\')">
			'.$value['title'].'	</a>
		<div class="clear"></div>
		<div class="waitingload" style="display:none">正在读取详情，请稍候...</div>
		<div class="fullabstract" id="fullabstract" style="display:none"></div>
		<div class="clear"></div>
		<a class="thumbnail"  isconvert="1" onmousedown="xlog(\''.$value['id'].'\',\'mpic\')">
			<img src="'.$value['mainPic'].'" alt="'.$value['title'].'" style="width:70px;height:auto;margin-left:0px;margin-top:0px;margin-bottom:0px;margin-right:0px;">
		</a>
		<div class="infoandabstract">
			<div class="abstract">'.$value['keywords'].'</div>
			<div class="clear"></div>
								<div class="mallname">'.$value['dec'].'</div>
								<div class="info"><span class="latesttime">发布于'.$date.'</span>&nbsp;&nbsp;到期时间'.$value['couponEndTime'].'</div>
			<div class="clear"></div>
		
						<div class="more"><span class="idnum" style="display:none">'.$value['id'].'</span><span class="moreword">展开全文</span></div>
					</div>
	</div>
</div>
<div class="clear"></div>
';

     echo $html;

 }
	}


  public function loadView(){
  	$id=$this->arg("id");
  	if(!is_numeric($id)){
  		exit('error');
  	}
   $where[] = "  `id` ={$id} ";
    $view = obj("api/ApiData")->dataSelect("yun_article", $where);
	$viewLink=url($route='go/to/url/id=<id>', $params=array('id'=>$view['id']));
	$date=obj("api/Api")->mdate($view['date']);
	 $newBody=preg_replace_callback('/\[ZhiCmsUrl](.+?)\[\/ZhiCmsUrl]/',[$this, 'findItems'],urldecode($view['content']));
  	$html='
<div class="mallname">'.$view['keywords'].'</div>
<div class="info" style="float:left;margin-left:3%;"><span class="latesttime">'.$date.'</span></div>
<div class="clear"></div>
<span class="remoteabstract">'.$newBody.'</span>
';

echo $html;

  }




//风云榜
public function rank(){
	header("Content-type: text/html; charset=utf-8"); 
   $Siteinfo = \app\common\ConfigStore::load('site');
		$newData= new \ZhiCms\ext\Weixin;
        $host=$Siteinfo['apiurl']."?s=App.taobao.times";
		$cacheKey = 'm_rank_times_' . md5($host);
		$data = tcache($cacheKey, function() use ($newData, $host) {
			return obj("api/Api")->objectArray(json_decode($newData->http($host)));
		}, 300);
		$time=$data['data']['time'];
	$html.='<div class="rankhead">
	<div class="rankheadtitle">天猫淘宝出单风云榜&nbsp;·&nbsp;今日'.$time.'点档）</div>
   </div>';
  $ret=$data['data']['list'];
  foreach ($ret as $key => $value) {
  $key=$key+1;
  $viewLink=url($route='index/redirect/go/platform/<platform>/id/<id>', $params=array('platform'=>'tb', 'id'=>$value['goodsId']));
  $html.='<div class="rankitem">
	<div class="rankleftcontent">
		<div class="clear"></div>
		<a class="rankthumbnail" href="'.$viewLink.'" target="_blank">
			<img src="'.$value['mainPic'].'" style="width:70px;height:auto;margin-left:0px;margin-top:0px;margin-bottom:0px;margin-right:0px;">
		</a>
		<div class="rankinfoandabstract">
			<div class="rankinfo">No.'.$key.'</div>
			<div class="clear"></div>
			<a class="ranktitle" href="'.$viewLink.'" target="_blank">
				'.$value['title'].'			</a>
			<div class="clear"></div>
			<div class="rankmore rankmoreclick" style="float:left;"><a href="'.$viewLink.'" target="_blank"><span class="moreword" >立刻查看</span></a></div>
		</div>
	</div>
</div>
<div class="clear"></div>
';

}
echo $html;

}

//九块九
public function cheaps(){
		$Siteinfo = \app\common\ConfigStore::load('site');
        $page=$this->arg("p");

		if(!$page || $page<=0){
			$page="1";
		}

		$pageN="30";

		$where[]="1";
		
		$count=obj("api/ApiData")->dataCount("yun_items",$where);

		$indexpage=round($count/$pageN);
		$pageSize=($page-1)*$pageN;

		$sql[]="1";
		$ret=obj("api/ApiData")->dataSelect("yun_items",$sql,"`id` DESC LIMIT {$pageSize} , {$pageN}");
		foreach ($ret as $key => $value) {

		$quanLink=url($route='index/redirect/go/platform/<platform>/id/<id>', $params=array('platform'=>'tb', 'id'=>$value['goodsId']));
	$html='<div class="cheapitem">
	<div class="cheapleftcontent">
		<div class="clear"></div>
		<a class="cheapthumbnail" isconvert=1 href="'.$quanLink.'" target="_blank">
			<img src="'.$value['mainPic'].'" alt="'.$value['dtitle'].'" style="width:70px;height:70px;margin-left:0px;margin-top:0px;margin-bottom:0px;margin-right:0px;">
		</a>
		<div class="cheapinfoandabstract">
			<div class="clear"></div>
			<a class="cheaptitle" isconvert=1 href="'.$quanLink.'" target="_blank">
				'.$value['title'].'			</a>
			<div class="clear"></div>
			<div class="cheapinfo"><span style="font-size:10px">券后&nbsp;&yen;</span>'.$value['actualPrice'].'</div>
			<a class="buy" isconvert=1 href="'.$quanLink.'" target="_blank"><span class="buyword">购买&gt;</span></a>
						<a class="coupon" href="'.$quanLink.'" target="_blank"><span class="couponword">领'.$value['couponPrice'].'元券</span></a>
					</div>
	</div>
</div>
<div class="clear"></div>
';

echo $html;

}
}


	// m 端频道 tab 切换：返回归一化商品 JSON，前端原地切换内容（不跳转页面）
	public function tab(){
		header('Content-Type: application/json; charset=utf-8');
		$Siteinfo = \app\common\ConfigStore::load('site');
		$type = $this->arg('type', 'index');
		$p = intval($this->arg('p', 1));
		if ($p < 1) $p = 1;
		$pageN = 20;
		$keyword = urldecode($this->arg('keyword', ''));
		$out = array('code' => 1, 'type' => $type, 'data' => array(), 'next_page' => $p + 1, 'finished' => false);

		if ($type === 'cheaps') {
			$where = $keyword ? array("`title` LIKE '%" . addslashes($keyword) . "%'") : array("`del` = 0");
			$ret = obj('api/ApiData')->dataSelect('yun_items', $where, "`id` DESC LIMIT " . (($p - 1) * $pageN) . ", {$pageN}");
			$out['data'] = $this->normGoods($ret);
		} elseif ($type === 'hot') {
			$api = obj('api/ApiData');
			$order = $keyword ? "`id` DESC" : "`monthSales` DESC";
			$where = $keyword ? array("`title` LIKE '%" . addslashes($keyword) . "%'") : array("1");
			try {
				$ret = $api->dataSelect('yun_items', $where, $order . " LIMIT " . (($p - 1) * $pageN) . ", {$pageN}");
			} catch (\Exception $e) {
				$ret = $api->dataSelect('yun_items', array("1"), "`id` DESC LIMIT " . (($p - 1) * $pageN) . ", {$pageN}");
			}
			// yun_items 无数据时回退到优惠券商品（按销量），保证热榜 tab 不为空
			if (empty($ret)) {
				$fbWhere = $keyword ? array("`title` LIKE '%" . addslashes($keyword) . "%'") : array("`del` = 0");
				$ret = $api->dataSelect('yun_items', $fbWhere, "`monthSales` DESC LIMIT " . (($p - 1) * $pageN) . ", {$pageN}");
			}
			$out['data'] = $this->normGoods($ret);
		} elseif ($type === 'rank') {
			$newData = new \ZhiCms\ext\Weixin;
			$host = $Siteinfo['apiurl'] . "?s=App.taobao.times";
			$cacheKey = 'm_tab_rank_' . md5($host);
			$data = tcache($cacheKey, function () use ($newData, $host) {
				return obj('api/Api')->objectArray(json_decode($newData->http($host)));
			}, 300);
			$list = isset($data['data']['list']) ? $data['data']['list'] : array();
			$out['data'] = $this->normRank($list, $p, $pageN);
		} elseif ($type === 'brand') {
			$items = $this->normBrand($Siteinfo, $p, $pageN);
			// 外部大牌接口无数据时回退到优惠券精选商品（按置顶/精选），保证大牌 tab 不为空
			if (empty($items)) {
				$api = obj('api/ApiData');
				$fbWhere = $keyword ? array("`title` LIKE '%" . addslashes($keyword) . "%'") : array("`del` = 0");
				$ret = $api->dataSelect('yun_items', $fbWhere, "`top` DESC, `id` DESC LIMIT " . (($p - 1) * $pageN) . ", {$pageN}");
				$items = $this->normGoods($ret);
			}
			$out['data'] = $items;
		} else {
			$where = $keyword ? array("`title` LIKE '%" . addslashes($keyword) . "%'") : array("1");
			$ret = obj('api/ApiData')->dataSelect('yun_article', $where, "`id` DESC LIMIT " . (($p - 1) * $pageN) . ", {$pageN}");
			$out['data'] = $this->normArticle($ret);
		}

		if (count($out['data']) < $pageN) {
			$out['finished'] = true;
		}
		echo json_encode($out, JSON_UNESCAPED_UNICODE);
		exit;
	}

	// 通用商品归一化（yun_items / yun_items / 大牌商品）
	private function norm($row){
		$mainPic = isset($row['mainPic']) ? $row['mainPic'] : '';
		$title   = isset($row['title']) ? $row['title'] : '';
		$dtitle  = isset($row['dtitle']) ? $row['dtitle'] : $title;
		$goodsId = isset($row['goodsId']) ? $row['goodsId'] : '';
		$actual  = isset($row['actualPrice']) ? floatval($row['actualPrice']) : 0;
		$coupon  = isset($row['couponPrice']) ? floatval($row['couponPrice']) : 0;
		$original= $actual > 0 ? ($actual + $coupon) : 0;
		return array(
			'itemid'         => $goodsId,
			'itempic'        => $mainPic,
			'itemshorttitle' => $dtitle,
			'itemtitle'      => $title,
			'itemprice'      => $original,
			'itemendprice'   => $actual,
			'itemsale'       => isset($row['monthSales']) ? intval($row['monthSales']) : 0,
			'couponmoney'    => $coupon,
			'item_from'      => '',
		);
	}
	private function normGoods($ret){ $out = array(); if ($ret) foreach ($ret as $r) $out[] = $this->norm($r); return $out; }
	private function normArticle($ret){
		$out = array();
		if ($ret) foreach ($ret as $r) {
			$out[] = array(
				'itemid' => isset($r['goodsId']) ? $r['goodsId'] : '',
				'itempic' => isset($r['mainPic']) ? $r['mainPic'] : '',
				'itemshorttitle' => isset($r['title']) ? $r['title'] : '',
				'itemtitle' => isset($r['title']) ? $r['title'] : '',
				'itemprice' => 0, 'itemendprice' => 0, 'itemsale' => 0, 'couponmoney' => 0, 'item_from' => '',
			);
		}
		return $out;
	}
	private function normRank($list, $p, $pageN){
		$slice = array_slice($list, ($p - 1) * $pageN, $pageN);
		$out = array();
		foreach ($slice as $r) {
			$out[] = array(
				'itemid' => isset($r['goodsId']) ? $r['goodsId'] : '',
				'itempic' => isset($r['mainPic']) ? $r['mainPic'] : '',
				'itemshorttitle' => isset($r['title']) ? $r['title'] : '',
				'itemtitle' => isset($r['title']) ? $r['title'] : '',
				'itemprice' => 0, 'itemendprice' => 0, 'itemsale' => 0, 'couponmoney' => 0, 'item_from' => '',
			);
		}
		return $out;
	}
	private function normBrand($Siteinfo, $p, $pageN){
		$newData = new \ZhiCms\ext\Weixin;
		$host = $Siteinfo['apiurl'] . "?s=App.taobao.brandlist";
		$bl = tcache('m_tab_brandlist_' . md5($host), function () use ($newData, $host) {
			return obj('api/Api')->objectArray(json_decode($newData->http($host . '&page=1&pagesize=10')));
		}, 600);
		$brands = isset($bl['data']['list']) ? $bl['data']['list'] : array();
		$all = array();
		$cnt = 0;
		foreach ($brands as $b) {
			$bid = isset($b['id']) ? $b['id'] : (isset($b['brandid']) ? $b['brandid'] : '');
			if (!$bid) continue;
			$vhost = $Siteinfo['apiurl'] . "?s=App.taobao.brandview&page=1&pagesize=30&brandid=" . $bid;
			$vb = tcache('m_tab_brandview_' . $bid, function () use ($newData, $vhost) {
				return obj('api/Api')->objectArray(json_decode($newData->http($vhost)));
			}, 600);
			$items = isset($vb['data']['list']) ? $vb['data']['list'] : array();
			foreach ($items as $it) { $all[] = $it; }
			$cnt++;
			if ($cnt >= 4 || count($all) >= 200) break;
		}
		$slice = array_slice($all, ($p - 1) * $pageN, $pageN);
		$out = array();
		foreach ($slice as $r) { $out[] = $this->norm($r); }
		return $out;
	}

	public function search(){

		$this->_renderMobile();
	}

public function getDeviceType()
{
 $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
 $type = 'other';
 if(strpos($agent, 'iphone') || strpos($agent, 'ipad'))
{
 $type = 'ios';
 } 
  
 if(strpos($agent, 'android'))
{
 $type = 'android';
 }
 return $type;
}

       public function findItems($id){
        error_reporting(0);
        if(!$id){
           exit;
        }
        foreach ($id as $value) {
        preg_match_all('/http[s]{0,1}:\/\/([\w.]+\/?)\S*/', $value, $itemsId);
        $itemsUrl= $itemsId['0']['0'];
        $itemsUrl=preg_replace('/\[\/ZhiCmsUrl]/','',$itemsUrl);
        $content=urldecode($itemsUrl);
        // 用本地 Tjk 接口替代已废弃的 App.Search.zfy 远程 API
        $card = $this->resolveLinkCard($content);
        if ($card !== null) return $card;
        // 解析失败时，渲染兜底「去购买」按钮
        return $this->buildFallbackBtn($content);
        }
    }

    /**
     * 使用 Tjk 本地接口解析短链接并生成商品卡片（移动端）
     * 淘宝走大淘客 ParseContent + GetGoodsDetails；
     * 拼多多/京东/唯品会目前无法通过 Tjk 解析短链，返回 null 走兜底按钮
     */
    private function resolveLinkCard($url) {
        $url = trim($url);
        if (empty($url)) return null;

        $isTaobao = (strpos($url, 'taobao.com') !== false || strpos($url, 'tmall.com') !== false);
        if (!$isTaobao) return null;

        $cacheKey = 'm_card_taobao_' . md5($url);
        return tcache($cacheKey, function() use ($url) {
            try {
                $api = \app\common\ConfigStore::load('api');
                $dtkAppKey = $api['dtk_appkey'] ?? '';
                $dtkAppSecret = $api['dtk_appsecret'] ?? '';
                if (empty($dtkAppKey) || empty($dtkAppSecret)) return null;

                $tjk = new \ZhiCms\ext\Tjk();
                $dtk = $tjk->getDtk();
                if (!$dtk) return null;

                // 1. 解析短链接获取 goodsId
                $parsed = $dtk->ParseContent($url);
                if ($parsed['code'] != 1 || empty($parsed['data']['goodsId'])) {
                    $twd = $dtk->TwdToTwd($url);
                    if ($twd['code'] == 1 && !empty($twd['data']['goodsId'])) {
                        $goodsId = $twd['data']['goodsId'];
                    } else {
                        return null;
                    }
                } else {
                    $goodsId = $parsed['data']['goodsId'];
                }

                // 2. 获取商品详情
                $detail = $dtk->GetGoodsDetails($goodsId);
                if ($detail['code'] != 1 || empty($detail['data'])) return null;

                $item = $detail['data'];
                $cardUrl = url($route='index/redirect/go/platform/<platform>/id/<id>', $params=array('platform'=>'tb', 'id'=>$goodsId));

                $title   = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
                $mainPic = htmlspecialchars($item['mainPic'] ?? '', ENT_QUOTES, 'UTF-8');
                $origPrice = floatval($item['originalPrice'] ?? 0);
                $actPrice  = floatval($item['actualPrice'] ?? 0);
                $sales     = intval($item['monthSales'] ?? 0);
                $coupon    = floatval($item['couponPrice'] ?? 0);

                $html = '<section style="padding:5px;color: #333;float: left;width:100%">
<section style="box-shadow: 0px 0px 6px rgb(211,211,211);border:1px solid #e5e5e5 ;border-radius:20px ;">
<section style="padding-top: 2em;"><section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;margin:0.5em auto;"></section>
<section style="width: 100%;height: 1px;border-bottom:1px solid #e5e5e5 ;"></section></section>
<section style="text-align: center;margin-top: -1.5em;">
<section style="text-align: center;padding:0px 15px;font-size: 20px;font-weight: bold;display: inline-block;background: rgb(255,255,255);">
'.$title.'</section></section><section style="padding:2em 1em 2em 1em;">
<section style="border-radius:20px ;">
<img src="'.$mainPic.'" width="100%" height=auto />
</section><p>原价：'.$origPrice.'元&nbsp;&nbsp;&nbsp;券后价：<font style="color:red;">'.$actPrice.'</font>元</p><p>销量：'.$sales.'  &nbsp;&nbsp;&nbsp;优惠券金额：'.$coupon.'元</p>
   <a href="'.$cardUrl.'" target="_blank" style=" width: 120px;
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
            } catch (\Exception $e) {
                return null;
            }
        }, 600);
    }

    /**
     * 兜底按钮：根据链接域名识别平台
     */
    private function buildFallbackBtn($url) {
        $url = trim($url);
        if (empty($url)) return '';
        if (strpos($url, 'pinduoduo.com') !== false || strpos($url, 'yangkeduo.com') !== false) {
            $color = '#e02e24'; $text = '去拼多多购买';
        } elseif (strpos($url, 'jd.com') !== false) {
            $color = '#e4393c'; $text = '去京东购买';
        } elseif (strpos($url, 'vip.com') !== false || strpos($url, 'vipshop.com') !== false) {
            $color = '#cd2e6b'; $text = '去唯品会购买';
        } elseif (strpos($url, 'taobao.com') !== false || strpos($url, 'tmall.com') !== false) {
            $color = '#ff2e54'; $text = '去淘宝购买';
        } else {
            $color = '#ff2e54'; $text = '去购买';
        }
        return '<div style="text-align:center;padding:16px 0;">
   <a href="' . $url . '" target="_blank" rel="nofollow" style="
       display:inline-block;padding:10px 36px;font-size:15px;color:#fff;
       background:' . $color . ';border-radius:24px;text-decoration:none;
       box-shadow:0 2px 8px rgba(0,0,0,0.15);">' . $text . '</a>
</div>';
    }



}