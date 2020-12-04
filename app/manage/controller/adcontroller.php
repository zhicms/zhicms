<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class  adController extends \app\base\controller\BaseController
{

 public function huan(){
 	$this->pagetext=array("幻灯广告","幻灯广告管理");
 	$where[] = "1";
    $baseurl = "index.php?r=manage/ad/huan";
    $Page = obj('api/ApiData')->page("50", "huan", $where, "`id` DESC", $baseurl);
    $this->Page = $Page;
 	$this->display();
 }



 public function link(){
 	$this->pagetext=array("友情链接","友情链接管理");
 	$where[] = "1";
    $baseurl = "index.php?r=manage/ad/link";
    $Page = obj('api/ApiData')->page("50", "link", $where, "`id` DESC", $baseurl);
    $this->Page = $Page;
 	$this->display();
 }


public function addlink(){
		if(!IS_POST){
			$this->pagetext=array("友情链接","添加链接");

    		$this->display();
			exit;
		}else{
			//self::CheckarticleForm();
			 $data = obj('api/Api')->Form($this->POSTarg());
			 obj('api/ApiData')->Inserts('link', $data);
			echo json_encode(array("info" => "保存成功", "status" => "y"));


		}
	}

	public function editorlink(){

		if(!IS_POST){
    		$this->pagetext=array("友情链接","编辑链接");
            $id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("link",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
			$this->display('app/manage/view/ad/addlink');
			exit;
		}else{

			 $id=$this->arg("id");
			 $where[]="  `id` ={$id} ";
			 $data = obj('api/Api')->Form($this->POSTarg());
             obj("api/Apidata")->Data_Updata("link",$data,$where);
             echo json_encode(array("info" => "保存成功", "status" => "y"));
			
		}

	}

public function deletelink(){
       error_reporting('0');
        $id=$this->arg("id");
        $where = " `id` ={$id}";
        obj('api/ApiData')->Deletethis('link', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));

	}

 public function addhuan(){
		if(!IS_POST){
			$this->pagetext=array("幻灯广告","添加幻灯");

    		$this->display();
			exit;
		}else{
			//self::CheckarticleForm();
			 $data = obj('api/Api')->Form($this->POSTarg());
			 $data['date']=date("Y-m-d H:i:s",time());
			 obj('api/ApiData')->Inserts('huan', $data);
			echo json_encode(array("info" => "保存成功", "status" => "y"));


		}
	}

	public function editorhuan(){

		if(!IS_POST){
    		$this->pagetext=array("幻灯广告","编辑幻灯");
            $id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("huan",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
			$this->display('app/manage/view/ad/addhuan');
			exit;
		}else{

			 $id=$this->arg("id");
			 $where[]="  `id` ={$id} ";
			 $data = obj('api/Api')->Form($this->POSTarg());

             obj("api/Apidata")->Data_Updata("huan",$data,$where);
             echo json_encode(array("info" => "保存成功", "status" => "y"));
			
		}

	}

	public function deletehuan(){
       error_reporting('0');
        $id=$this->arg("id");
        $where = " `id` ={$id}";
        obj('api/ApiData')->Deletethis('huan', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));

	}
}