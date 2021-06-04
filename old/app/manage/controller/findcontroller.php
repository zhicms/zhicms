<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class findController extends \app\base\controller\BaseController
{

	public function index(){
		$this->pagetext=array("发现管理","文章列表");
		$where[] = "1";
    	$baseurl = "index.php?r=manage/find/index";
        $Page = obj('api/ApiData')->page("50", "article", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
		$this->display();
	}

	public function addarticle(){
	    
	    include CONFIG_PATH . 'siteconfig.php';
	    $newdata= new \ZhiCms\ext\weixin;
    	if(!IS_POST){
			$this->pagetext=array("发现管理","发布文章");
           $lock=$this->arg("lock");
           $goodsid=$this->arg("goodsid");
           if($goodsid!=''){
              $host=$Siteinfo['apiurl']."?s=App.taobao.friends";
              $arr=array ( 
              'goodsid' => $goodsid, 
              );
	    $rooturl = $host . '&' . http_build_query($arr);
		$data=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
    	$this->ret=$data['data']['list'][0];
    	$this->html='<input type="hidden" name="goodsid" value="'.$goodsid.'" />';
           }
    		

 
    		$this->display();
			exit;
		}else{
 
    		 
    		$goodsid=$this->arg("goodsid");
    		$title=$this->arg("title");
    		$cid=$this->arg("cid");
    		$pic=$this->arg("mainPic");
    		$keywords=$this->arg("keywords");
    		$dec=$this->arg("dec");
    		$content=$this->arg("body");
    		
    		
    		
    		 //当天开始时间
            $start_time=strtotime(date("Y-m-d",time()));
             //当天结束之间
            $end_time=$start_time+60*60*24*7;
    		 $data['goodsId']=null;
    		 $data['itemLink']=null;
    		 $data['cid']==$cid;
    		 $data['couponEndTime']=date("Y-m-d H:i:s",$end_time);
    		if($goodsid!='' || $goodsid!=null){
    		 $newdata= new \ZhiCms\ext\weixin;
              $host=$Siteinfo['apiurl']."?s=App.taobao.friends";
              $arr=array ( 
              'goodsid' => $goodsid, 
              );
	          $rooturl = $host . '&' . http_build_query($arr);
		      $dataser=obj("api/Api")->object_array(json_decode($newdata->http($rooturl)));
    	      $datas=$dataser['data']['list'][0];
    	      //以下字段由API提供
    	      $data['goodsId']=$goodsid;
    		  $data['itemLink']=$datas['itemLink'];
    		  $data['cid']==$datas['cid'];
    		  $data['couponEndTime']=$datas['couponEndTime'];
    		}
    		
  
           
    	     
    		 //以下字段由由用户编辑提供
    		
        
    		 $data['title']=$title;
    		 $data['lock']=0;
    		 $data['mainPic']=$pic;
    		 $data['keywords']=$keywords;
    		 $data['dec']=$dec;
    		 $data['content']=$content;
    		 $data['view']=0;
			 $data['like']=0;
			 $data['date']=date("Y-m-d H:i:s",time());
			 
		     obj("api/Apidata")->Inserts("article",$data);
		     //exit(json_encode(array("info" => "保存成功", "status" => "y")));
			 $url="index.php?r=manage/find/index";
			 $this->redirect($url, $code = 302);
		}
		
	}

	public function editorarticle(){
	    include CONFIG_PATH . 'siteconfig.php';
	    $newdata= new \ZhiCms\ext\weixin;
		if(!IS_POST){
    		$this->pagetext=array("发现管理","编辑文章");
  

            $id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("article",$where);
            if($ret['lock']==0){
             $url="index.php?r=manage/find/index";
			 $this->redirect($url, $code = 302);   
            }
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" /><input type="hidden" name="goodsid" value="'.$ret['goodsId'].'" />';
			$this->display('app/manage/view/find/addarticle');
			//$this->display('app/manage/view/find/addarticleshop');
			exit;
		}else{



			 //$data = obj('api/Api')->Form($this->POSTarg());
             $id=$this->arg("id");
    		$title=$this->arg("title");
    		$pic=$this->arg("mainPic");
    		$cid=$this->arg("cid");
    		$keywords=$this->arg("keywords");
    		$dec=$this->arg("dec");
    		$content=$this->arg("body");
            
    	      
    	      $data['title']=$title;
    		 $data['mainPic']=$pic;
    		 $data['keywords']=$keywords;
    		 $data['dec']=$dec;
    		 $data['content']=$content;

    		  $data['cid']==$cid;

             $where[]="  `id` ={$id} ";
             obj("api/Apidata")->Data_Updata("article",$data,$where);
             $url="index.php?r=manage/find/index";
			 $this->redirect($url, $code = 302);
			
		}

	}

	public function delete(){
		error_reporting('0');
		$id=$this->arg("id");

		//删除文章
        $where="  `id` ={$id}";
        obj('api/ApiData')->Deletethis('article', $where);

 
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
	} 


	public function type(){
		$this->pagetext=array("分类管理","管理发现分类");
		$where[] = "1";
    	$baseurl = "index.php?r=manage/find/type";
        $Page = obj('api/ApiData')->page("50", "nav", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
		$this->display();
	}

	public function addtype(){

		if(!IS_POST){
			$this->pagetext=array("分类管理","新建分类");
			$this->color=self::diycolor();
			$this->display();
			exit;
		}else{
			self::CheckTypeForm();
			 $data = obj('api/Api')->Form($this->POSTarg());
			 obj('api/ApiData')->Inserts('nav', $data);
			 echo json_encode(array("info" => "保存成功", "status" => "y"));

		}
	}

	public function editortype(){
     if(!IS_POST){
			$this->pagetext=array("分类管理","编辑分类");
			$id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("nav",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
            $this->color=self::diycolor();
			$this->display('app/manage/view/find/addtype');
			exit;
		}else{
			 self::CheckTypeForm();
             $id=$this->arg("id");
			 $where[]="  `id` ={$id} ";
			 $data = obj('api/Api')->Form($this->POSTarg());
             obj("api/Apidata")->Data_Updata("nav",$data,$where);
             echo json_encode(array("info" => "保存成功", "status" => "y"));
		}

	}

	public function deletetype(){

		$id=$this->arg("id");
		$where[] = "`navid` ={$id}";
		//查询该分类下面有没有文章
		$count = obj("api/ApiData")->Data_Count("article", $where);

		if($count>0){
			exit(json_encode(array("info" => "请先删除改分类下的文章", "status" => "n")));
		}

		//删除
		$where="  `id` ={$id}";
        obj('api/ApiData')->Deletethis('nav', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));



	}

	public function navcount($id){
       
            $where[] = "`navid` ={$id}";
            $count = obj("api/ApiData")->Data_Count("article", $where);
            echo $count;
        
    }


	public function CheckTypeForm(){

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

	public function CheckarticleForm(){

		if(!$this->arg("title")){
			exit(json_encode(array("info" => "请填写标题", "status" => "n")));
		}
		if($this->arg("navid")=='0'){
			exit(json_encode(array("info" => "请选择分类", "status" => "n")));
		}
		if(!$this->arg("pic")){
			exit(json_encode(array("info" => "请上传一张封面", "status" => "n")));
		}
		if(!$this->arg("keywords")){
			exit(json_encode(array("info" => "请输入关键字", "status" => "n")));
		}
		if(!$this->arg("dec")){
			exit(json_encode(array("info" => "请输入描述", "status" => "n")));
		}
		if($this->arg("uid")=='0'){
			exit(json_encode(array("info" => "请选择一个虚拟马甲", "status" => "n")));
		}
	}


	//自定义颜色
	public function diycolor(){

		$array=array("#ac725e","#d06b64","#f83a22","#fa573c","#ff7537","#ffad46","#42d692","#16a765","#7bd148","#b3dc6c","#fbe983","#fad165","#92e1c0","#9fe1e7","#9fc6e7","#4986e7","#9a9cff","#c2c2c2","#cabdbf","#cca6ac","#f691b2","#cd74e6","#a47ae2","#555","#4600e4");
		return $array;
	}
}