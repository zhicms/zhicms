<?php
namespace app\api\model;

class ApiDataModel extends \app\base\model\BaseModel {


	public function dataSelect($table, $where, $order = null){
	  if(!$order){
	  	 $data = $this->table($table, true)->where($where)->find();
	  }else{
	  	 $data = $this->table($table, true)->where($where)->order($order)->select();
	  }
	  return $data;
	}

	public function dataUpdate($table, $data, $condition){
		$this->table($table, true)->data($data)->where($condition)->update();
	}

   public function dataCount($table, $where){
   	 return $this->table($table, true)->where($where)->count();
   }

   public function thisQuery($sql, $params = array()){
     return $this->query($sql, $params);
   }

   public function executeQuery($sql, $params = array()){
      return $this->execute($sql, $params);
   }

  public function insertData($table, $data){
     $id = $this->table($table, true)->data($data)->insert();
     return $id;
   }

  /**
   * 批量插入（一条多值 INSERT，性能远优于逐条 insert）
   * @param string $table 真实表名
   * @param array $dataList 二维数组，每行字段需一致
   * @return int 插入行数
   */
  public function insertAllData($table, array $dataList){
     return $this->insertAll($table, $dataList, true);
  }
   
   public function deleteThis($table, $condition, $params = array()){
	   // 表名使用真实表名（核心表为 yun_xxx，插件表为 plug_xxx），与其他查询方法保持一致
	   if(!empty($params)){
		   $sql = "DELETE FROM `{$table}` WHERE {$condition}";
		   $this->query($sql, $params);
	   } else {
		   // 向后兼容：仅接受纯数字ID的WHERE条件
		   // 对于仅包含 `id` = number 的简单条件，提取数字并参数化
		   if(preg_match('/^\s*`id`\s*=\s*(\d+)\s*$/i', $condition, $matches)){
			   $sql = "DELETE FROM `{$table}` WHERE `id` = ?";
			   $this->query($sql, [(int)$matches[1]]);
		   } else {
			   trigger_error('deleteThis() requires parameterized input for safety', E_USER_WARNING);
			   return false;
		   }
	   }
	}

   public function pageIndex($row, $table, $where, $order, $root){
        $page = new \ZhiCms\ext\PageIndex;
        $url = $root . "?page={page}";
        $listRows = $row;
        $curPage = $page->getCurPage($url);
        $limitStart = ($curPage - 1) * $listRows;
        $limit = $limitStart . ',' . $listRows;
        $count = $this->table($table, true)->where($where)->count();
        $list = $this->table($table, true)->where($where)->order($order)->limit($limit)->select(''); 
        $array = array('list' => $list, 'count' => $count, 'page' => $page->show($url, $count, $listRows));
        return $array;
  }

   public function page($row, $table, $where, $order, $root){
        $page = new \ZhiCms\ext\Page;
        $sep = (strpos($root, '?') !== false) ? '&' : '?';
        $url = $root . $sep . "page={page}";
        $listRows = $row;
        $curPage = $page->getCurPage($url);
        $limitStart = ($curPage - 1) * $listRows;
        $limit = $limitStart . ',' . $listRows;
        $count = $this->table($table, true)->where($where)->count();
        $list = $this->table($table, true)->where($where)->order($order)->limit($limit)->select(''); 
        $array = array('list' => $list, 'count' => $count, 'page' => $page->show($url, $count, $listRows));
        return $array;
  }   
}
