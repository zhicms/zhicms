<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class FindController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

	public function index(){


	$this->checkManageSession();

		$this->categories = $this->getGoodsCategories();
		$this->pageText=array("发现管理","文章列表");
		$where[] = "1";

    	$keyword = $this->arg("keyword", '');
    	if($keyword){
    		$where[] = "`title` LIKE '%" . addslashes($keyword) . "%'";
    	}

    	$baseUrl = "index.php?r=manage/find/index";
    	if($keyword){
    		$baseUrl .= "&keyword=" . urlencode($keyword);
    	}

        $page = obj('api/ApiData')->page("50", "yun_article", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->ret = $page['list'];
        $this->keyword = $keyword;
		$this->display();
	}

	/**
	 * 一键采集（好单库朋友圈素材库）
	 * 拉取朋友圈发圈文案，生成包含产品卡片（券后价/优惠价/图片/去购买）的文章入库 yun_article
	 */
	public function collect(){
        $this->checkManageSession();
        set_time_limit(0);

        $api = \app\common\ConfigStore::load('api');
        $dtkAppKey = $api['dtk_appkey'] ?? '';
        $dtkAppSecret = $api['dtk_appsecret'] ?? '';
        $hdkApiKey = $api['hdk_appkey'] ?? '';

        if (empty($hdkApiKey)) {
            exit(json_encode(array("info" => "请先在后台配置好单库API(key)", "status" => "n")));
        }

        $tjk = new \ZhiCms\ext\Tjk([
            'DtkappKey' => $dtkAppKey,
            'DtkappSecret' => $dtkAppSecret,
            'HdkApiKey' => $hdkApiKey,
        ]);
        $hdk = $tjk->getHdk();
        if (!$hdk) {
            exit(json_encode(array("info" => "好单库API未配置", "status" => "n")));
        }
        $dtk = $tjk->getDtk();

        $cid = intval($this->arg("cid", 0));
        $pages = max(1, intval($this->arg("pages", 5)));
        $minId = intval($this->arg("min_id", 1));

        $success = 0;
        $skip = 0;
        $pageDone = 0;
        $lastMinId = $minId;

        for ($p = 0; $p < $pages; $p++) {
            $res = $hdk->FriendsCircleItems($minId);
            if ($res['code'] != 1) {
                break;
            }
            $list = $res['data'];
            if (empty($list) || !is_array($list)) {
                break;
            }

            foreach ($list as $item) {
                $itemid = $item['items']['itemid'] ?? ($item['itemid'] ?? '');
                if (empty($itemid)) {
                    continue;
                }
                $chk = obj("api/ApiData")->dataCount("yun_article", ["`goodsId` = '" . addslashes($itemid) . "'"]);
                if ($chk > 0) {
                    $skip++;
                    continue;
                }

                $title = trim($item['items']['itemshorttitle'] ?? '');
                $mainPic = $item['items']['itempic'] ?? '';
                $commentHtml = $item['comment']['copy_content'] ?? '';
                $descText = $item['items']['itemdesc'] ?? '';

                $contentText = '';
                if ($commentHtml !== '') {
                    $contentText .= '<p>' . $commentHtml . '</p>';
                }
                if ($descText !== '') {
                    $contentText .= '<p>' . $descText . '</p>';
                }
                $contentText .= $this->buildGoodsMarker($dtk, $item);

                $decText = strip_tags($commentHtml);
                $decText = mb_substr($decText, 0, 120, 'UTF-8');

                $data = [
                    'goodsId' => $itemid,
                    'itemLink' => $item['items']['couponurl'] ?? ('https://item.taobao.com/item.htm?id=' . $itemid),
                    'title' => $title,
                    'content' => $contentText,
                    'cid' => $cid,
                    'mainPic' => $mainPic,
                    'keywords' => $item['items']['itemshorttitle'] ?? '',
                    'dec' => $decText,
                    'view' => 0,
                    'like' => 0,
                    'lock' => 0,
                    'status' => 1,
                    'couponEndTime' => '',
                    'date' => date("Y-m-d H:i:s", time()),
                ];
                obj("api/ApiData")->insertData("yun_article", $data);
                $success++;
            }

            $pageDone++;

            // 获取下一页游标，如果未变化说明到最后一页了
            $nextMinId = intval($res['min_id'] ?? 0);
            if ($nextMinId > 0 && $nextMinId != $minId) {
                $lastMinId = $minId;
                $minId = $nextMinId;
            } else {
                break;
            }

            // 页间稍歇，避免频繁请求被限
            usleep(300000);
        }

        exit(json_encode(array(
            "info" => "采集完成：翻页 {$pageDone} 次，成功入库 {$success} 条，跳过重复 {$skip} 条",
            "status" => "y"
        )));
	}

	/**
	 * 生成站内商品卡片标记 [ZhiCmsUrl]短链接[/ZhiCmsUrl]
	 * 1. itemid 调用大淘客转链接口 GetPrivilegeLink 获取短链（shortUrl）
	 * 2. 前端 findItems 通过 Tjk 本地接口（Dtk::ParseContent + GetGoodsDetails）解析短链并渲染卡片
	 * 3. 卡片含：标题/券后价/原价/销量/优惠券金额/图片/「立即去看看」按钮
	 * 兜底：Dtk 未配置或短链为空时，用商品详情页链接；解析失败时渲染去购买按钮
	 */
	private function buildGoodsMarker($dtk, $item) {
        // 新API：itemid 在 items 子对象里
        $itemid = $item['items']['itemid'] ?? ($item['itemid'] ?? '');
        if (empty($itemid)) {
            return '';
        }

        $goodsUrl = '';
        if ($dtk) {
            $rate = $dtk->GetPrivilegeLink($itemid);
            if ($rate['code'] == 1 && !empty($rate['data']['shortUrl'])) {
                $goodsUrl = $rate['data']['shortUrl'];
            }
        }
        // 兜底：用优惠券链接或直接跳转
        if (empty($goodsUrl)) {
            $goodsUrl = $item['items']['couponurl'] ?? ('https://item.taobao.com/item.htm?id=' . $itemid);
        }

        return "\n[ZhiCmsUrl]" . $goodsUrl . "[/ZhiCmsUrl]\n";
	}

	public function edit(){


		$this->editArticle();
	}

	public function addArticle(){


	$this->checkManageSession();

	    
	    $Siteinfo = \app\common\ConfigStore::load('site');
	    $newData= new \ZhiCms\ext\Weixin;
    	if(!IS_POST){
			$this->pageText=array("发现管理","发布文章");
           $this->categories = $this->getGoodsCategories();
           $lock=$this->arg("lock");
           $goodsId=$this->arg("goodsid");
           if($goodsId!=''){
              $host=$Siteinfo['apiurl']."?s=App.taobao.friends";
              $arr=array ( 
              'goodsid' => $goodsId, 
              );
	    $rootUrl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->objectArray(json_decode($newData->http($rootUrl)));
    	$this->ret=$data['data']['list'][0];
    	$this->html='<input type="hidden" name="goodsid" value="'.$goodsId.'" />';
           }
    		

 
    		$this->display();
			exit;
		}else{
			// 清理可能的编辑器额外字段
			unset($_POST['editor-md-container-html-code']);
			unset($_POST['editor-md-container-article-html-code']);
 
    		 
    		$goodsId=$this->arg("goodsid");
    		$title=$this->arg("title");
    		$cid=$this->arg("cid");
    		$pic=$this->arg("pic");
    		$keywords=$this->arg("keywords");
    		$dec=$this->arg("dec");
    		$content=$this->arg("body");
    		$status=$this->arg("status", 0);
    		$author=$this->arg("author", '');
    		$laiyuan=$this->arg("laiyuan", '');
    		$surl=$this->arg("surl", '');
    		$sort=intval($this->arg("sort", 0));
    		$hits=intval($this->arg("hits", 0));
    		$bili=intval($this->arg("bili", 0));
    		$sheng=$this->arg("sheng", '');
    		
    		
    		 $startTime=strtotime(date("Y-m-d",time()));
             $endTime=$startTime+60*60*24*7;
    		 $data['goodsId']=null;
    		 $data['itemLink']=null;
    		 $data['cid']=$cid;
    		 $data['couponEndTime']=date("Y-m-d H:i:s",$endTime);
    		if($goodsId!='' && $goodsId!=null){
    		 $newData= new \ZhiCms\ext\Weixin;
              $host=$Siteinfo['apiurl']."?s=App.taobao.friends";
              $arr=array ( 
              'goodsid' => $goodsId, 
              );
	          $rootUrl = $host . '&' . http_build_query($arr);
		      $dataSer=obj("api/Api")->objectArray(json_decode($newData->http($rootUrl)));
    	      $datas=$dataSer['data']['list'][0];
    	      $data['goodsId']=$goodsId;
    		  $data['itemLink']=$datas['itemLink'];
    		  $data['cid']=$datas['cid'];
    		  $data['couponEndTime']=$datas['couponEndTime'];
    		}
    		
  
           
    	     
    		 $data['title']=$title;
    		 $data['lock']=0;
    		 $data['status']=$status ? 1 : 0;
    		 $data['author']=$author;
    		 $data['laiyuan']=$laiyuan;
    		 $data['surl']=$surl;
    		 $data['sort']=$sort;
    		 $data['hits']=$hits;
    		 $data['bili']=$bili;
    		 $data['sheng']=$sheng;
    		 $data['mainPic']=$pic;
    		 $data['keywords']=$keywords;
    		 $data['dec']=$dec;
    		 $data['content']=$content;
    		 $data['view']=0;
			 $data['like']=0;
			 $data['date']=date("Y-m-d H:i:s",time());
			 
		     obj("api/ApiData")->insertData("yun_article",$data);
			 $url="index.php?r=manage/find/index";
			 $this->redirect($url, $code = 302);
		}
		
	}

	public function editArticle(){



	$this->checkManageSession();

    $Siteinfo = \app\common\ConfigStore::load('site');
    $newData= new \ZhiCms\ext\Weixin;
	if(!IS_POST){
    		$this->pageText=array("发现管理","编辑文章");
  


            $id=intval($this->arg("id"));
            $where['id'] = $id;
            $ret=obj("api/ApiData")->dataSelect("yun_article",$where);
            $this->ret=$ret;
            $this->categories = $this->getGoodsCategories();
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" /><input type="hidden" name="goodsid" value="'.$ret['goodsId'].'" />';
			$this->display('app/manage/view/find/addarticle');
			exit;
		}else{
			// 清理可能的编辑器额外字段
			unset($_POST['editor-md-container-html-code']);
			unset($_POST['editor-md-container-article-html-code']);

			 $id=intval($this->arg("id"));
    		$title=$this->arg("title");
    		$pic=$this->arg("pic");
    		$cid=$this->arg("cid");
    		$keywords=$this->arg("keywords");
    		$dec=$this->arg("dec");
    		$content=$this->arg("body");
    		$status=$this->arg("status", 0);
    		$author=$this->arg("author", '');
    		$laiyuan=$this->arg("laiyuan", '');
    		$surl=$this->arg("surl", '');
    		$sort=intval($this->arg("sort", 0));
    		$hits=intval($this->arg("hits", 0));
    		$bili=intval($this->arg("bili", 0));
    		$sheng=$this->arg("sheng", '');
            


      		$data['title']=$title;
    		 $data['status']=$status ? 1 : 0;
    		 $data['author']=$author;
    		 $data['laiyuan']=$laiyuan;
    		 $data['surl']=$surl;
    		 $data['sort']=$sort;
    		 $data['hits']=$hits;
    		 $data['bili']=$bili;
    		 $data['sheng']=$sheng;
    		 $data['mainPic']=$pic;
    		 $data['keywords']=$keywords;
    		 $data['dec']=$dec;
    		 $data['content']=$content;

    		  $data['cid']=$cid;

             $where['id'] = $id;
             obj("api/ApiData")->dataUpdate("yun_article",$data,$where);
             $url="index.php?r=manage/find/index";
			 $this->redirect($url, $code = 302);
			
		}

	}

	public function batch(){

	$this->checkManageSession();
		error_reporting(0);
		header('Content-Type: application/json; charset=utf-8');

		$action = $this->arg("action");
		$ids    = $this->arg("ids");

		if(empty($ids)){
			exit(json_encode(array("info" => "请选择要操作的文章", "status" => "n")));
		}

		// 安全过滤，确保 ID 都是整数
		$idArr = array_map('intval', explode(',', $ids));
		$idArr = array_filter($idArr, function($v){ return $v > 0; });
		if(empty($idArr)){
			exit(json_encode(array("info" => "无效的文章ID", "status" => "n")));
		}
		$idStr = implode(',', $idArr);
		$count = count($idArr);

		// where() 方法仅接受数组，不能用字符串（会触发 TypeError）
		$where = array("`id` IN ({$idStr})");

		switch($action){
			case 'publish':
				obj('api/ApiData')->dataUpdate('yun_article', array('status' => 1), $where);
				exit(json_encode(array("info" => "已批量发布 {$count} 篇文章", "status" => "y")));

			case 'unpublish':
				obj('api/ApiData')->dataUpdate('yun_article', array('status' => 0), $where);
				exit(json_encode(array("info" => "已批量取消发布 {$count} 篇文章", "status" => "y")));

			case 'delete':
				obj('api/ApiData')->table('yun_article', true)->where($where)->delete();
				exit(json_encode(array("info" => "已批量删除 {$count} 篇文章", "status" => "y")));

			default:
				exit(json_encode(array("info" => "未知操作类型", "status" => "n")));
		}
	}

	public function delete(){




	$this->checkManageSession();

		error_reporting(0);
		$id=intval($this->arg("id"));

		$where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_article', $where, array($id));

 
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
	} 


	public function type(){
 


	$this->checkManageSession();

		$this->pageText=array("分类管理","管理发现分类");
		$where[] = "1";
    	$baseUrl = "index.php?r=manage/find/type";
        $page = obj('api/ApiData')->page("50", "yun_nav", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
		$this->display();
	}

	public function addType(){


	$this->checkManageSession();


		if(!IS_POST){
			$this->pageText=array("分类管理","新建分类");
			$this->color=self::diyColor();
			$this->display();
			exit;
		}else{
			self::checkTypeForm();
			 $data = obj('api/Api')->Form($this->POSTarg());
			 obj('api/ApiData')->insertData('yun_nav', $data);
			 echo json_encode(array("info" => "保存成功", "status" => "y"));

		}
	}

	public function editType(){




	$this->checkManageSession();

     if(!IS_POST){
			$this->pageText=array("分类管理","编辑分类");
			$id=intval($this->arg("id"));
            $where['id'] = $id;
            $ret=obj("api/ApiData")->dataSelect("yun_nav",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
            $this->color=self::diyColor();
			$this->display('app/manage/view/find/addtype');
			exit;
		}else{
			 self::checkTypeForm();
             $id=intval($this->arg("id"));
			 $where['id'] = $id;
			 $data = obj('api/Api')->Form($this->POSTarg());
             obj("api/ApiData")->dataUpdate("yun_nav",$data,$where);
             echo json_encode(array("info" => "保存成功", "status" => "y"));
		}

	}

	public function deleteType(){





	$this->checkManageSession();



		$id=intval($this->arg("id"));
		$where['navid'] = $id;
		$count = obj("api/ApiData")->dataCount("yun_article", $where);

		if($count>0){
			exit(json_encode(array("info" => "请先删除改分类下的文章", "status" => "n")));
		}

		$where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_nav', $where, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));




	}

	public function navCount(){





	$this->checkManageSession();

       
            $id=intval($this->arg("id"));
            $where['navid'] = $id;
            $count = obj("api/ApiData")->dataCount("yun_article", $where);
            echo $count;
        
    }


	public function checkTypeForm(){



	$this->checkManageSession();


		if(!$this->arg("name")){
			exit(json_encode(array("info" => "请填写名称", "status" => "n")));
		}
		if(!$this->arg("pic")){
			exit(json_encode(array("info" => "请上传图标", "status" => "n")));
		}
		if(!$this->arg("keywords")){
			exit(json_encode(array("info" => "请输入关键字", "status" => "n")));
		}
		if(!$this->arg("dec")){
			exit(json_encode(array("info" => "请输入描述", "status" => "n")));
		}

	}

	public function diyColor(){



	$this->checkManageSession();


		$array=array("#ac725e","#d06b64","#f83a22","#fa573c","#ff7537","#ffad46","#42d692","#16a765","#7bd148","#b3dc6c","#fbe983","#fad165","#92e1c0","#9fe1e7","#9fc6e7","#4986e7","#9a9cff","#c2c2c2","#cabdbf","#cca6ac","#f691b2","#cd74e6","#a47ae2","#555","#4600e4");
		return $array;
	}
}