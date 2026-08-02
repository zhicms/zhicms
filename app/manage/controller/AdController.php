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

 public function Link(){
    $this->checkManageSession();

 	$this->pageText=array("友情链接","友情链接管理");
 	$where[] = "1";
    $baseUrl = "index.php?r=manage/ad/link";
    $Page = obj('api/ApiData')->page("50", "yun_link", $where, "`id` DESC", $baseUrl);
    $this->Page = $Page;
 	$this->display();
 }

public function AddLink(){


$this->checkManageSession();

		if(!IS_POST){
			$this->pageText=array("友情链接","添加链接");

    		$this->display();
			exit;
		}else{
			 try {
			     $data = obj('api/Api')->Form($this->POSTarg());
			     // 默认排序
			     if(!isset($data['px']) || $data['px'] === '') $data['px'] = 0;
			     // 编辑时表单带 id 隐藏字段，走更新；否则新增（避免 1062 主键冲突）
			     if(!empty($data['id'])){
			         $id = intval($data['id']);
			         unset($data['id']);
			         $where = array('id' => $id);
			         obj('api/ApiData')->dataUpdate('yun_link', $data, $where);
			     } else {
			         obj('api/ApiData')->insertData('yun_link', $data);
			     }
			     echo json_encode(array("info" => "保存成功", "status" => "y"));
		     } catch(\Exception $e) {
			     echo json_encode(array("info" => "保存失败: " . $e->getMessage(), "status" => "n"));
		     }
		}
	}

	public function Editorlink(){


	$this->checkManageSession();


		if(!IS_POST){
    		$this->pageText=array("友情链接","编辑链接");
            $id=intval($this->arg("id"));
            $where['id'] = $id;
            $ret=obj("api/ApiData")->dataSelect("yun_link",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
			$this->display('app/manage/view/ad/addlink');
			exit;
		}else{

			 $id=intval($this->arg("id"));
			 $where['id'] = $id;
			 try {
				 $data = obj('api/Api')->Form($this->POSTarg());
				 unset($data['id']);
				 obj("api/ApiData")->dataUpdate("yun_link",$data,$where);
				 echo json_encode(array("info" => "保存成功", "status" => "y"));
			 } catch(\Exception $e) {
				 echo json_encode(array("info" => "保存失败: " . $e->getMessage(), "status" => "n"));
			 }

		}

	}

public function DeleteLink(){


$this->checkManageSession();

       error_reporting(0);
        $id=intval($this->arg("id"));
        $where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_link', $where, array($id));
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
			 $data['file'] = isset($data['file']) ? intval($data['file']) : 0;
			 $data['type'] = isset($data['type']) && $data['type'] !== '' ? intval($data['type']) : 0;
			 $data['date']=date("Y-m-d H:i:s",time());
			 obj('api/ApiData')->insertData('yun_huan', $data);
			echo json_encode(array("info" => "保存成功", "status" => "y"));

		}
	}

	public function edithuan(){



	$this->checkManageSession();


		if(!IS_POST){
    		$this->pageText=array("幻灯广告","编辑幻灯");
            $id=intval($this->arg("id"));
            if($id <= 0){
                echo "无效的幻灯片 ID";
                exit;
            }
            $where['id'] = $id;
            $ret=obj("api/ApiData")->dataSelect("yun_huan",$where);
            $this->ret=$ret;
            $this->display('app/manage/view/ad/edithuan');
			exit;
		}else{

			 $id=intval($this->arg("id"));
			 if($id <= 0){
				 echo json_encode(array("info" => "缺少有效的幻灯片 ID", "status" => "n"));
				 exit;
			 }
        $where['id'] = $id;
        try {
            $data = obj('api/Api')->Form($this->POSTarg());
            // 移除 data 中的 id，避免与 WHERE 的 id 重复（SET 与 WHERE 共用 :__id 占位符）
            unset($data['id']);
            $data['file'] = isset($data['file']) ? intval($data['file']) : 0;
            $data['type'] = isset($data['type']) && $data['type'] !== '' ? intval($data['type']) : 0;
            obj("api/ApiData")->dataUpdate("yun_huan",$data,$where);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        } catch(\Exception $e) {
            echo json_encode(array("info" => "保存失败: " . $e->getMessage(), "status" => "n"));
        }

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