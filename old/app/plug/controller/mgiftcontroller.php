<?php
namespace app\plug\controller;

class mgiftController extends \app\base\controller\BaseController
{
	public function index(){
         //加载幻灯
        $where[] = "`type` =1";
        $huan= obj("api/Apidata")->Data_Select("huan", $where,"`id` DESC ");
        $this->huan=$huan;
		//获取最新攻略5条
		$where_gl[]="1";
		$glret=obj("api/Apidata")->Data_Select("plug_gift_gonglue", $where_gl,"`id` DESC LIMIT 0 , 5 ");
		$this->glret=$glret;

		$where_wd[]="1";
		$wdret=obj("api/Apidata")->Data_Select("plug_question", $where_wd,"`id` DESC LIMIT 0 , 5 ");
		$this->wdret=$wdret;

		//最新礼物

		$where_gift[]="`lock` =0";
		$giftret=obj("api/Apidata")->Data_Select("plug_gift", $where_gift,"`like` ASC LIMIT 0 , 40 ");
		$this->giftret=$giftret;
		
		$this->display();
	}

	public function find(){

		$where[]="1";
		$giftret=obj("api/Apidata")->Data_Select("plug_gift_type", $where,"`id` ASC  LIMIT 0 , 400 ");


		//获取子分类
		if($this->arg('id')){

			if(!is_numeric($this->arg("id"))){
				exit('404');
			}

			$where_pid[]="`pid` ={$this->arg('id')}";
			$pidret=obj("api/Apidata")->Data_Select("plug_gift_type", $where_pid,"`id` ASC  LIMIT 0 , 400 ");

			$where_id[]="`id` ={$this->arg('id')}";
			$idret=obj("api/Apidata")->Data_Select("plug_gift_type", $where_id);

			$this->pidret=$pidret;
			$this->idret=$idret;
		}

		$this->giftret=$giftret;
		$this->display();
	}

	public function findgift(){
		$id=$this->arg("id");
		$act=$this->arg("act");

		$where_id[]="`id` ={$this->arg('id')}";
		$idret=obj("api/Apidata")->Data_Select("plug_gift_type", $where_id);

		if(!$act){


			$this->act="all";
		}
		if($act=="down"){

			$this->act="down";
		}
		if($act=="up"){

			$this->act="up";
		}
		if($act=="o"){
			$this->act="o";
		}


		if($this->arg('t')=='json'){
        $page=$this->arg("page");
        if(!$page){
            $page="1";
        }
		if($act=="all"){

			$s="`type` ={$id} and `lock` =0";
			$orderby=" id desc";

		}
		if($act=="down"){

			$s="`type` ={$id} and `lock` =0";
			$orderby=" price ASC ";
		}
		if($act=="up"){

			$s="`type` ={$id} and `lock` =0";
			$orderby=" price DESC ";
		}
		if($act=="o"){

			$s="`type` ={$id} and `lock` =0";
			$orderby=" `like` DESC  ";	
		}

		$cs[]=$s;
        $count=obj("api/ApiData")->Data_Count("plug_gift",$cs);
        $amount="10";
        $pagenum=ceil($count/$amount); 
        $last=($page-1)*$amount;
      
        $sql = "SELECT *  FROM  `{pre}plug_gift` where {$s}   order by {$orderby} limit {$last},{$amount} ";
    
        $all = obj('api/ApiData')->thisquery($sql);

        if (empty($all)) {
                $sz=array();
              exit(json_encode($sz));
         }
           if (empty($all)) {
                exit('nodata');
         }
         foreach ($all as $key => $vo) {
           $url=url($route='plug/mgift/giftview/id=<id>', $params=array('id'=>$vo['id']));
           $price=obj('api/Api')->moneytype($vo['price']);
           $sz[]=array("id"=>$vo['id'],"itemstitle"=>$vo['itemstitle'],"pic"=>$vo['pic'],"price"=>$price,"like"=>$vo['like'],"url"=>$url);
           $b=$sz;
         }
          exit(json_encode($b));

		}

		
      
		$this->idret=$idret;
		$this->display();
	}

