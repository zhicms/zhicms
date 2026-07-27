<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class AdController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

 public function huan(){


 $this->checkManageSession();

 	$this->pageText=array("幻灯广告","幻灯广告管理");
 	$where[] = "1";
    $baseUrl = "index.php?r=manage/ad/huan";
    $page = obj('api/ApiData')->page("50", "yun_huan", $where, "`id` DESC", $baseUrl);
    $this->page = $page;
 	$this->display();
 }

 public function link(){


 $this->checkManageSession();

 	$this->pageText=array("友情链接","友情链接管理");
 	$where[] = "1";
    $baseUrl = "index.php?r=manage/ad/link";
    $page = obj('api/ApiData')->page("50", "yun_link", $where, "`id` DESC", $baseUrl);
    $this->page = $page;
 	$this->display();
 }

public function addLink(){


$this->checkManageSession();

		if(!IS_POST){
			$this->pageText=array("友情链接","添加链接");

    		$this->display();
			exit;
		}else{
			 $data = obj('api/Api')->Form($this->POSTarg());
			 obj('api/ApiData')->insertData('yun_link', $data);
			echo json_encode(array("info" => "保存成功", "status" => "y"));

		}
	}

	public function editLink(){


	$this->checkManageSession();


		if(!IS_POST){
    		$this->pageText=array("友情链接","编辑链接");
            $id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/ApiData")->dataSelect("yun_link",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
			$this->display('app/manage/view/ad/addlink');
			exit;
		}else{

			 $id=$this->arg("id");
			 $where[]="  `id` ={$id} ";
			 $data = obj('api/Api')->Form($this->POSTarg());
             obj("api/ApiData")->dataUpdate("yun_link",$data,$where);
             echo json_encode(array("info" => "保存成功", "status" => "y"));

		}

	}

public function deleteLink(){


$this->checkManageSession();

       error_reporting(0);
        $id=$this->arg("id");
        $where = " `id` ={$id}";
        obj('api/ApiData')->deleteThis('yun_link', $where);
        exit(json_encode(array("info" => "删除成功", "status" => "y")));

	}

 public function addHuan(){


 $this->checkManageSession();

		if(!IS_POST){
			$this->pageText=array("幻灯广告","添加幻灯");

    		$this->display();
			exit;
		}else{
			 $data = obj('api/Api')->Form($this->POSTarg());
			 $data['date']=date("Y-m-d H:i:s",time());
			 obj('api/ApiData')->insertData('yun_huan', $data);
			echo json_encode(array("info" => "保存成功", "status" => "y"));

		}
	}

	public function editHuan(){



	$this->checkManageSession();


		if(!IS_POST){
    		$this->pageText=array("幻灯广告","编辑幻灯");
            $id=intval($this->arg("id"));
            $where[]="`id` = ?";
            $ret=obj("api/ApiData")->dataSelect("yun_huan",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
			$this->display('app/manage/view/ad/addhuan');
			exit;
		}else{

			 $id=intval($this->arg("id"));
			 $where[]="`id` = ?";
			 $data = obj('api/Api')->Form($this->POSTarg());

             obj("api/ApiData")->dataUpdate("yun_huan",$data,$where);
             echo json_encode(array("info" => "保存成功", "status" => "y"));

		}

	}

	public function deleteHuan(){



	$this->checkManageSession();

       error_reporting(0);
        $id=intval($this->arg("id"));
        $where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_huan', $where, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));

	}
}