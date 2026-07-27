<?php

class PDOStatement_mysql {
	var $__connection;
	var $__dbinfo;
	var $__persistent = false;
	var $__query = '';
	var $__result = null;
	var $__fetchmode = PDO::FETCH_BOTH;
	var $__errorCode = '';
	var $__errorInfo = array('');
	var $__boundParams = array();

	function PDOStatement_mysql(&$__query, &$__connection, &$__dbinfo) {
		$this->__query = &$__query;
		$this->__connection = &$__connection;
		$this->__dbinfo = &$__dbinfo;
	}

	function bindParam($mixed, &$variable, $type = null, $lenght = null) {
		if(is_string($mixed))
			$this->__boundParams[$mixed] = $variable;
		else
			array_push($this->__boundParams, $variable);
	}

	function columnCount() {
		if(!is_null($this->__result)) {
			return $this->__result->columnCount();
		}
		return 0;
	}

	function errorCode() {
		return $this->__errorCode;
	}

	function errorInfo() {
		return $this->__errorInfo;
	}

	function execute($array = array()) {
		if(count($this->__boundParams) > 0)
			$array = &$this->__boundParams;
		
		if(count($array) > 0) {
			foreach($array as $k => $v) {
				if(!is_int($k) || substr($k, 0, 1) === ':') {
					if(!isset($tempf))
						$tempf = $tempr = array();
					array_push($tempf, $k);
					array_push($tempr, $this->__connection->quote($v));
				}
				else {
					$k = 0;
					$this->__query = preg_replace_callback("/(\?)/", function($matches) use (&$k, $array) {
						return $this->__connection->quote($array[$k++]);
					}, $this->__query);
					break;
				}
			}
			if(isset($tempf)) {
				foreach ($tempf as $k=>$v) {
					$search[$k] = '/' . preg_quote($tempf[$k],'`') . '\b/';
				}
				$this->__query = preg_replace($search, $tempr, $this->__query);
			}
		}
		
		try {
			$this->__result = $this->__connection->query($this->__query);
			$this->__boundParams = array();
			return true;
		} catch (PDOException $e) {
			$this->__setErrors('SQLER');
			$this->__result = null;
			$this->__boundParams = array();
			return false;
		}
	}

	function fetch($mode = PDO_FETCH_ASSOC, $cursor = null, $offset = null) {
		if(func_num_args() == 0)
			$mode = &$this->__fetchmode;
		$result = false;
		if(!is_null($this->__result)) {
			try {
				$result = $this->__result->fetch($mode);
			} catch (PDOException $e) {
				$result = false;
			}
		}
		if(!$result)
			$this->__result = null;
		return $result;
	}

	function fetchAll($mode = PDO_FETCH_ASSOC) {
		$result = array();
		if(!is_null($this->__result)) {
			try {
				$result = $this->__result->fetchAll($mode);
			} catch (PDOException $e) {
				$result = array();
			}
		}
		$this->__result = null;
		return $result;
	}

	function fetchSingle() {
		$result = null;
		if(!is_null($this->__result)) {
			try {
				$row = $this->__result->fetch(PDO::FETCH_NUM);
				if($row)
					$result = $row[0];
				else
					$this->__result = null;
			} catch (PDOException $e) {
				$this->__result = null;
			}
		}
		return $result;
	}

	function fetchColumn($column=0) {
		if(!is_null($this->__result)) {
			try {
				return $this->__result->fetchColumn($column);
			} catch (PDOException $e) {
				return false;
			}
		}
		return false;
	}

	function rowCount() {
		if(!is_null($this->__result)) {
			try {
				return $this->__result->rowCount();
			} catch (PDOException $e) {
				return 0;
			}
		}
		return 0;
	}

	function getAttribute($attribute) {
		try {
			return $this->__connection->getAttribute($attribute);
		} catch (PDOException $e) {
			return false;
		}
	}

	function setAttribute($attribute, $mixed) {
		try {
			return $this->__connection->setAttribute($attribute, $mixed);
		} catch (PDOException $e) {
			return false;
		}
	}

	function setFetchMode($mode) {
		$result = false;
		switch($mode) {
			case PDO_FETCH_NUM:
			case PDO_FETCH_ASSOC:
			case PDO_FETCH_OBJ:
			case PDO_FETCH_BOTH:
				$result = true;
				$this->__fetchmode = &$mode;
				break;
		}
		return $result;
	}

	function bindColumn($mixewd, &$param, $type = null, $max_length = null, $driver_option = null) {
		return false;
	}

	function __setErrors($er) {
		try {
			$this->__errorInfo = $this->__connection->errorInfo();
		} catch (Exception $e) {
			$this->__errorInfo = array($er, 0, '');
		}
		$this->__errorCode = $er;
		$this->__result = null;
	}

	function __uquery(&$query) {
		try {
			return $this->__connection->query($query);
		} catch (PDOException $e) {
			$this->__setErrors('SQLER');
			return null;
		}
	}
}
