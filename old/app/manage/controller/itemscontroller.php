<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class  itemsController extends \app\base\controller\BaseController
{
    public function index()
    {
    	$this->pagetext=array("宝贝管理","宝贝列表");
    	$where[] = "1";
    	$baseurl = "index.php?r=manage/items/index";
        $Page = obj('api/ApiData')->page("50", "goods", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
        $this->display();
    }
   
    public function additems(){
       include CONFIG_PATH . 'siteconfig.php';
    	if(!IS_POST){
    		$this->pagetext=array("宝贝管理","新增宝贝");
    	  $goodsid = $this->arg("goodsid");
           if($goodsid!=''){
            $newdata= new \ZhiCms\ext\weixin;
              $host=$Siteinfo['apiurl']."?s=App.taobao.info";
              $arr=array ( 
              'goodsid' => $goodsid, 
              );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
    	$this->ret=$data['data'];
           }
    		$this->display();
    		exit;
    	}else{
 
    		 
    		 $url=$this->arg("link");
    		 $title=$this->arg("title");
    		 $content=$this->arg("content");
    		 $cid=$this->arg("cid");
    		 $pic=$this->arg("mainPic");
    
    		 	$itemsurl=urldecode($url);
	   preg_match('/id=[1-9]\d*/', $itemsurl, $itemiid);
	   preg_match('/[1-9]\d*/', $itemiid['0'], $urlid);
	   
	   
	   
    		  $newdata= new \ZhiCms\ext\weixin;
              $host=$Siteinfo['apiurl']."?s=App.taobao.info";
              $arr=array ( 
              'goodsid' => $urlid['0'], 
              );
	    $rooturl = $host . '&' . http_build_query($arr);
		$datas=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
    	//$dataer=$datas['data'];
    	
    		 $data['goodsId']=$urlid['0'];
    		 $data['itemLink']=$url;
    		 $data['title']=$title;
    		 $data['content']=$content;
    		 $data['cid']=$cid;
    		 $data['mainPic']=$pic;
    		 //以下字段由API提供
    		 $data['originalPrice']=$datas['data']['originalPrice'];
    		 $data['actualPrice']=$datas['data']['actualPrice'];
    		 $data['discounts']=$datas['data']['discounts'];
    		 $data['commissionRate']=$datas['data']['commissionRate'];
    		 $data['couponTotalNum']=$datas['data']['couponTotalNum'];
    		 $data['couponReceiveNum']=$datas['data']['couponReceiveNum'];
    		 $data['couponEndTime']=$datas['data']['couponEndTime'];
    		 $data['couponStartTime']=$datas['data']['couponStartTime'];
    		 $data['couponConditions']=$datas['data']['couponConditions'];
    		 $data['couponPrice']=$datas['data']['couponPrice'];
    		 $data['monthSales']=$datas['data']['monthSales'];
    		 $data['shopType']=$datas['data']['shopType'];
		     //$data['date']=date("Y-m-d H:i:s",time());
		     obj("api/Apidata")->Inserts("goods",$data);
		     exit(json_encode(array("info" => "保存成功", "status" => "y")));
    	}
    }

    public function editoritems(){
        
    	if(!IS_POST){
    	  $this->pagetext=array("宝贝管理","编辑宝贝");
    	  $id=$this->arg("id");
          $where[]="  `id` ={$id} ";
          $ret=obj("api/Apidata")->Data_Select("goods",$where);
          $this->ret=$ret;
          $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
          $this->display('app/manage/view/items/additems');
    	  exit;
    	}else{
    	     include CONFIG_PATH . 'siteconfig.php';
    	     $id=$this->arg("id");
    		 $url=$this->arg("link");
    		 $title=$this->arg("title");
    		 $content=$this->arg("content");
    		 $cid=$this->arg("cid");
    		 $pic=$this->arg("mainPic");
             $where[]="  `id` ={$id} ";
             $itemsurl=urldecode($url);
	   preg_match('/id=[1-9]\d*/', $itemsurl, $itemiid);
	   preg_match('/[1-9]\d*/', $itemiid['0'], $urlid);
             
    		  $newdata= new \ZhiCms\ext\weixin;
              $host=$Siteinfo['apiurl']."?s=App.taobao.info";
              $arr=array ( 
              'goodsid' => $urlid['0'], 
              );
	    $rooturl = $host . '&' . http_build_query($arr);
		$datas=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
             $data['goodsId']=$urlid['0'];
    		 $data['itemLink']=$url;
    		 $data['title']=$title;
    		 $data['content']=$content;
    		 $data['cid']=$cid;
    		 $data['mainPic']=$pic;
    		 //以下字段由API提供
    		 $data['originalPrice']=$datas['data']['originalPrice'];
    		 $data['actualPrice']=$datas['data']['actualPrice'];
    		 $data['discounts']=$datas['data']['discounts'];
    		 $data['commissionRate']=$datas['data']['commissionRate'];
    		 $data['couponTotalNum']=$datas['data']['couponTotalNum'];
    		 $data['couponReceiveNum']=$datas['data']['couponReceiveNum'];
    		 $data['couponEndTime']=$datas['data']['couponEndTime'];
    		 $data['couponStartTime']=$datas['data']['couponStartTime'];
    		 $data['couponConditions']=$datas['data']['couponConditions'];
    		 $data['couponPrice']=$datas['data']['couponPrice'];
    		 $data['monthSales']=$datas['data']['monthSales'];
    		 $data['shopType']=$datas['data']['shopType'];
             obj("api/Apidata")->Data_Updata("goods",$data,$where);
             exit(json_encode(array("info" => "保存成功", "status" => "y")));   

    	}
    }

    public function deleteitems(){
        error_reporting('0');
        $id=$this->arg("id");
        //删除商品
        $where_c="`id` ={$id}";
        obj('api/ApiData')->Deletethis('goods', $where_c);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));

    }
 	
    public function itemslist(){
    	$this->pagetext=array("商品管理","商品管理");
    	$id=$this->arg("id");
    	$where[]="`fid` ={$id}";
    	$ret=obj("api/Apidata")->Data_Select("items",$where,"`id` DESC ");
    	$this->ret=$ret;
    	$this->display();
    }

    public function additemslist(){

    	if(!IS_POST){

    		$this->display();
    		exit;
    	}else{
    		include CONFIG_PATH . 'siteconfig.php';
    		$data = obj('api/Api')->Form($this->POSTarg());
    		self::ChecktbkForm();
    		$data['model']=$Siteinfo['model'];
    		$data['fid']=$this->arg("fid");
    	    obj('api/ApiData')->Inserts('items', $data);
            echo json_encode(array("info" => "保存成功", "status" => "y","windows"=>"pop"));

    	}
    }

    public function deleteitemslist(){
    	error_reporting('0');
        $id=$this->arg("id");
        $where = " `id` ={$id}";
       obj('api/ApiData')->Deletethis('items', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    public function editoritemslist(){
    	if(!IS_POST){
    		$id=$this->arg("id");
            $where[]="`id` ={$id}";
    	    $ret=obj("api/Apidata")->Data_Select("items",$where);
    	    $this->ret=$ret;
    	    $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
    	    $this->display('app/manage/view/items/additemslist');
    		exit;
    	}else{
    		include CONFIG_PATH . 'siteconfig.php';
    		$id=$this->arg("id");
    		$data = obj('api/Api')->Form($this->POSTarg());
    		self::ChecktbkForm();
    		$data['model']=$Siteinfo['model'];
    		$data['fid']=$this->arg("fid");
    		$where[]="  `id` ={$id} ";
            obj("api/Apidata")->Data_Updata("items",$data,$where);
            echo json_encode(array("info" => "保存成功", "status" => "y","windows"=>"pop"));

    	}

    }
	
	 public function mall()
    {
        $this->pagetext = array("商城管理", "管理商城");
        $where[] = "1";
        $baseurl = "index.php?r=manage/items/mall";
        $Page = obj('api/ApiData')->page("50", "mall", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
        $this->display();
    }
    public function addmall()
    {
        if (!IS_POST) {
            $this->pagetext = array("商城管理", "添加商城");
            $this->union=self::loadunion();
            $this->display();
            exit;
        } else {
            self::CheckmallForm();
            $data = obj('api/Api')->Form($this->POSTarg());
            obj('api/ApiData')->Inserts('mall', $data);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }
    }
    public function editormall()
    {
        if (!IS_POST) {
            $this->pagetext = array("商城管理", "编辑商城");
            $id = $this->arg("id");
            $where[] = "`id` ={$id}";
            $ret = obj("api/Apidata")->Data_Select("mall", $where);
            $this->ret = $ret;
            $this->html = '<input type="hidden" name="id" value="' . $ret['id'] . '" />';
            $this->union=self::loadunion();
            $this->display('app/manage/view/items/addmall');
            exit;
        } else {
            self::CheckmallForm();
            $id = $this->arg("id");
            $where[] = "  `id` ={$id} ";
            $data = obj('api/Api')->Form($this->POSTarg());
            obj("api/Apidata")->Data_Updata("mall", $data, $where);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }
    }
    public function deletemall()
    {
        error_reporting('0');
        $id = $this->arg("id");
        // $where[] = "`groupid` ={$id}";
        //查询该分类下面有没有文章
        //  $count = obj("api/ApiData")->Data_Count("forum", $where);
        // if($count>0){
        //     exit(json_encode(array("info" => "请先删除改分类下的文章", "status" => "n")));
        // }
        //删除
        $where = "  `id` ={$id}";
        obj('api/ApiData')->Deletethis('mall', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }


    public function ItemsGetInfo(){
    	   header("Content-type: text/html; charset=utf-8"); 

    	   include CONFIG_PATH . 'siteconfig.php';
    	  

    	   $itemsurl=$_POST['url'];

          // $itemsurl="https://detail.tmall.com/item.htm?id=563685072391";
    	   //通用模式
    	   
			   preg_match('/[1-9]\d*/', $itemsurl,$itemsid);
			   $newdata= new \ZhiCms\ext\weixin;
              $host=$Siteinfo['apiurl']."?s=App.taobao.info";
              $arr=array ( 
        'goodsid' => $itemsid['0'], 
        );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
		$items_data['title']=$data['data']['title'];
		$items_data['content']=$data['data']['desc'];
		$items_data['mainPic']=$data['data']['mainPic'];
    	    /*$info=$tbk->getinfo($Siteinfo['appkey'],$Siteinfo['secretKey'],$itemsid['0']);
			
    	   	

    	   	$items_data['title']=$info['title'];
			$items_data['itemiid']=$info['num_iid'];
    	   	$items_data['pic']=$info['pict_url'];
    	   	$items_data['itemsurl']=$info['item_url'];
    	   	$items_data['zkPrice']=$info['zk_final_price'];
    	   	$items_data['tkCommFee']=$info['reserve_price'];
    	   	$items_data['model']=$model;*/

    	   
    	   

    	   echo json_encode($items_data);
    } 

    public function duomai(){
		include CONFIG_PATH . 'apiset.php';
        header('Content-Type:text/html; charset=utf-8');
       //1.获取xml数据 
     $xmldata=file_get_contents("https://www.duomai.com/api/ads.php?hash=".$api['hash']."&action=getApplyAds");
     $xmlstring = simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA);
     $value_array = json_decode(json_encode($xmlstring),true); 
	 $data =json_encode($value_array, JSON_UNESCAPED_UNICODE);
    $json = json_decode($data, true);//再次解析参数获得数组
     $result=array_shift($json);//减去第一组无用数组
    $ret=obj("api/Api")->object_array($json);
	 $a = obj('api/ApiData')->thisquery('BEGIN');
     for($key='0';$key<=100000;$key++){
		// $where[]="`ads_id` ='{$ret['fix_'.$key]['ads_id']}'";
	 //$chongfu=obj("api/Apidata")->Data_Select("duomai",$where);
	//if(!$chongfu){
		$shuju['ads_id']=$ret['fix_'.$key]['ads_id'];
		$shuju['ads_name']=$ret['fix_'.$key]['ads_name'];
		$shuju['channel']=$ret['fix_'.$key]['channel'];
		$shuju['status']=$ret['fix_'.$key]['status'];
		$shuju['applay_mode']=$ret['fix_'.$key]['applay_mode'];
		$shuju['hide']=$ret['fix_'.$key]['hide'];
		$shuju['type']=$ret['fix_'.$key]['type'];
		$shuju['cate_name']=$ret['fix_'.$key]['cate_name']=="" || $ret['fix_'.$key]['cate_name']==null ?"":$ret['fix_'.$key]['cate_name'];
		$shuju['ads_endtime']=$ret['fix_'.$key]['ads_endtime'];
		$shuju['ads_commission']=$ret['fix_'.$key]['ads_commission'];
		$shuju['site_url']=$ret['fix_'.$key]['site_url'];
		$shuju['site_logo']=$ret['fix_'.$key]['site_logo'];
		$shuju['site_description']=$ret['fix_'.$key]['site_description']=="" || $ret['fix_'.$key]['site_description']==null ? "":$ret['fix_'.$key]['site_description'];
		$shuju['adser']=$ret['fix_'.$key]['adser'];
		$shuju['country']=$ret['fix_'.$key]['country'];
		 obj("api/Apidata")->Inserts("duomai",$shuju);
	 //}
if($key%100==0){
    //每1w条提交一次
    $d = obj('api/ApiData')->thisquery('COMMIT');
    $e = obj('api/ApiData')->thisquery('BEGIN');
}
 echo 'ok';
    }
	$f = obj('api/ApiData')->thisquery('COMMIT');


    	exit(json_encode($ret, JSON_UNESCAPED_UNICODE));
    } 
	
	public function duomailist(){
        header('Content-Type:text/html; charset=utf-8');
       //1.获取xml数据 
     $xmldata=file_get_contents("https://www.duomai.com/api/ads.php?hash=355d9878e6469953b0383452533b6f43&action=getHighProductions");
     $xmlstring = simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA);
     $value_array = json_decode(json_encode($xmlstring),true); 
	 $data =json_encode($value_array, JSON_UNESCAPED_UNICODE);
    $json = json_decode($data, true);//再次解析参数获得数组
     //$result=array_shift($json);//减去第一组无用数组
    //$ret=obj("api/Api")->object_array($json);



    	exit(strip_tags(json_encode($json, JSON_UNESCAPED_UNICODE)));
    } 
	


    public function type(){
        $this->pagetext=array("版块管理","管理天猫淘宝宝贝版块");
        $where[] = "1";
        $baseurl = "index.php?r=manage/items/type";
        $Page = obj('api/ApiData')->page("50", "bankuai", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
        $this->display();
    }

    public function addtype(){
      if(!IS_POST){
            $this->pagetext=array("分类管理","新建分类");
            $this->display();
            exit;
        }else{
             self::CheckTypeForm();
             $data = obj('api/Api')->Form($this->POSTarg());
             obj('api/ApiData')->Inserts('group', $data);
             echo json_encode(array("info" => "保存成功", "status" => "y"));

        }  
    }
    
    public function editortype(){
     if(!IS_POST){
            $this->pagetext=array("分类管理","编辑分类");
            $id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("group",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
            $this->display('app/manage/view/items/addtype');
            exit;
        }else{
             self::CheckTypeForm();
             $id=$this->arg("id");
             $where[]="  `id` ={$id} ";
             $data = obj('api/Api')->Form($this->POSTarg());
             obj("api/Apidata")->Data_Updata("group",$data,$where);
             echo json_encode(array("info" => "保存成功", "status" => "y"));
        }

    }

    public function deletetype(){
        error_reporting('0');
        $id=$this->arg("id");
        $where[] = "`groupid` ={$id}";
        //查询该分类下面有没有文章
        $count = obj("api/ApiData")->Data_Count("forum", $where);
        if($count>0){
            exit(json_encode(array("info" => "请先删除改分类下的文章", "status" => "n")));
        }

        //删除
        $where="  `id` ={$id}";
        obj('api/ApiData')->Deletethis('group', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));



    }
	 public  function union(){
        $this->pagetext = array("联盟模型", "联盟模型");
        $where[] = "1";
        $baseurl = "index.php?r=manage/items/union";
        $Page = obj('api/ApiData')->page("50", "union", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
        $this->display();
    }

    public function addunion(){
     if (!IS_POST) {
            $this->pagetext = array("联盟模型", "新建联盟模型");
            $this->display();
            exit;
        } else {
            //self::CheckmallForm();
            $data = obj('api/Api')->Form($this->POSTarg());
            $data['code']=base64_encode($_POST['code']);
            obj('api/ApiData')->Inserts('union', $data);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }
    }

     public function editorunion()
    {
        if (!IS_POST) {
            $this->pagetext = array("联盟模型", "编辑联盟模型");
            $id = $this->arg("id");
            $where[] = "`id` ={$id}";
            $ret = obj("api/Apidata")->Data_Select("union", $where);
            $this->ret = $ret;
            $this->html = '<input type="hidden" name="id" value="' . $ret['id'] . '" />';
            $this->display('app/manage/view/items/addunion');
            exit;
        } else {
           // self::CheckmallForm();
            $id = $this->arg("id");
            $where[] = "  `id` ={$id} ";
            $data = obj('api/Api')->Form($this->POSTarg());
            $data['code']=base64_encode($_POST['code']);
            obj("api/Apidata")->Data_Updata("union", $data, $where);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }
    }

    public function deleteunion(){
        error_reporting('0');
        $id=$this->arg("id");
        $where = "  `id` ={$id}";
        obj('api/ApiData')->Deletethis('union', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }


    public function navcount($id){
       
            $where[] = "`groupid` ={$id}";
            $count = obj("api/ApiData")->Data_Count("forum", $where);
            echo $count;
        
    }


    public function CheckTypeForm(){

        if(!$this->arg("groupname")){
            exit(json_encode(array("info" => "请填写分类名称", "status" => "n")));
        }
         if(!$this->arg("px")){
            exit(json_encode(array("info" => "请填写排序", "status" => "n")));
        }

    }

 	public function CheckItemsForm($title,$type,$keywords,$dec,$pic,$uid){
 		 if(!$title){
    		 	exit(json_encode(array("info" => "请填写标题", "status" => "n")));
    		 }
    		  if($type=='0'){
    		 	exit(json_encode(array("info" => "请选择分类", "status" => "n")));
    		 }
    		 if(!$keywords){
    		 	exit(json_encode(array("info" => "请输入关键字", "status" => "n")));
    		 }
    		  if(!$dec){
    		 	exit(json_encode(array("info" => "请输入描述", "status" => "n")));
    		 }
    		 if(!$pic){
    		 	exit(json_encode(array("info" => "请上传封面", "status" => "n")));
    		 }
    		 if($uid=='0'){
    		 	exit(json_encode(array("info" => "请选择一个马甲", "status" => "n")));
    		 }
 	}  
 	
 	      //置顶处理
   public function top(){
        $id=$this->arg("id");
        
        
        //当天开始时间
        $start_time=strtotime(date("Y-m-d",time()));
        //当天结束之间
        $end_time=$start_time+60*60*24*7;
    
    
        $where[]="`id` ={$id}";
        
        $data['top']="1";
        $data['top_stime']=date("Y-m-d H:i:s",$start_time);
        $data['top_etime']=date("Y-m-d H:i:s",$end_time);
        obj("api/Apidata")->Data_Updata("goods",$data,$where);
        exit(json_encode(array("info" => "操作置顶成功", "status" => "y")));

    

   }
    	      //置顶处理
   public function editortop(){
        $id=$this->arg("id");
        $lock=$this->arg("lock");
        $where[]="`id` ={$id}";
        if($lock=="1"){ 
        $ret=obj("api/Apidata")->Data_Select("goods",$where);
        $topetime=strtotime($ret['top_etime']);
        $end_time=$topetime+60*60*24*7;
        $data['top']=1;
        $data['top_etime']=date("Y-m-d H:i:s",$end_time);
        obj("api/Apidata")->Data_Updata("goods",$data,$where);
        exit(json_encode(array("info" => "延期置顶成功", "status" => "y")));
       }elseif($lock=="2"){
        $data['top']=0;
        $data['top_stime']=0;
        $data['top_etime']=0;
        obj("api/Apidata")->Data_Updata("goods",$data,$where);
        exit(json_encode(array("info" => "取消置顶成功", "status" => "y")));

       }

   }

 	public function ChecktbkForm(){
 		if(!$this->arg("url")){
 			exit(json_encode(array("info" => "请输入宝贝网址", "status" => "n")));
 		}
 		if(!$this->arg('itemsurl')){
 			exit(json_encode(array("info" => "请输入宝贝链接", "status" => "n")));
 		}
 		if(!$this->arg('wa')){
 			exit(json_encode(array("info" => "请输入文案", "status" => "n")));
 		}
 		if(!$this->arg('taoToken')){
 			exit(json_encode(array("info" => "请输入宝贝口令", "status" => "n")));
 		}
 	}
	 //加载联盟
    public function loadunion(){

        $where[] = "1";
        $ret = obj("api/Apidata")->Data_Select("union", $where,"`id` DESC");
        return $ret;

    }
        	  public function lists($cid,$lock){
	 	if($lock=="y"){
	 		 	if($cid=="1"){
    	 	return  "女装,1";
    	 }
    	  if($cid=="2"){
    	 	return "母婴,2";
    	 }
    	  if($cid=="3"){
    	 	return "化妆品,3";
    	 }
    	  if($cid=="4"){
    	 	return "居家,4";
    	 }
    	  if($cid=="5"){
    	 	return "鞋包配饰,5";
    	 }
    	  if($cid=="6"){
    	 	return "美食,6";
    	 }
    	  if($cid=="7"){
    	 	return "文体车品,7";
    	 }
    	  if($cid=="8"){
    	 	return "数码家电,8";
    	 }
    	  if($cid=="9"){
    	 	return "男装,9";
    	 }
    	   if($cid=="10"){
    	 	return "内衣,10";
    	 }
         if($cid=="12"){
            return "配饰,12";
         }
         if($cid=="11"){
            return " 箱包,11";
         }
         if($cid=="14"){
            return '家装家纺,14';
         }
          if($cid=="13"){
            return '户外运动,13';
         }
	 	}
	  }
	  
	  	 public function overdue(){//新增更新删除数据
	   //include CONFIG_PATH . 'siteconfig.php';
	   //$newdata= new \ZhiCms\ext\weixin;
	   //$where[]="1";
       //$ret=obj("api/Apidata")->Data_Select("goods",$where);
	   //先执行删除过期，首先删除优惠券过期，不管是不是淘宝客

	   //删除当时结束优惠券
	   $today=date('Y-m-d H:i:s');
       $day=substr($today,8,2);
       $month=substr($today,5,2);
      $year=substr($today,0,4);
      $date=$year."-".$month."-".$day;//拼接日期，形成形如2020-11-20的日期数据
      $del="delete from `{pre}goods` where couponEndTime like '$date%' and timediff(couponEndTime,'$today')<0";
	   obj('api/ApiData')->thisquery($del);
       /*$end_time=$start_time+60*60*24;
       $endtime=date("Y-m-d H:i:s",$start_time);
	   $where_del="`couponEndTime` <={$endtime}";
	   obj('api/ApiData')->Deletethis('goods', $where_del);*/
	   

        exit(json_encode(array("info" => "更新成功", "status" => "y")));
    }
}