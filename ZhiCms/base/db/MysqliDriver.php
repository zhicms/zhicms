<?php
namespace ZhiCms\base\db;
use ZhiCms\base\Hook;
class MysqliDriver implements DbInterface {
	protected $config = array();
	protected $writeLink = NULL;
	protected $readLink = NULL;
	protected $sqlMeta = array('sql'=>'', 'params'=>array(), 'link'=>NULL);

	/**
	 * 记录 SQL 错误到日志文件（与 PDO 驱动保持一致）
	 */
	private function logError($sql, $error) {
		$logFile = defined('\ROOT_PATH') ? \ROOT_PATH . 'data/log/sql_error.log' : __DIR__ . '/../../data/log/sql_error.log';
		$dir = dirname($logFile);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$time = date('Y-m-d H:i:s');
		$logEntry = "[{$time}] SQL: {$sql}\nError: {$error}\n\n";
		@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
	}
	
	public function __construct( $config = array() ){
		$this->config = $config;
	}

	public function select($table, array $condition = array(), $field='*', $order=NULL, $limit=NULL){
		$field = !empty($field) ? $field : '*';
		$order = !empty($order) ? ' ORDER BY '.$order : '';
		$limit = !empty($limit) ? ' LIMIT '.$limit : '';
		$table = $this->_table($table);
		$condition = $this->_where($condition);
		return $this->query("SELECT {$field} FROM {$table} {$condition['_where']} $order $limit", $condition['_bindParams']);		
	}
	
	public function query($sql, array $params = array()){
		$link = $this->_getReadLink();
		// 命名参数转换：MySQLi 仅支持 ? 占位符，此处将 :key 转为 ?
		$params = $this->_normalizeParams($sql, $params);
		$this->sqlMeta = array('sql'=>$sql, 'params'=>$params, 'link'=>$link);
		
		if (!($link instanceof \mysqli)) {
			throw new \Exception('Database connection failed', 500);
		}
		
		$stmt = $link->prepare($sql);
		if ($stmt === false) {
			$this->logError($sql, $link->error);
			throw new \Exception('Prepare SQL failed: ' . $link->error, 500);
		}
		
		if (!empty($params)) {
			$types = '';
			$values = array();
			foreach ($params as $param) {
				if (is_int($param)) {
					$types .= 'i';
				} elseif (is_float($param)) {
					$types .= 'd';
				} else {
					$types .= 's';
				}
				$values[] = $param;
			}
			array_unshift($values, $types);
			call_user_func_array(array($stmt, 'bind_param'), $this->_refValues($values));
		}
		
		Hook::listen('dbQueryBegin', array($sql, $params));
		
		if (!$stmt->execute()) {
			$err = $stmt->error;
			$stmt->close();
			$this->logError($this->getSql(), $err);
			Hook::listen('dbException', array($this->getSql(), $err));
			throw new \Exception('Database SQL: "' . $this->getSql(). '". ErrorInfo: '. $err, 500);
		}
		
		$result = $stmt->get_result();
		$data = array();
		while ($row = $result->fetch_assoc()) {
			$data[] = $row;
		}
		$result->free();
		$stmt->close();
		
		Hook::listen('dbQueryEnd', array($this->getSql(), $data));
		return $data;
	}
	
	public function execute($sql, array $params = array()){
		$link = $this->_getWriteLink();
		// 命名参数转换：MySQLi 仅支持 ? 占位符，此处将 :key 转为 ?
		$params = $this->_normalizeParams($sql, $params);
		$this->sqlMeta = array('sql'=>$sql, 'params'=>$params, 'link'=>$link);
		
		if (!($link instanceof \mysqli)) {
			throw new \Exception('Database connection failed', 500);
		}
		
		$stmt = $link->prepare($sql);
		if ($stmt === false) {
			$this->logError($sql, $link->error);
			throw new \Exception('Prepare SQL failed: ' . $link->error, 500);
		}
		
		if (!empty($params)) {
			$types = '';
			$values = array();
			foreach ($params as $param) {
				if (is_int($param)) {
					$types .= 'i';
				} elseif (is_float($param)) {
					$types .= 'd';
				} else {
					$types .= 's';
				}
				$values[] = $param;
			}
			array_unshift($values, $types);
			call_user_func_array(array($stmt, 'bind_param'), $this->_refValues($values));
		}
		
		Hook::listen('dbExecuteBegin', array($sql, $params));
		
		if (!$stmt->execute()) {
			$err = $stmt->error;
			$stmt->close();
			$this->logError($this->getSql(), $err);
			Hook::listen('dbException', array($this->getSql(), $err));
			throw new \Exception('Database SQL: "' . $this->getSql(). '". ErrorInfo: '. $err, 500);
		}
		
		$affectedRows = $stmt->affected_rows;
		$stmt->close();
		
		Hook::listen('dbExecuteEnd', array($this->getSql(), $affectedRows));
		return $affectedRows;
	}
	
