<?php
namespace ZhiCms\base\db;
use ZhiCms\base\Hook;
class MysqlPdoDriver implements DbInterface {
	protected $config =array();
	protected $writeLink = NULL;
	protected $readLink = NULL;
	protected $sqlMeta = array('sql'=>'', 'params'=>array(), 'link'=>NULL);

	/**
	 * 记录 SQL 错误到日志文件
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
		$sth = $this->_bindParams( $sql, $params, $this->_getReadLink());
		Hook::listen('dbQueryBegin', array($sql, $params));
		if( $sth->execute() ) {
			$data = $sth->fetchAll(\PDO::FETCH_ASSOC);
			Hook::listen('dbQueryEnd', array($this->getSql(), $data));
			return $data;
		}

		$err = $sth->errorInfo();
		$this->logError($this->getSql(), $err[2]); // 记录错误日志
		Hook::listen('dbException', array($this->getSql(), $err[2]));
		throw new \Exception('Database SQL: "' . $this->getSql(). '". ErrorInfo: '. $err[2], 500);
	}
	
	public function execute($sql, array $params = array()){
		$sth = $this->_bindParams( $sql, $params, $this->_getWriteLink() );
		Hook::listen('dbExecuteBegin', array($sql, $params));
		if( $sth->execute() ) {
			$affectedRows = $sth->rowCount();
			Hook::listen('dbExecuteEnd', array($this->getSql(), $affectedRows));
			return $affectedRows;
		}

		$err = $sth->errorInfo();
		$this->logError($this->getSql(), $err[2]); // 记录错误日志
		Hook::listen('dbException', array($this->getSql(), $err[2]));
		throw new \Exception('Database SQL: "' . $this->getSql(). '". ErrorInfo: '. $err[2], 500);
	}
	
	public function insert($table, array $data = array()){
		$table = $this->_table($table);
		$values = array();
		foreach($data as $k=>$v){
			$keys[] = "`{$k}`"; 
			$values[":{$k}"] = $v; 
			$marks[] = ":{$k}";
		}
		$status = $this->execute("INSERT INTO {$table} (".implode(', ', $keys).") VALUES (".implode(', ', $marks).")", $values);
		$id = $this->_getWriteLink()->lastInsertId();
		if($id){
			return $id;
		}else{
			return $status;
		}
	}
	
	public function insertAll($table, array $dataList = array()){
		if (empty($dataList)) {
			return 0;
		}
		$table = $this->_table($table);
		$first = reset($dataList);
		$keys = array();
		foreach ($first as $k => $v) {
			$keys[] = "`{$k}`";
		}
		// 所有行按首行字段顺序拼成 (?,?),(?,?)... 形式，参数扁平化为数字索引数组
		$allParams = array();
		$rows = array();
		foreach ($dataList as $row) {
			$rowMarks = array();
			foreach ($keys as $col) {
				$colName = trim($col, '`');
				$allParams[] = array_key_exists($colName, $row) ? $row[$colName] : null;
				$rowMarks[] = '?';
			}
			$rows[] = '(' . implode(', ', $rowMarks) . ')';
		}
		$sql = "INSERT INTO {$table} (" . implode(', ', $keys) . ") VALUES " . implode(', ', $rows);
		$sth = $this->_bindParams($sql, $allParams, $this->_getWriteLink());
		Hook::listen('dbExecuteBegin', array($sql, $allParams));
		if ($sth->execute()) {
			$affected = $sth->rowCount();
			Hook::listen('dbExecuteEnd', array($sql, $affected));
			return $affected;
		}
		$err = $sth->errorInfo();
		$this->logError($sql, $err[2]);
		Hook::listen('dbException', array($sql, $err[2]));
		throw new \Exception('Database SQL: "' . $sql . '". ErrorInfo: ' . $err[2], 500);
	}

	public function update($table, array $condition = array(), array $data = array()){
		if( empty($condition) ) return false;
		$values = array();
		foreach ($data as $k=>$v){
			$keys[] = "`{$k}`=:__{$k}";
			$values[":__{$k}"] = $v;			
		}
		$table = $this->_table($table);
		$condition = $this->_where( $condition );
		// 重命名 WHERE 参数键，避免与 SET 字段名冲突（如 data 与 where 同时含 id 时，
		// 会出现两个 :__id 占位符却只绑定一个参数，触发 SQLSTATE[HY093]）
		$whereParams = array();
		$whereSql = $condition['_where'];
		foreach ($condition['_bindParams'] as $wk => $wv) {
			$newKey = ':__w_' . ltrim($wk, ':');
			$whereSql = str_replace($wk, $newKey, $whereSql);
			$whereParams[$newKey] = $wv;
		}
		return $this->execute("UPDATE {$table} SET ".implode(', ', $keys) . $whereSql, $whereParams + $values);
	}
	
	public function delete($table, array $condition = array() ){
		if( empty($condition) ) return false;
		$table = $this->_table($table);
		$condition = $this->_where( $condition );
		return $this->execute("DELETE FROM {$table} {$condition['_where']}", $condition['_bindParams']);
	}

	public function count($table, array $condition = array()) {
		$table = $this->_table($table);
		$condition = $this->_where( $condition );
		$count = $this->query("SELECT COUNT(*) AS __total FROM {$table} ".$condition['_where'], $condition['_bindParams']);
		return isset($count[0]['__total']) && $count[0]['__total'] ? $count[0]['__total'] : 0;
	}
	
	public function getFields($table) {
		$table = $this->_table($table);
		return  $this->query("SHOW FULL FIELDS FROM {$table}");
	}
	
	public function getSql(){
		$sql = $this->sqlMeta['sql'];
		$arr = $this->sqlMeta['params'];
		uksort($arr, function($a, $b){ return strlen($b)-strlen($a);} );
		foreach($arr as $k=>$v ){
			$sql = str_replace($k, $this->sqlMeta['link']->quote($v), $sql);
		}
		return $sql;
	}
	
	public function beginTransaction(){
		return $this->_getWriteLink()->beginTransaction();
	}
	
	public function commit(){
		return $this->_getWriteLink()->commit();
	}
	
	public function rollBack(){
		return $this->_getWriteLink()->rollBack();
	}
	
	protected function _bindParams($sql, array $params, $link=null){
		$this->sqlMeta = array('sql'=>$sql, 'params'=>$params, 'link'=>$link);
		$sth = $link->prepare($sql);		
		$index = 1;
		foreach($params as $k=>$v){
			if (is_numeric($k)) {
				$sth->bindValue($index++, $v);
			} else {
				$sth->bindValue($k, $v);
			}
		}				
		return $sth;
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
						// 支持 WHERE field IN (?) 占位符批量查询，修复循环内 N+1 查库
						$inKeys = array();
						foreach ($v as $ik => $iv) {
							$ikey = ':__'.str_replace('.', '_', $k).'_'.$ik;
							$inKeys[] = $ikey;
							$params[$ikey] = $iv;
						}
						$sqlArr[] = "{$field} IN (".implode(',', $inKeys).")";
					} else {
						$key = ':__'.str_replace('.', '_', $k);
						$sqlArr[] = "{$field} = {$key}";
						$params[$key] = $v;
					}
				}else{
					$key = $k;
					$params[$key] = $v;
				}
			}else{
				// 数字索引分支：裸 SQL 片段拼接（框架历史用法，如 "`del` = 0"）。
				// 为防御调用方未过滤就拼入用户输入，对字符串值再做一次 addslashes 兜底，
				// 阻断引号闭合注入；静态片段（无引号）经 addslashes 后不变，无副作用。
				if (is_string($v)) {
					$v = addslashes($v);
				}
				$sqlArr[] = $v;
			}
		}
		if(!$sql) $sql = implode(' AND ', $sqlArr);

		if($sql) $result['_where'] = " WHERE ". $sql;
		
		$result['_bindParams'] = $params;		
		return $result;
	}
	
	protected  function _connect( $isMaster = true ) {
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

		$pdo = null;
		$error = '';
		foreach($dbArr as $db) {
			$dsn = "mysql:host={$db['DB_HOST']};port={$db['DB_PORT']};dbname={$db['DB_NAME']};charset={$db['DB_CHARSET']}";
			try{
				$pdo = new \PDO($dsn, $db['DB_USER'], $db['DB_PWD'], array(
					\PDO::ATTR_PERSISTENT => true,
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_TIMEOUT => 30, // 连接超时 30 秒
					\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$db['DB_CHARSET']}",
					\PDO::ATTR_EMULATE_PREPARES => false // 使用原生预处理（生产环境推荐）
				));
				break;
			}catch(\PDOException $e){
				$error = $e->getMessage();
			}
		}
		
		if(!$pdo){
			throw new \Exception('connect database error :'.$error, 500);
		}
		return $pdo;
	}

    protected function _getReadLink() {
		if( !isset( $this->readLink ) ) {
			try{
				$this->readLink = $this->_connect( false );
			}catch( \Throwable $e){
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
	}	
}