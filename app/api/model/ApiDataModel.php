<?php
namespace app\api\model;

class ApiDataModel extends \app\base\model\BaseModel {

	/**
	 * 表名归一化：把业务层硬编码的 `yun_xxx` 映射为当前站点真实前缀的表名。
	 *
	 * 背景：历史代码在控制器中大量硬编码 `yun_items`、`yun_user` 等完整表名，
	 * 若用户安装时自定义了表前缀（如 zc_），这些查询会全部失败。
	 * 这里做一次统一转换，使自定义前缀真正可用，同时保持旧代码零改动。
	 *
	 * 规则：
	 *  1. 当前前缀就是 yun_ 时原样返回（绝大多数站点，零开销）
	 *  2. 以 yun_ 开头的核心表 -> 替换为真实前缀
	 *  3. {pre} 占位符 -> 替换为真实前缀
	 *  4. 其他（plug_ 插件表、已带真实前缀的表、含空格的多表 JOIN 片段）原样返回
	 *
	 * @param string $table 表名或多表片段
	 * @return string
	 */
	public function realTable($table){
		$prefix = isset($this->config['DB_PREFIX']) ? $this->config['DB_PREFIX'] : 'yun_';
		if (!is_string($table) || $table === '') {
			return $table;
		}
		// 统一处理 {pre} 占位符
		if (strpos($table, '{pre}') !== false) {
			$table = str_replace('{pre}', $prefix, $table);
		}
		// 默认前缀无需转换
		if ($prefix === 'yun_' || strpos($table, 'yun_') === false) {
			return $table;
		}
		// 多表片段（JOIN / 逗号分隔 / 带反引号）逐个 token 替换，避免破坏 SQL 结构
		return preg_replace('/\byun_([a-zA-Z0-9_]+)\b/', $prefix . '$1', $table);
	}

	public function dataSelect($table, $where, $order = null){
	  $table = $this->realTable($table);
	  if(!$order){
	  	 $data = $this->table($table, true)->where($where)->find();
	  }else{
	  	 $data = $this->table($table, true)->where($where)->order($order)->select();
	  }
	  return $data;
	}

	public function dataUpdate($table, $data, $condition){
		$this->table($this->realTable($table), true)->data($data)->where($condition)->update();
	}

   public function dataCount($table, $where){
   	 return $this->table($this->realTable($table), true)->where($where)->count();
   }

   public function thisQuery($sql, $params = array()){
     return $this->query($this->realTable($sql), $params);
   }

   public function executeQuery($sql, $params = array()){
      return $this->execute($this->realTable($sql), $params);
   }

  public function insertData($table, $data){
     $id = $this->table($this->realTable($table), true)->data($data)->insert();
     return $id;
   }

  /**
   * 批量插入（一条多值 INSERT，性能远优于逐条 insert）
   * @param string $table 真实表名
   * @param array $dataList 二维数组，每行字段需一致
   * @return int 插入行数
   */
  public function insertAllData($table, array $dataList){
     return $this->insertAll($this->realTable($table), $dataList, true);
  }
   
   public function deleteThis($table, $condition, $params = array()){
	   // 表名使用真实表名（核心表为 yun_xxx，插件表为 plug_xxx），与其他查询方法保持一致
	   $table = $this->realTable($table);
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
        $table = $this->realTable($table);
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
        $table = $this->realTable($table);
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