	public function insert($table, array $data = array()){
		$table = $this->_table($table);
		$values = array();
		$types = '';
		foreach($data as $k=>$v){
			$keys[] = "`{$k}`"; 
			if (is_int($v)) {
				$types .= 'i';
			} elseif (is_float($v)) {
				$types .= 'd';
			} else {
				$types .= 's';
			}
			$values[] = $v; 
			$marks[] = '?';
		}
		
		$link = $this->_getWriteLink();
		$stmt = $link->prepare("INSERT INTO {$table} (".implode(', ', $keys).") VALUES (".implode(', ', $marks).")");
		
		if ($stmt === false) {
			throw new \Exception('Prepare SQL failed: ' . $link->error, 500);
		}
		
		array_unshift($values, $types);
		call_user_func_array(array($stmt, 'bind_param'), $this->_refValues($values));
		
		if (!$stmt->execute()) {
			$err = $stmt->error;
			$stmt->close();
			throw new \Exception('Database SQL: "' . $this->getSql(). '". ErrorInfo: '. $err, 500);
		}
		
		$id = $link->insert_id;
		$stmt->close();
		
		if($id){
			return $id;
		}else{
			return true;
		}
	}
	
	/**
	 * 批量插入（一条多值 INSERT，大幅减少网络往返与事务开销）
	 * @param string $table 表名
	 * @param array $dataList 二维数组，每行字段需一致
	 * @return int 插入行数
	 */
	public function insertAll($table, array $dataList = array()){
		if (empty($dataList)) {
			return 0;
		}
		$table = $this->_table($table);

		// 以第一行为基准取字段顺序，保证所有行字段一致
		$first = reset($dataList);
		$keys = array();
		$types = '';
		foreach ($first as $k => $v) {
			$keys[] = "`{$k}`";
			$types .= $this->_typeChar($v);
		}
		$colCount = count($keys);
		$marks = '(' . implode(', ', array_fill(0, $colCount, '?')) . ')';

		$link = $this->_getWriteLink();
		$sql = "INSERT INTO {$table} (" . implode(', ', $keys) . ") VALUES "
			. implode(', ', array_fill(0, count($dataList), $marks));
		$stmt = $link->prepare($sql);
		if ($stmt === false) {
			throw new \Exception('Prepare SQL failed: ' . $link->error, 500);
		}

		// 把所有行的参数按顺序扁平化，并拼出类型字符串
		$allTypes = str_repeat($types, count($dataList));
		$allValues = array();
		foreach ($dataList as $row) {
			// 按首行字段顺序取值，缺失补 null
			foreach ($keys as $i => $col) {
				$colName = trim($col, '`');
				$allValues[] = array_key_exists($colName, $row) ? $row[$colName] : null;
			}
		}
		array_unshift($allValues, $allTypes);
		call_user_func_array(array($stmt, 'bind_param'), $this->_refValues($allValues));

		if (!$stmt->execute()) {
			$err = $stmt->error;
			$stmt->close();
			throw new \Exception('Database SQL: "' . $sql . '". ErrorInfo: ' . $err, 500);
		}
		$affected = $stmt->affected_rows;
		$stmt->close();
		return $affected;
	}

	/**
	 * 将命名参数（:key）转换为 MySQLi 的 ? 位置占位符
	 * 使驱动兼容 PDO 风格的命名参数调用
	 */
	protected function _normalizeParams(&$sql, array $params) {
		if (empty($params)) return $params;
		
		// 检测是否包含命名参数（字符串键含 ":" 前缀）
		$hasNamed = false;
		foreach ($params as $k => $v) {
			if (is_string($k) && strpos($k, ':') !== false) {
				$hasNamed = true;
				break;
			}
		}
		if (!$hasNamed) return $params;
		
		$positional = array();
		// 按 key 长度降序，避免 :__table_id 被 :__table 误替换
		uksort($params, function($a, $b) { return strlen($b) - strlen($a); });
		
		foreach ($params as $key => $value) {
			if (is_string($key) && strpos($key, ':') !== false) {
				$count = 0;
				$sql = str_replace($key, '?', $sql, $count);
				for ($i = 0; $i < $count; $i++) {
					$positional[] = $value;
				}
			} else {
				$positional[] = $value;
			}
		}
		
		return $positional;
	}