	public function giftview(){

		$id=$this->arg("id");
		if(!is_numeric($id)){
			exit('404');
		}

		$where[]="`id` ={$id} and `lock` =0";
	    $ret=obj("api/Apidata")->Data_Select("plug_gift", $where);
	    $this->ret=$ret;

	    //获取分类详情
        $where_pid[]="`id` ={$ret['type']}";
        $type=obj("api/Apidata")->Data_Select("plug_gift_type", $where_pid);
        $this->type=$type;

	    //猜你喜欢
        $where_like[]="`type` ={$ret['type']} and `lock` =0";
        $likeret=obj("api/Apidata")->Data_Select("plug_gift", $where_like,"`id` DESC LIMIT 0, 40 ");
        $this->likeret=$likeret;

        //判断是天猫宝贝还是淘宝宝贝
        preg_match_all('/tmall/', $ret['link'], $matches);

        if(empty($matches['0'])){
          $gobtntxt="淘宝";
        }else{
          $gobtntxt="天猫";
        }

        $this->gobtntxt=$gobtntxt;

		$this->display();
	}


	public function strategy(){
		$id=$this->arg("id");
        if($id){
            if(!is_numeric($id)){
            exit('404');
            exit;
          }
        }
        $s="1";
       	$this->act="all";
        if($this->arg("type")=="send"){

            $s="`gonglue_send` ={$id}";
            $table="plug_gift_gonglue_send";
            	$this->act="send";

        }
        if($this->arg("type")=="whysend"){

            $s="`gonglue_whysend` ={$id}";
            $table="plug_gift_gonglue_whysend";
            $this->act="whysend";

        }
         if($this->arg("type")=="sendtime"){

            $s="`gonglue_sendtime` ={$id}";
            $table="plug_gift_gonglue_sendtime";
            $this->act="sendtime";

        }
          if($this->arg("type")!='all' && $this->arg('type')!=''){
           $where_pid[]="`id` =$id";
           $type=obj("api/Apidata")->Data_Select($table, $where_pid);
           $this->ret=$type;
        }

        if($this->arg('t')=='json'){
        $page=$this->arg("page");
        if(!$page){
            $page="1";
        }
		

		$cs[]=$s;
        $count=obj("api/ApiData")->Data_Count("plug_gift_gonglue",$cs);
        $amount="10";
        $pagenum=ceil($count/$amount); 
        $last=($page-1)*$amount;
      
        $sql = "SELECT *  FROM  `{pre}plug_gift_gonglue` where {$s}   order by  id desc limit {$last},{$amount} ";
    
        $all = obj('api/ApiData')->thisquery($sql);

        if (empty($all)) {
                $sz=array();
              exit(json_encode($sz));
         }
           if (empty($all)) {
                exit('nodata');
         }
         foreach ($all as $key => $vo) {
           $url=url($route='plug/mgift/strategyview/id=<id>', $params=array('id'=>$vo['id']));
           $date=obj('api/Api')->mdate($vo['date']);
           $sz[]=array("id"=>$vo['id'],"title"=>$vo['title'],"pic"=>$vo['pic'],"like"=>$vo['like'],"date"=>$date,"url"=>$url);
           $b=$sz;
         }
          exit(json_encode($b));

		}

		$this->display();
	}

	public function strategyview(){
        $id=$this->arg("id");
        if(!is_numeric($id)){
            self::e_404();
            exit;
          }
        $where[]="`id` ={$id}";
        $ret=obj("api/Apidata")->Data_Select("plug_gift_gonglue", $where);
        $this->ret=$ret;

         $newbody=preg_replace_callback('/\[ZhiCmsID][1-9]\d*\[\/ZhiCmsID]/','self::find_q_items',$ret['mybody']);
        $this->newbody=$newbody;


        //获取喜欢的宝贝
        $where_like[]="`lock` =0";
        $likeret=obj("api/Apidata")->Data_Select("plug_gift", $where_like,"`like` DESC LIMIT 0, 5 ");
        $this->likeret=$likeret;

        //相关推荐算法
        $gonglue_send=$ret['gonglue_send']; //送给谁
        $gonglue_sendtime=$ret['gonglue_sendtime'];//送礼时间
        $gonglue_whysend=$ret['gonglue_whysend'];//为什么送

        if($gonglue_send!='0'){
            $tjsql['0']="`gonglue_send` ={$gonglue_send}";
        }
        if($gonglue_sendtime!='0'){
            $tjsql['1']="`gonglue_sendtime` ={$gonglue_sendtime}";
        }
        if($gonglue_whysend!='0'){
            $tjsql['2']="`gonglue_whysend` ={$gonglue_whysend}";
        }

        $randsql=array_rand($tjsql,1);

        $about_where[]=$$tjsql[$randsql];

        //查询相关礼物
        $aboutitemsret=obj("api/Apidata")->Data_Select("plug_gift_gonglue", $about_where,"`id` DESC LIMIT 0, 6 ");

        $this->aboutitemsret=$aboutitemsret;

		$this->display();
	}

