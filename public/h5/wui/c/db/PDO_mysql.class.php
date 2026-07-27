<?php

class PDO_mysql {
	var $__connection;
	var $__dbinfo;
	var $__persistent = false;
	var $__errorCode = '';
	var $__errorInfo = array('');

	function PDO_mysql(&$host, &$db, &$user, &$pass) {
		try {
			$this->__connection = new PDO(
				"mysql:host={$host};dbname={$db};charset=utf8mb4",
				$user,
				$pass,
				array(
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
				)
			);
			$this->__dbinfo = array($host, $user, $pass, $db);
		} catch (PDOException $e) {
			$this->__setErrors('DBCON');
		}
	}

	function close() {
		$this->__connection = null;
		return true;
	}

	function errorCode() {
		return $this->__errorCode;
	}

	function errorInfo() {
		return $this->__errorInfo;
	}

	function exec($query) {
		try {
			return $this->__connection->exec($query);
		} catch (PDOException $e) {
			$this->__setErrors('SQLER');
			return false;
		}
	}

	function lastInsertId() {
		try {
			return $this->__connection->lastInsertId();
		} catch (PDOException $e) {
			$this->__setErrors('SQLER');
			return false;
		}
	}

	function prepare($query, $array = array()) {
		return new PDOStatement_mysql($query, $this->__connection, $this->__dbinfo);
	}

	function query($query) {
		try {
			$result = $this->__connection->query($query);
			if ($result) {
				return $result->fetchAll(PDO::FETCH_ASSOC);
			}
			return false;
		} catch (PDOException $e) {
			$this->__setErrors('SQLER');
			return false;
		}
	}

	function quote($string) {
		return $this->__connection->quote($string);
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

	function beginTransaction() {
		try {
			return $this->__connection->beginTransaction();
		} catch (PDOException $e) {
			return false;
		}
	}

	function commit() {
		try {
			return $this->__connection->commit();
		} catch (PDOException $e) {
			return false;
		}
	}

	function rollBack() {
		try {
			return $this->__connection->rollBack();
		} catch (PDOException $e) {
			return false;
		}
	}

	function __setErrors($er) {
		try {
			$this->__errorInfo = $this->__connection->errorInfo();
		} catch (Exception $e) {
			$this->__errorInfo = array($er, 0, '');
		}
		$this->__errorCode = $er;
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