	protected function _typeChar($v){
		if (is_int($v)) {
			return 'i';
		} elseif (is_float($v)) {
			return 'd';
		}
		return 's';
	}

	public function update($table, array $condition = array(), array $data = array()){
		if( empty($condition) ) return false;
		$values = array();
		$types = '';
		foreach ($data as $k=>$v){
			$keys[] = "`{$k}`=?";
			if (is_int($v)) {
				$types .= 'i';
			} elseif (is_float($v)) {
				$types .= 'd';
			} else {
				$types .= 's';
			}
			$values[] = $v;			
		}
		$table = $this->_table($table);
		$conditionData = $this->_where($condition);
		
		$condTypes = '';
		$condValues = array();
		foreach ($conditionData['_bindParams'] as $param) {
			if (is_int($param)) {
				$condTypes .= 'i';
			} elseif (is_float($param)) {
				$condTypes .= 'd';
			} else {
				$condTypes .= 's';
			}
			$condValues[] = $param;
		}
		
		$sql = "UPDATE {$table} SET ".implode(', ', $keys) . $conditionData['_where'];
		
		$link = $this->_getWriteLink();
		$stmt = $link->prepare($sql);
		
		if ($stmt === false) {
			throw new \Exception('Prepare SQL failed: ' . $link->error, 500);
		}
		
		$allTypes = $types . $condTypes;
		$allValues = array_merge($values, $condValues);
		array_unshift($allValues, $allTypes);
		
		call_user_func_array(array($stmt, 'bind_param'), $this->_refValues($allValues));
		
		if (!$stmt->execute()) {
			$err = $stmt->error;
			$stmt->close();
			throw new \Exception('Database SQL: "' . $this->getSql(). '". ErrorInfo: '. $err, 500);
		}
		
		$affectedRows = $stmt->affected_rows;
		$stmt->close();
		
		return $affectedRows;
	}
	
	public function delete($table, array $condition = array() ){
		if( empty($condition) ) return false;
		$table = $this->_table($table);
		$conditionData = $this->_where($condition);
		
		$types = '';
		$values = array();
		foreach ($conditionData['_bindParams'] as $param) {
			if (is_int($param)) {
				$types .= 'i';
			} elseif (is_float($param)) {
				$types .= 'd';
			} else {
				$types .= 's';
			}
			$values[] = $param;
		}
		
		$sql = "DELETE FROM {$table} {$conditionData['_where']}";
		
		$link = $this->_getWriteLink();
		$stmt = $link->prepare($sql);
		
		if ($stmt === false) {
			throw new \Exception('Prepare SQL failed: ' . $link->error, 500);
		}
		
		if (!empty($values)) {
			array_unshift($values, $types);
			call_user_func_array(array($stmt, 'bind_param'), $this->_refValues($values));
		}
		
		if (!$stmt->execute()) {
			$err = $stmt->error;
			$stmt->close();
			throw new \Exception('Database SQL: "' . $this->getSql(). '". ErrorInfo: '. $err, 500);
		}
		
		$affectedRows = $stmt->affected_rows;
		$stmt->close();
		
		return $affectedRows;
	}

	public function count($table, array $condition = array()) {
		$table = $this->_table($table);
		$conditionData = $this->_where($condition);
		
		$types = '';
		$values = array();
		foreach ($conditionData['_bindParams'] as $param) {
			if (is_int($param)) {
				$types .= 'i';
			} elseif (is_float($param)) {
				$types .= 'd';
			} else {
				$types .= 's';
			}
			$values[] = $param;
		}
		
		$sql = "SELECT COUNT(*) AS __total FROM {$table} ".$conditionData['_where'];
		
		$link = $this->_getReadLink();
		$stmt = $link->prepare($sql);
		
		if ($stmt === false) {
			throw new \Exception('Prepare SQL failed: ' . $link->error, 500);
		}
		
		if (!empty($values)) {
			array_unshift($values, $types);
			call_user_func_array(array($stmt, 'bind_param'), $this->_refValues($values));
		}
		
		if (!$stmt->execute()) {
			$err = $stmt->error;
			$stmt->close();
			throw new \Exception('Database SQL: "' . $this->getSql(). '". ErrorInfo: '. $err, 500);
		}
		
		$result = $stmt->get_result();
		$row = $result->fetch_assoc();
		$result->free();
		$stmt->close();
		
		return isset($row['__total']) && $row['__total'] ? $row['__total'] : 0;
	}
	
	public function getFields($table) {
		$table = $this->_table($table);
		return $this->query("SHOW FULL FIELDS FROM {$table}");
	}
	