	public function ask(){


		$this->display();
	}


	public function asklist(){
      $id=$this->arg("id");

        if($id){
           if(!is_numeric($id)){
            exit('404');
         }
     }


     if($this->arg('t')=='json'){
        $page=$this->arg("page");
        if(!$page){
            $page="1";
        }
		
        $s="`pid` ={$id}";
		$cs[]=$s;
        $count=obj("api/ApiData")->Data_Count("plug_question",$cs);
        $amount="10";
        $pagenum=ceil($count/$amount); 
        $last=($page-1)*$amount;
      
        $sql = "SELECT *  FROM  `{pre}plug_question` where {$s}   order by  id desc limit {$last},{$amount} ";
    
        $all = obj('api/ApiData')->thisquery($sql);

        if (empty($all)) {
                $sz=array();
              exit(json_encode($sz));
         }
           if (empty($all)) {
                exit('nodata');
         }
         foreach ($all as $key => $vo) {
           $url=url($route='plug/mgift/askview/id=<id>', $params=array('id'=>$vo['id']));
           $date=obj('api/Api')->mdate($vo['date']);
           $sz[]=array("id"=>$vo['id'],"title"=>$vo['title'],"like"=>$vo['like'],"url"=>$url);
           $b=$sz;
         }
          exit(json_encode($b));

		}

        $where_ask[]="`pid` ={$id}";
        $askinfo=obj("api/Apidata")->Data_Select("plug_question", $where_ask);
        $this->askinfo=$askinfo;
        $this->askcount=obj("api/ApiData")->Data_Count("plug_question",$where_ask);
		$this->display();
	}

    public function askview(){
      $id=$this->arg("id");
        if(!is_numeric($id)){
            //self::e_404();
            exit;
          }
        $where[]="`id` ={$id}";
        $ret=obj("api/Apidata")->Data_Select("plug_question", $where);
        $this->ret=$ret;


        $newbody=preg_replace_callback('/\[ZhiCmsID][1-9]\d*\[\/ZhiCmsID]/','self::find_q_items',$ret['mybody']);

       // $this->items=$items;

       $this->newbody=$newbody;


        //获取喜欢的宝贝
        $where_like[]="`lock` =0";
        $likeret=obj("api/Apidata")->Data_Select("plug_gift", $where_like,"`like` DESC LIMIT 0, 5 ");
        $this->likeret=$likeret;

        //其他问题处理
        $qt=$ret['qt'];


        $newqt=explode("\n", $qt);

        $this->newqt=$newqt;


        $this->display();
    }

