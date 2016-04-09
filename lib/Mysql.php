<?php
/**
 * LMLPHP Framework
 * Copyright (c) 2014 http://lmlphp.com All rights reserved.
 * Licensed ( http://mit-license.org/ )
 * Author: leiminglin <leiminglin@126.com>
 *
 * A fully object-oriented PHP framework.
 * Keep it light, magnificent, lovely.
 *
 */

class Mysql{

	private $link;
	private $resource;
	private static $instances;

	private function __construct($config){
		if ( !extension_loaded('mysql') ) {
			throw new LmlDbException('MySQL support not be enabled');
		}
		$this->connect($config);
	}

	public static function getInstance($config){
		$flag = $config['hostname'] . $config['database'];
		if (isset(self::$instances[$flag])) {
			return self::$instances[$flag];
		}
		return self::$instances[$flag] = new self($config);
	}

	public function connect($config='') {
		if ( !$this->link ) {
			$host = $config['hostname'].($config['hostport']?":{$config['hostport']}":'');
			$pconnect = isset($config['persist'])?$config['persist']:0;
			if($pconnect){
				$this->link = mysql_pconnect($host, $config['username'], $config['password'],
					MYSQL_CLIENT_COMPRESS*MYSQL_CLIENT_IGNORE_SPACE*
						MYSQL_CLIENT_INTERACTIVE*MYSQL_CLIENT_SSL);
			}else{
				$this->link = mysql_connect($host, $config['username'], $config['password'], true,
					MYSQL_CLIENT_COMPRESS*MYSQL_CLIENT_IGNORE_SPACE*
						MYSQL_CLIENT_INTERACTIVE*MYSQL_CLIENT_SSL);
			}
			if ( !$this->link || (!empty($config['database']) &&
					!mysql_select_db($config['database'], $this->link) ) ) {
				throw new LmlDbException(mysql_error());
			}
			$dbVersion = mysql_get_server_info($this->link);
			mysql_query("SET NAMES '".$config['charset']."'", $this->link);
			if($dbVersion >'5.0.1'){
				mysql_query("SET sql_mode=''",$this->link);
			}
		}
		return $this;
	}

	public function free() {
		if( is_resource($this->resource) ){
			mysql_free_result($this->resource);
		}
		$this->resource = null;
	}

	public function query($str, $params=array()) {
		if($params){
			foreach($params as $k=>$v){
				$v = $this->escapeString($v);
				if(!is_numeric($v)){
					$v = "'".$v."'";
				}
				if(is_int($k)){
					$str = preg_replace('/\?/', $v, $str, 1);
				}else{
					$str = preg_replace('/:'.$k.'/', $v, $str, 1);
				}
			}
		}
		if($this->resource){
			$this->free();
		}
		$this->resource = mysql_query($str, $this->link);
		if( false === $this->resource) {
			$this->error();
			return false;
		}elseif(is_resource($this->resource)) {
			$this->numRows = mysql_num_rows($this->resource);
			return $this->getAll();
		}else{
			return mysql_affected_rows($this->link);
		}
	}

	public function insert($table, $arr){
		$sql = 'INSERT INTO '.$table;
		foreach ($arr as $k=>$v){
			$keys[] = '`'.$k.'`';
			$vals[] = '\''.$this->escapeString($v).'\'';
		}
		$sql .= '('.implode(',', $keys).')';
		$sql .= 'VALUES('.implode(',', $vals).')';
		return $this->query($sql);
	}

	public function update($table, $arr, $where=''){
		$sql = 'UPDATE '.$table.' SET ';
		foreach ($arr as $k=>$v){
			$sql .= '`'.$k.'` = \''.$this->escapeString($v).'\',';
		}
		$sql = rtrim($sql, ',');
		if($where){
			$sql .= ' WHERE '.$where;
		}
		return $this->query($sql);
	}

	public function delete($table, $where='', $params=array()){
		$sql = 'DELETE FROM '.$table;
		if($where){
			$sql .= ' WHERE '.$where;
		}
		return $this->query($sql, $params);
	}

	public function select($table, $fields='*', $where_tail='', $params=array()){
		$sql = 'SELECT '.$fields.' FROM '.$table;
		if($where_tail){
			$sql .= ' WHERE '.$where_tail;
		}
		return $this->query($sql, $params);
	}

	public function getOne($str, $params=array()){
		$rs = $this->query($str, $params);
		return isset($rs[0])?$rs[0]:array();
	}

	public function getLastId(){
		return mysql_insert_id($this->link);
	}

	public function execute($str) {
		if ( $this->resource ) {
			$this->free();
		}
		$result = mysql_query($str, $this->link) ;
		if ( false === $result) {
			$this->error();
			return false;
		} else {
			return mysql_affected_rows($this->link);
		}
	}

	public function startTrans() {
		mysql_query('START TRANSACTION', $this->link);
		return ;
	}

	public function commit() {
		$result = mysql_query('COMMIT', $this->link);
		if(!$result){
			$this->error();
		}
		return true;
	}

	public function rollback() {
		$result = mysql_query('ROLLBACK', $this->link);
		if(!$result){
			$this->error();
			return false;
		}
		return true;
	}

	private function getAll() {
		$result = array();
		while( true==( is_resource($this->resource) &&
				$row=mysql_fetch_assoc($this->resource)) ){
			$result[] = $row;
		}
		if(!empty($result)){
			mysql_data_seek($this->resource, 0);
		}
		return $result;
	}

	public function escapeString($str) {
		if($this->link) {
			return mysql_real_escape_string($str,$this->link);
		}else{
			return mysql_escape_string($str);
		}
	}

	public function close() {
		if ($this->link){
			mysql_close($this->link);
		}
		$this->link = null;
	}

	private function error(){
		throw new LmlDbException(mysql_error($this->link));
	}

}
class LmlDbException extends LmlException{}
