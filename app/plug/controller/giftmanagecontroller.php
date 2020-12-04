<?php
namespace app\plug\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class giftmanageController extends \app\base\controller\BaseController
{
    public function index()
    {
    	$this->pagetext=array("礼物模块","礼物模块");
      $where[]="1";

       //礼物数量
       $this->index_a= obj("api/ApiData")->Data_Count("plug_gift", $where);
       $this->index_b= obj("api/ApiData")->Data_Count("plug_gift_gonglue", $where);
       $this->index_c= obj("api/ApiData")->Data_Count("plug_question", $where);
       //加载公告http://www.zhicms.cc/api.php
        $token= new \ZhiCms\ext\weixin;
        $ret=obj("api/Api")->object_array(json_decode($token->http("http://www.zhicms.cc/api.php")));
        $this->news=$ret;
        $this->display();
    }

    public function giftlist(){
    	$this->pagetext=array("礼物模块","礼物列表");
      if($this->arg("key")){
        $where[]="`itemstitle` LIKE  '%{$this->arg('key')}%'";
        $baseurl = "index.php?r=plug/giftmanage/giftlist&key={$this->arg('key')}";
      }else{
        $where[] = "1";
        $baseurl = "index.php?r=plug/giftmanage/giftlist";
      }
    	
    	
        $Page = obj('api/ApiData')->page("50", "plug_gift", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
    	$this->display();
    }

    public function addgift(){

    	if(!IS_POST){
    		$this->pagetext=array("礼物模块","添加礼物");
    		$this->plug_gift_send=self::getlist('plug_gift_send');
    		$this->plug_gift_mudi=self::getlist('plug_gift_mudi');
    		$this->plug_gift_sendtime=self::getlist('plug_gift_sendtime');
    		$this->plug_gift_special=self::getlist('plug_gift_special');
            $this->plug_gift_type=self::getlist('plug_gift_type');
    		$this->display();
    		exit;
    	}else{
        self::checkgift();
    		$data = obj('api/Api')->Form($this->POSTarg());
    		$data['send']=json_encode($_POST['send']);
    		$data['mudi']=json_encode($_POST['mudi']);
    		$data['sendtime']=json_encode($_POST['sendtime']);
    		$data['special']=json_encode($_POST['special']);
    		obj('api/ApiData')->Inserts('plug_gift', $data);
    		echo json_encode(array("info" => "保存成功", "status" => "y"));

    	}
    }
	    public function set(){

    	if(!IS_POST){
			$this->pagetext=array("礼物版","礼物SEO设置");
			include CONFIG_PATH . 'giftseo.php';
			$this->ret=$SEO;
			$this->display();
			exit;
		}else{
      $index_title=$_POST['index_title'];
      $index_keywords=$_POST['index_keywords'];
      $index_dec=$_POST['index_dec'];
      $topic_title=$_POST['topic_title'];
      $topic_keywords=$_POST['topic_keywords'];
      $topic_dec=$_POST['topic_dec'];
      $findgift_title=$_POST['findgift_title'];
      $findgift_keywords=$_POST['findgift_keywords'];
      $findgift_dec=$_POST['findgift_dec'];
      $gl_title=$_POST['gl_title'];
      $gl_keywords=$_POST['gl_keywords'];
      $gl_dec=$_POST['gl_dec'];
      $q_title=$_POST['q_title'];
      $q_keywords=$_POST['q_keywords'];
      $q_dec=$_POST['q_dec'];
      $banquan=$_POST['banquan'];
			 $content="<?php
       \r\n\$SEO=array(
              'index_title'=>'{$index_title}',
              'index_keywords'=>'{$index_keywords}',
              'index_dec'=>'{$index_dec}',
              'topic_title'=>'{$topic_title}',
              'topic_keywords'=>'{$topic_keywords}',
              'topic_dec'=>'{$topic_dec}',
              'findgift_title'=>'{$findgift_title}',
              'findgift_keywords'=>'{$findgift_keywords}',
              'findgift_dec'=>'{$findgift_dec}',
              'gl_title'=>'{$gl_title}',
              'gl_keywords'=>'{$gl_keywords}',
              'gl_dec'=>'{$gl_dec}',
              'q_title'=>'{$q_title}',
              'q_keywords'=>'{$q_keywords}',
              'q_dec'=>'{$q_dec}',
              'banquan'=>'{$banquan}'



        );";

        $of = fopen(CONFIG_PATH . 'giftseo.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

       echo json_encode(array("info" => "设置成功", "status" => "y"));

		}
    }

    public function editorgift(){

    	if(!IS_POST){
    		$this->pagetext=array("礼物模块","编辑礼物");
    		$id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("plug_gift",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
            $this->plug_gift_send=self::getlist('plug_gift_send');
            $this->plug_gift_mudi=self::getlist('plug_gift_mudi');
            $this->plug_gift_sendtime=self::getlist('plug_gift_sendtime');
            $this->plug_gift_special=self::getlist('plug_gift_special');
            $this->plug_gift_type=self::getlist('plug_gift_type');
			$this->display('app/plug/view/giftmanage/addgift');
			exit;

    	}else{
         self::checkgift();
    		$id=$this->arg("id");
			$where[]="  `id` ={$id} ";
    		$data = obj('api/Api')->Form($this->POSTarg());
    		$data['send']=json_encode($_POST['send']);
    		$data['mudi']=json_encode($_POST['mudi']);
    		$data['sendtime']=json_encode($_POST['sendtime']);
    		$data['special']=json_encode($_POST['special']);
    		obj("api/Apidata")->Data_Updata("plug_gift",$data,$where);
    		echo json_encode(array("info" => "保存成功", "status" => "y"));

    	}
    }


    public function deletegift(){
        error_reporting('0');
    	$id=$this->arg("id");
    	$where="  `id` ={$id}";
        obj('api/ApiData')->Deletethis('plug_gift', $where);//获取需要更新的数据
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }
	
	 public function overdue(){//新增更新数据
	  include CONFIG_PATH . 'siteconfig.php';
	  $tbk= new \ZhiCms\ext\Tbk;
		$where[]="`id` !=0";
    	$shuju=obj("api/Apidata")->Data_Select("plug_gift",$where,"`id` DESC ");
		for($key='0';$key<=count($shuju);$key++){
       preg_match('/[1-9]\d*/', $shuju[$key]['link'],$itemsid);
	  $info=$tbk->getinfo($Siteinfo['appkey'],$Siteinfo['secretKey'],$itemsid['0']);
	   if(!$info){
		 $sql = "DELETE FROM `{pre}plug_gift`  WHERE  `id` =  '{$shuju[$key]['id']}'";
	   obj('api/ApiData')->thisquery($sql);     
	   }else{
		$sql = "UPDATE `{pre}plug_gift`  SET  itemiid='{$itemsid['0']}',price='{$info['zk_final_price']}' ,pic='{$info['pict_url']}'  WHERE  `id` =  '{$shuju[$key]['id']}'";
	   obj('api/ApiData')->thisquery($sql);   
	   }
		}
        exit(json_encode(array("info" => "更新成功", "status" => "y")));
    }
	
	
	
	
		
        
   function itemupdata($arr) {
	   if($arr['itemiid']==0){
		$itemsurl=urldecode($arr['link']);
	   preg_match('/id=[1-9]\d*/', $itemsurl, $id);
	   preg_match('/[1-9]\d*/', $id['0'], $urlid);
	   $ids=$arr['id'];
	   $wheres[]="  `id` ={$ids} ";
	   $datas['itemiid']=$urlid['0'];
	   obj('api/ApiData')->Data_Updata('plug_gift', $datas, $wheres);
	   $itemiid=$urlid['0'];   
	   }else{
		$itemiid=$arr['itemiid'];   
	   }
	   
	   //加载淘宝开放平台配置文件以及引入淘宝客SDK
      include CONFIG_PATH . 'siteconfig.php';
	  include TBK_PATH; 
	  $c = new TopClient;
      $c->appkey = $Siteinfo['appkey'];
      $c->secretKey = $Siteinfo['secretKey'];
      $req = new TbkItemInfoGetRequest;
      $req->setNumIids($itemiid);
      $req->setPlatform("1");
      $resp = $c->execute($req);
      return $resp;
    }

   //获取全部分类
   public function getlist($table){

   		$where[]=" 1";
		$send = obj("api/Apidata")->Data_Select($table, $where,"`id` DESC ");

		$cat=new \ZhiCms\ext\Category(array('id','pid','name','cname'));
	    $s=$cat->getTree($send);//获取分类数据树结构
	    //$s=$cat->getTree($data,1);获取pid=1所有子类数据树结构

	    return $s;
   }


   //根据id查询分类名称
   public function getlistinfo($id,$table){
   	    $where[]=" `id` ={$id}";
		$send = obj("api/Apidata")->Data_Select($table, $where);
		echo $send['name'];
   }


   public function strategy(){
      $this->pagetext=array("礼物模块","攻略列表");
       if($this->arg("key")){
        $where[]="`title` LIKE  '%{$this->arg('key')}%'";
        $baseurl = "index.php?r=plug/giftmanage/strategy&key={$this->arg('key')}";
      }else{
        $where[] = "1";
        $baseurl = "index.php?r=plug/giftmanage/strategy";
      }
      
      $Page = obj('api/ApiData')->page("50", "plug_gift_gonglue", $where, "`id` DESC", $baseurl);
      $this->Page = $Page;
      $this->display();
   }

   public function addstrategy(){
    if(!IS_POST){
        $this->pagetext=array("礼物模块","添加攻略");
        $this->plug_gift_gonglue_send=self::getlist('plug_gift_gonglue_send');
        $this->plug_gift_gonglue_whysend=self::getlist('plug_gift_gonglue_whysend');
        $this->plug_gift_gonglue_sendtime=self::getlist('plug_gift_gonglue_sendtime');
        $this->url="index.php?r=plug/giftmanage/addstrategy";
        $this->display();
        exit;
    }else{
        self::checkstrategy();
        $data = obj('api/Api')->Form($this->POSTarg());
        $data['mybody']=$_POST['mybody'];
        $data['date']=date("Y-m-d H:i:s",time());
        obj('api/ApiData')->Inserts('plug_gift_gonglue', $data);
        echo json_encode(array("info" => "保存成功", "status" => "y"));
        
    }
   }

   public function editorstrategy(){
    if(!IS_POST){
        $this->pagetext=array("礼物模块","编辑攻略");
        $this->plug_gift_gonglue_send=self::getlist('plug_gift_gonglue_send');
        $this->plug_gift_gonglue_whysend=self::getlist('plug_gift_gonglue_whysend');
        $this->plug_gift_gonglue_sendtime=self::getlist('plug_gift_gonglue_sendtime');
        $id=$this->arg("id");
        $where[]="`id` ={$id}";
        $ret=obj("api/Apidata")->Data_Select("plug_gift_gonglue",$where);
        $this->ret=$ret;
        $this->html='<input type="hidden" id="id" value="'.$ret['id'].'" />';
        $this->url="index.php?r=plug/giftmanage/editorstrategy";
       $this->display('app/plug/view/giftmanage/addstrategy');
        exit;
    }else{
        self::checkstrategy();
        $id=$this->arg("id");
        $where[]="  `id` ={$id} ";
        $data = obj('api/Api')->Form($this->POSTarg());
        $data['mybody']=$_POST['mybody'];
        $data['date']=date("Y-m-d H:i:s",time());
        obj("api/Apidata")->Data_Updata("plug_gift_gonglue",$data,$where);
        echo json_encode(array("info" => "保存成功", "status" => "y"));
        
    }

   }

   public function deltestrategy(){
    error_reporting('0');
        $id=$this->arg("id");
        $where="  `id` ={$id}";
        obj('api/ApiData')->Deletethis('plug_gift_gonglue', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));

   }

   public function question(){
     $this->pagetext=array("礼物模块","问答管理");
        if($this->arg("key")){
        $where[]="`title` LIKE  '%{$this->arg('key')}%'";
        $baseurl = "index.php?r=plug/giftmanage/question&key={$this->arg('key')}";
      }else{
        $where[] = "1";
        $baseurl = "index.php?r=plug/giftmanage/question";
      }
      $Page = obj('api/ApiData')->page("50", "plug_question", $where, "`id` DESC", $baseurl);
      $this->Page = $Page;
     $this->display();
   }

   public function addquestion(){

    if(!IS_POST){
         $this->pagetext=array("礼物模块","添加问答");
         $this->plug_gift_wendatype=self::getlist('plug_gift_wendatype');
         $this->url="index.php?r=plug/giftmanage/addquestion";
         $this->display();
         exit;
    }else{
        self::checkaddquestion();
        $data = obj('api/Api')->Form($this->POSTarg());
        $data['mybody']=$_POST['mybody'];
        $data['date']=date("Y-m-d H:i:s",time());
        $where_pid[]="`id` ={$this->arg("pid")}";
        $ret=obj("api/Apidata")->Data_Select("plug_gift_wendatype", $where_pid);
        $data['qid']=$ret['pid'];
        obj('api/ApiData')->Inserts('plug_question', $data);
        echo json_encode(array("info" => "保存成功", "status" => "y"));
    }

   }

   public function editorsquestion(){
    if(!IS_POST){
       $this->pagetext=array("礼物模块","编辑问答");
       $this->plug_gift_wendatype=self::getlist('plug_gift_wendatype');
       $id=$this->arg("id");
       $where[]="`id` ={$id}";
       $ret=obj("api/Apidata")->Data_Select("plug_question",$where);
       $this->ret=$ret;
       $this->html='<input type="hidden" id="id" value="'.$ret['id'].'" />';
       $this->url="index.php?r=plug/giftmanage/editorsquestion";
       $this->display('app/plug/view/giftmanage/addquestion');
       exit;
    }else{
        self::checkaddquestion();
        $id=$this->arg("id");
        $where[]="  `id` ={$id} ";
        $data = obj('api/Api')->Form($this->POSTarg());
        $data['mybody']=$_POST['mybody'];
        $data['date']=date("Y-m-d H:i:s",time());
        $where_pid[]="`id` ={$this->arg("pid")}";
        $ret=obj("api/Apidata")->Data_Select("plug_gift_wendatype", $where_pid);
        $data['qid']=$ret['pid'];
        obj("api/Apidata")->Data_Updata("plug_question",$data,$where);
        echo json_encode(array("info" => "保存成功", "status" => "y"));
    }

   }

    public function deltequestion(){
    error_reporting('0');
        $id=$this->arg("id");
        $where="  `id` ={$id}";
        obj('api/ApiData')->Deletethis('plug_question', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));

   }
   
      //上下架处理
   public function lock(){
        $id=$this->arg("id");
        $lock=$this->arg("lock");
        $where[]="`id` ={$id}";
        if($lock=="1"){ 
        $data['lock']="1";
        obj("api/Apidata")->Data_Updata("plug_gift",$data,$where);
        exit(json_encode(array("info" => "下架成功", "status" => "y")));
       }elseif($lock=="2"){
        $data['lock']="0";
        obj("api/Apidata")->Data_Updata("plug_gift",$data,$where);
        exit(json_encode(array("info" => "上架成功", "status" => "y")));

       }

   }
    

    public function checkgift(){

      if(!$this->arg("alink")){
        exit(json_encode(array("info" => "请填写转链", "status" => "n")));
      }
      if(!$this->arg("itemstitle")){
        exit(json_encode(array("info" => "请填写宝贝名称", "status" => "n")));
      }
       if(!$this->arg("price")){
        exit(json_encode(array("info" => "请填写宝贝价格", "status" => "n")));
      }
      if($this->arg("type")==0){
         exit(json_encode(array("info" => "请选择分类", "status" => "n")));
      }
        if(!$this->arg("pic")){
        exit(json_encode(array("info" => "请至少上传一张封面图", "status" => "n")));
      }
      if(!$_POST['send']){
        exit(json_encode(array("info" => "请选择送给谁", "status" => "n")));
      }
      if(!$_POST['mudi']){
        exit(json_encode(array("info" => "请选择送礼目的", "status" => "n")));
      }
      if(!$_POST['sendtime']){
        exit(json_encode(array("info" => "请选择送礼时间", "status" => "n")));
      }
       if(!$_POST['special']){
        exit(json_encode(array("info" => "请选择送专题", "status" => "n")));
      }
    }

    public function checkstrategy(){

       if(!$this->arg('title')){
        exit(json_encode(array("info" => "请填写攻略标题", "status" => "n")));
       }
       if(!$this->arg("pic")){
        exit(json_encode(array("info" => "请至少上传一张封面图", "status" => "n")));
      }

    }

    public function checkaddquestion(){
     if(!$this->arg('title')){
        exit(json_encode(array("info" => "请填写问答标题", "status" => "n")));
       }
        if(!$this->arg('user')){
        exit(json_encode(array("info" => "请填写用户", "status" => "n")));
       }
         if(!$this->arg("pic")){
        exit(json_encode(array("info" => "请至少上传一张封面图", "status" => "n")));
      }

    }
}