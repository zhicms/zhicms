<?php
namespace ZhiCms\ext;

class Dbbak {
	public $dbhost;
	public $dbuser;
	public $dbpw;
	public $dbname;
	public $dataDir;
	protected $transfer = "";
	protected $pdo = null;
	
	public function __construct($dbhost,$dbuser,$dbpw,$dbname,$charset='utf8mb4',$dir='data/dbbak/')
	{		
		$this->connect($dbhost,$dbuser,$dbpw,$dbname,$charset);
		$this->dataDir=$dir;
	}

	public function connect($dbhost,$dbuser,$dbpw,$dbname,$charset='utf8mb4')
	{
		$this->dbhost = $dbhost;
		$this->dbuser = $dbuser;
		$this->dbpw = $dbpw;
		$this->dbname = $dbname;
		
		try {
			$this->pdo = new \PDO(
				"mysql:host={$dbhost};dbname={$dbname};charset={$charset}",
				$dbuser,
				$dbpw,
				array(
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
				)
			);
			return true;
		} catch (\PDOException $e) {
			$this->error('无法连接数据库服务器: ' . $e->getMessage());
			return false;
		}
	}

	public function getTables($database='')
	{
		$database = empty($database) ? $this->dbname : $database;
		try {
			$result = $this->pdo->query("SHOW TABLES FROM `{$database}`");
			$dbArry = array();
			while ($tmpArry = $result->fetch(\PDO::FETCH_NUM)) {
				$dbArry[] = $tmpArry[0];
			}
			return $dbArry;
		} catch (\PDOException $e) {
			throw new \Exception($e->getMessage(), 500);
		}
	}

    public function exportSql($table='',$subsection=0)
	 {
		$table = empty($table) ? $this->getTables() : $table;
     	if(!$this->_checkDir($this->dataDir))
		{
			$this->error('您没有权限操作目录,备份失败');
			return false;
		}
		
     	if($subsection == 0)
		{
     		if(!is_array($table))
			{
				$this->_setSql($table,0,$this->transfer);
			}
			else
			{
				foreach($table as $t)
				{
					$this->_setSql($t,0,$this->transfer);
				}
			}
     		$fileName = $this->dataDir.date("Ymd",time()).'_all.sql.php';
     		if(!$this->_writeSql($fileName,$this->transfer))
			{
				return false;
			}
     	}
		else
		{
     		if(!is_array($table))
			{
				$sqlArry = $this->_setSql($table,$subsection,$this->transfer);
				$sqlArry[] = $this->transfer;
			}
			else
			{
				$sqlArry = array();
				foreach($table as $t){
					$tmpArry = $this->_setSql($t,$subsection,$this->transfer);
					$sqlArry = array_merge($sqlArry,$tmpArry);
				}
				$sqlArry[] = $this->transfer;
			}
     		foreach($sqlArry as $key => $sqlContent)
			{
     			$fileName = $this->dataDir.date("Ymd",time()).'_part'.$key.'.sql.php';
     			if(!$this->_writeSql($fileName,$sqlContent))
				{
					return false;
				}
     		}
     	}
     	return true;
    }
	
    public function importSql($dir=''){
		
		if(is_file($dir))
		{
			return $this->_importSqlFile($dir);
		}
		$dir = empty($dir) ? $this->dataDir : $dir;
		if($link = opendir($dir))
		{
			$fileArry = scandir($dir);
			$pattern = "/_part[0-9]+.sql.php$|_all.sql.php$/";
			foreach($fileArry as $file)
			{
				if(preg_match($pattern,$file))
				{
					if(false == $this->_importSqlFile($dir.$file))
					{
						return false;
					}
				}
			}
			return true;
		}
    }
	
    protected function _importSqlFile($filename='')
	{
		$sqls = file_get_contents($filename);
		$sqls = substr($sqls,13);
		$sqls = explode("\n",$sqls);
		if(empty($sqls))
			return false;
			
		foreach($sqls as $sql)
		{
			$sql = trim($sql);
			if(empty($sql))
				continue;
			try {
				$this->pdo->exec($sql);
			} catch (\PDOException $e) {
				$this->error('恢复失败：' . $e->getMessage());
				return false;
			}
		}
		return true;
    }
	
	protected function _setSql($table,$subsection=0,&$tableDom=''){
		$tableDom .= "DROP TABLE IF EXISTS `{$table}`;\n";
		$createtable = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
		$create = $createtable->fetch(\PDO::FETCH_NUM);
		$create[1] = str_replace("\n","",$create[1]);
		$create[1] = str_replace("\t","",$create[1]);

		$tableDom .= $create[1].";\n";

		$rows = $this->pdo->query("SELECT * FROM `{$table}`");
		$numfields = $rows->columnCount();
		$n = 1;
		$sqlArry = array();
		while ($row = $rows->fetch(\PDO::FETCH_NUM))
		{
		   $comma = "";
		   $tableDom .= "INSERT INTO `{$table}` VALUES(";
		   for($i = 0; $i < $numfields; $i++)
		   {
				if ($row[$i] === null) {
					$tableDom .= $comma . 'NULL';
				} else {
					$tableDom .= $comma . "'" . $this->pdo->quote($row[$i]) . "'";
				}
				$comma = ",";
		   }
		  $tableDom .= ");\n";
		   if($subsection != 0 && strlen($this->transfer) >= $subsection * 1000){
		   		$sqlArry[$n] = $tableDom;
		   		$tableDom = ''; $n++;
		   }
		}
		return $sqlArry;
   }
   
	protected function _checkDir($dir){
		if(!is_dir($dir)) {@mkdir($dir, 0777);}
		if(is_dir($dir)){
			if($link = opendir($dir)){
				$fileArry = scandir($dir);
				foreach($fileArry as $file){
					if($file != '.' && $file != '..'){
						@unlink($dir.$file);
					}
				}
			}
		}
		return true;
	}
	
	protected function _writeSql($fileName,$str){
		$re = true;
		if(!$fp = @fopen($fileName,"w+")) 
		{
			$re = false; $this->error("在打开文件时遇到错误，备份失败!");
		}
		if(!@fwrite($fp,'<?php exit;?>'.$str)) 
		{
			$re = false; $this->error("在写入信息时遇到错误，备份失败!");
		}
		if(!@fclose($fp)) 
		{
			$re = false; $this->error("在关闭文件时遇到错误，备份失败!");
		}
		return $re;
	}
	public function error($str)
	{
		throw new \Exception($str, 500);
	}
}