    public function find_q_items($id){
        error_reporting('0');
        if(!$id){
          // self::e_404();
           exit;
        }
        $tbk= new \ZhiCms\ext\Tbk;
    	include CONFIG_PATH . 'siteconfig.php';
		
        foreach ($id as $key => $value) {
            preg_match_all('/[1-9]\d*/', $value, $itemsid);
            $items= $itemsid['0']['0'];
            $where[]="`id` ={$items}";
            $ret=obj("api/Apidata")->Data_Select("plug_gift", $where);
			if(empty($ret)){
			$info=$tbk->getinfo($Siteinfo['appkey'],$Siteinfo['secretKey'],$items);
           $url=url($route='go/tb/itemiid/id=<id>', $params=array('id'=>$info['num_iid']));
            $price=obj('api/Api')->moneytype($info['zk_final_price']);

             $html="<div class=\"pro_list clearfix\">
                                <div class=\"imgbox\">
                                    <a href=\"{$url}\">
                                        <img class=\"lazy\" src=\"{$info['pict_url']}\" alt=\"{$info['title']}\" width=\"180\" height=\"180\" style=\"display: inline;\">
                                    </a>
                                </div>
                                <div class=\"info\">
                                    <h3 class=\"title\">
                                        <a href=\"{$url}\" style=\"color:#333\">{$info['title']}</a>
                                    </h3>
                                    <div class=\"nr\">
                                      {$info['title']}
                                    </div>
                                    <div class=\"picbox\"><span class=\"pice\"><span class=\"rmb\">￥</span>{$price}</span>
                                        <a href=\"{$url}\" class=\"go\">查看详情</a>
                                    </div>
                                    <!-- /picbox -->
                                </div>
                            </div>";
            return $html;	
			}
			$url=url($route='plug/mgift/giftview/id=<id>', $params=array('id'=>$ret['id']));
            $price=obj('api/Api')->moneytype($ret['price']);
            $html="<div class=\"pro_list clearfix\">
                                <div class=\"imgbox\">
                                    <a href=\"{$url}\">
                                        <img class=\"lazy\" src=\"{$ret['pic']}\" alt=\"{$ret['itemstitle']}\" width=\"180\" height=\"180\" style=\"display: inline;\">
                                    </a>
                                </div>
                                <div class=\"info\">
                                    <h3 class=\"title\">
                                        <a href=\"{$url}\" style=\"color:#333\">{$ret['itemstitle']}</a>
                                    </h3>
                                    <div class=\"nr\">
                                      {$ret['body']}
                                    </div>
                                    <div class=\"picbox\"><span class=\"pice\"><span class=\"rmb\">￥</span>{$price}</span>
                                        <a href=\"{$url}\" class=\"go\">查看详情</a>
                                    </div>
                                    <!-- /picbox -->
                                </div>
                            </div>";
            return $html;
            
        }
    }


    public function searchindex(){


    	$this->display();
    }

    public function search(){
        $SiteConfigOs=obj('base/Base')->SiteConfig('os');
        if($SiteConfigOs=='0'){
         $keywords=urldecode($this->arg("key"));
        }else{ 
          
           $keywords=$this->arg("key");
        } 
      
      $act=$this->arg("act");

		if(!$act){


			$this->act="all";
		}
		if($act=="down"){

			$this->act="down";
		}
		if($act=="up"){

			$this->act="up";
		}
		if($act=="o"){
			$this->act="o";
		}


		if($this->arg('t')=='json'){
        $page=$this->arg("page");
        if(!$page){
            $page="1";
        }
		if($act=="all"){

			$s=" `itemstitle` LIKE  '%{$keywords}%' and `lock` =0";
			$orderby=" id desc";

		}
		if($act=="down"){

			$s="  `itemstitle` LIKE  '%{$keywords}%' and `lock` =0";
			$orderby=" price ASC ";
		}
		if($act=="up"){

			$s="  `itemstitle` LIKE  '%{$keywords}%' and `lock` =0";
			$orderby=" price DESC ";
		}
		if($act=="o"){

			$s="  `itemstitle` LIKE  '%{$keywords}%' and `lock` =0";
			$orderby=" `like` DESC  ";	
		}

		$cs[]=$s;
        $count=obj("api/ApiData")->Data_Count("plug_gift",$cs);
        $amount="10";
        $pagenum=ceil($count/$amount); 
        $last=($page-1)*$amount;
      
        $sql = "SELECT *  FROM  `{pre}plug_gift` where {$s}   order by {$orderby} limit {$last},{$amount} ";
    
        $all = obj('api/ApiData')->thisquery($sql);

        if (empty($all)) {
                $sz=array();
              exit(json_encode($sz));
         }
           if (empty($all)) {
                exit('nodata');
         }
         foreach ($all as $key => $vo) {
           $url=url($route='plug/mgift/giftview/id=<id>', $params=array('id'=>$vo['id']));
           $price=obj('api/Api')->moneytype($vo['price']);
           $sz[]=array("id"=>$vo['id'],"itemstitle"=>$vo['itemstitle'],"pic"=>$vo['pic'],"price"=>$price,"like"=>$vo['like'],"url"=>$url);
           $b=$sz;
         }
          exit(json_encode($b));

		} 
         $SiteConfigOs=obj('base/Base')->SiteConfig('os');
        if($SiteConfigOs=='0'){
          
          $keywords=urldecode($this->arg("key"));
        }else{ 
          $keywords=$this->arg("key");
        } 
		$this->urldeocde=$keywords;
    	$this->display();
    }
}