	public function getSql(){
		$sql = $this->sqlMeta['sql'];
		$arr = $this->sqlMeta['params'];
		uksort($arr, function($a, $b){ return strlen($b)-strlen($a);} );
		foreach($arr as $k=>$v ){
			if ($this->sqlMeta['link'] instanceof \mysqli) {
				$sql = str_replace($k, "'" . $this->sqlMeta['link']->real_escape_string($v) . "'", $sql);
			} else {
				// 无连接时仍用 addslashes 做基本转义（仅用于日志输出）
				$sql = str_replace($k, "'" . addslashes($v) . "'", $sql);
			}
		}
		return $sql;
	}
	
	public function beginTransaction(){
		return $this->_getWriteLink()->autocommit(false);
	}
	
	public function commit(){
		$result = $this->_getWriteLink()->commit();
		$this->_getWriteLink()->autocommit(true);
		return $result;
	}
	
	public function rollBack(){
		$result = $this->_getWriteLink()->rollback();
		$this->_getWriteLink()->autocommit(true);
		return $result;
	}
	
	protected function _table($table){
		return (false===strpos($table, ' '))? "`{$table}`": $table;
	}
	
	protected function _where( array $condition ){
		$result = array( '_where' => '', '_bindParams' => array() );	 		
		$sql = null; 
		$sqlArr = array();
		$params = array();		
		foreach( $condition as $k => $v ){
			if(!is_numeric($k)){
				if( false===strpos($k, ':') ){
					$k = str_replace('`', '', $k);				
					$field = '`'.str_replace('.', '`.`', $k).'`';
					if (is_array($v)) {
						// 支持 WHERE field IN (?) 占位符批量查询
						$marks = array();
						foreach ($v as $ik => $iv) {
							$marks[] = '?';
							$params[] = $iv;
						}
						$sqlArr[] = "{$field} IN (".implode(',', $marks).")";
					} else {
						$sqlArr[] = "{$field} = ?";
						$params[] = $v;
					}
				}else{
					// 用户自定义条件，$k 中含 : 占位符，SQL 中已包含对应占位符 → 不做 SQL 拼接，仅保存参数
					$key = $k;
					$params[$key] = $v;
				}
			}else{
				$sqlArr[] = $v;
			}
		}
		if(!$sql) $sql = implode(' AND ', $sqlArr);

		if($sql) $result['_where'] = " WHERE ". $sql;
		
		$result['_bindParams'] = $params;		
		return $result;
	}
	
	protected function _refValues($arr){
		if (strnatcmp(phpversion(), '5.3') >= 0) {
			$refs = array();
			foreach($arr as $key => $value) {
				$refs[$key] = &$arr[$key];
			}
			return $refs;
		}
		return $arr;
	}
	
	protected function _connect( $isMaster = true ) {
		$dbArr = array();
		if( false==$isMaster && !empty($this->config['DB_SLAVE']) ) {	
			$master = $this->config;
			unset($master['DB_SLAVE']);
			foreach($this->config['DB_SLAVE'] as $k=>$v) {
				$dbArr[] = array_merge($master, $this->config['DB_SLAVE'][$k]);
			}
			shuffle($dbArr);
		} else {
			$dbArr[] = $this->config;
		}

		$mysqli = null;
		$error = '';
		foreach($dbArr as $db) {
			try{
				$mysqli = new \mysqli();
				$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10); // 连接超时 10 秒
				$mysqli->real_connect($db['DB_HOST'], $db['DB_USER'], $db['DB_PWD'], $db['DB_NAME'], $db['DB_PORT']);
				if ($mysqli->connect_error) {
					$error = $mysqli->connect_error;
					$mysqli = null;
					continue;
				}
				$mysqli->set_charset($db['DB_CHARSET'] ?? 'utf8mb4');
				break;
			}catch(\Exception $e){
				$error = $e->getMessage();
				$mysqli = null;
			}
		}
		
		if(!$mysqli){
			throw new \Exception('connect database error :'.$error, 500);
		}
		return $mysqli;
	}

    protected function _getReadLink() {
		if( !isset( $this->readLink ) ) {
			try{
				$this->readLink = $this->_connect( false );
			}catch( \Exception $e){
				$this->readLink = $this->_getWriteLink();
			}			
		}
		return $this->readLink;
    }
	
    protected function _getWriteLink() {
        if( !isset( $this->writeLink ) ) {
            $this->writeLink = $this->_connect( true );
        }
		return $this->writeLink;
    }
	
	public function __destruct() {
		if ($this->readLink instanceof \mysqli) {
			$this->readLink->close();
		}
		if ($this->writeLink instanceof \mysqli) {
			$this->writeLink->close();
		}
	}	
}