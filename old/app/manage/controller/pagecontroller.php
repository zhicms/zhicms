<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class pageController extends \app\base\controller\BaseController
{

	public function index(){
		$this->pagetext=array("单页管理","单页管理");
		$where[] = "1";
    	$baseurl = "index.php?r=manage/page/index";
        $Page = obj('api/ApiData')->page("50", "page", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
		$this->display();
	}

	public function addpage(){
		if(!IS_POST){
			$this->pagetext=array("单页管理","发布单页");
    		$this->display();
			exit;
		}else{
			//self::CheckarticleForm();
			 $data = obj('api/Api')->Form($this->POSTarg());
			 $data['date']=date("Y-m-d H:i:s",time());
			 $data['body']=$_POST['body'];
			 obj('api/ApiData')->Inserts('page', $data);
			//echo json_encode(array("info" => "保存成功", "status" => "y"));
			 $url="index.php?r=manage/page/index";
			 $this->redirect($url, $code = 302);


		}

	}

	public function editorpage(){

		if(!IS_POST){
    		$this->pagetext=array("单页管理","编辑单页");
    	
            $id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("page",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
			$this->display('app/manage/view/page/addpage');
			exit;
		}else{

			 $id=$this->arg("id");
			 $where[]="  `id` ={$id} ";
			 $data = obj('api/Api')->Form($this->POSTarg());
			 $data['body']=$_POST['body'];
             obj("api/Apidata")->Data_Updata("page",$data,$where);
             $url="index.php?r=manage/page/index";
			 $this->redirect($url, $code = 302);
			
		}

	}

	public function delete(){
		error_reporting('0');
		$id=$this->arg("id");

		//删除单页
        $where="  `id` ={$id}";
        obj('api/ApiData')->Deletethis('page', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
	} 


}