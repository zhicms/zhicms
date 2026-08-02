<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class PageController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

	public function index(){


	$this->checkManageSession();

		$this->pageText=array("单页管理","单页管理");
		$where[] = "1";
    	$baseUrl = "index.php?r=manage/page/index";
        $pageData = obj('api/ApiData')->page("50", "yun_page", $where, "`id` DESC", $baseUrl);
        // 视图 page/index.html 消费：$ret=行列表、$page=分页HTML、$page_total=总条数
        $this->ret = $pageData['list'];
        $this->page = $pageData['page'];
        $this->page_total = $pageData['count'];
		$this->display();
	}

	public function add(){


	$this->checkManageSession();

		$this->addPage();
	}

	public function addPage(){


	$this->checkManageSession();

		if(!\IS_POST){
			$this->pageText=array("单页管理","发布单页");
    		$this->display('app/manage/view/page/addpage');
			exit;
		}else{
			 $data = obj('api/Api')->Form($this->POSTarg());
			 // 清理可能的编辑器额外字段
			 unset($data['editor-md-container-html-code']);
			 unset($data['editor-md-container-article-html-code']);
			 $data['date']=date("Y-m-d H:i:s",time());
			 $data['body']=$_POST['body'];
			 obj('api/ApiData')->insertData('yun_page', $data);
			 $url="index.php?r=manage/page/index";
			 $this->redirect($url, $code = 302);

		}

	}

	public function edit(){
		$this->checkManageSession();
		$this->editPage();
	}

	public function editPage(){



	$this->checkManageSession();


		if(!\IS_POST){
    		$this->pageText=array("单页管理","编辑单页");

            $id=intval($this->arg("id"));
            $where['id'] = $id;
            $ret=obj("api/ApiData")->dataSelect("yun_page",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
			$this->display('app/manage/view/page/addpage');
			exit;
		}else{

			 $id=intval($this->arg("id"));
			 $where['id'] = $id;
			 $data = obj('api/Api')->Form($this->POSTarg());
			 // 清理可能的编辑器额外字段
			 unset($data['editor-md-container-html-code']);
			 unset($data['editor-md-container-article-html-code']);
			 $data['body']=$_POST['body'];
             obj("api/ApiData")->dataUpdate("yun_page",$data,$where);
             $url="index.php?r=manage/page/index";
			 $this->redirect($url, $code = 302);

		}

	}

	public function delete(){
		$this->pageDelete();
	}

	/**
	 * 删除别名（前端调用 manage/page/del）
	 */
	public function del(){
		$this->pageDelete();
	}

	private function pageDelete(){



	$this->checkManageSession();

		error_reporting(0);
		$id=intval($this->arg("id"));

		$where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_page', $where, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
	} 

}