<?php
namespace Radical\Database\DBAL\Adapter;

use Radical\Database\DBAL\Adapter\MySQL\IMysqlConnector;
use Radical\Database\DBAL\Instance;
use Radical\Database\Exception;

class MySQLConnection implements IConnection {
	private $connector;
	/**
	 * @var \mysqli
	 */
	private $last_connection;

	function __construct(IMysqlConnector $connector){
		$this->connector = $connector;
	}
	
	/**
	 * Connect to database
	 * 
	 * @throws Exception\ConnectionException
	 * @return \mysqli
	 */
	function connect(){
		$this->last_connection = $this->connector->getConnection($this);
		return $this->last_connection;
	}


	/* Savepoints */
	function savepointStart($name){
		return $this->connect()->savepoint($name);
	}
	function savepointRollback($name){
		return $this->last_connection->query('ROLLBACK TO SAVEPOINT '.$name);
	}
	function savepointCommit($name){
		return $this->last_connection->release_savepoint($name);
	}

	/* Transactions */
    function beginTransaction(){
        return $this->connect()->autocommit(false);
    }
	
	function commit(){
		$ret = $this->last_connection->commit();
		return $this->last_connection->autocommit(true) && $ret;
	}
	
	function rollback(){
		$ret = $this->last_connection->rollback();
		return $this->last_connection->autocommit(true) && $ret;
	}
	
	function ping(\mysqli $mysqli=null){
		if(!$mysqli){
			$mysqli = $this->connect();
		}

		//Ping
		return $mysqli->ping();		
	}
	
	function toInstance(){
		return new Instance($this,$this->connector);
	}
	
	function reConnect(){
		$this->close();
		$this->connect();
	}
	
	function close(){
		if($this->last_connection){
			mysqli_close($this->last_connection);
			$this->last_connection = null;
		}
	}
	
	function __destruct(){
		//I wish I could implement it this way		
		//echo "Connection Freed\r\n";
		//\Radical\DB::$connectionPool->Free($this);
	}
	
	function query($sql){
		$sql = trim($sql);
		if(!$sql){
			throw new \Exception('Empty Query');
		}
		
		return @$this->connect()->query ( $sql );
	}
	
	function prepare($sql){
		$sql = trim($sql);
		if(!$sql){
			throw new \Exception('Empty Query');
		}
	
		return new MySQL\PreparedStatement($sql, $this);
	}
	
	function escape($string){
		return $this->connect()->real_escape_string($string);
	}
	
	/**
	 * Return the last MySQL error
	 */
	function error() {
		return $this->last_connection?$this->last_connection->error:null;
	}
	
	/**
	 * Return the number of affected rows of the last MySQL query
	 */
	function affectedRows() {
		return mysqli_affected_rows ( $this->connect() );
	}
	
	/**
	 * @return string
	 */
	function __toString(){
		return 'mysqli://' . $this->connector;
	}
	
	static function fromArray(array $from){
		if(!isset($from['host'])){
			throw new \InvalidArgumentException('Mysql connection parameters must have a host');
		}
		if(!isset($from['user'])){
			throw new \InvalidArgumentException('Mysql connection parameters must have a username');
		}
		if(!isset($from['pass'])){
			throw new \InvalidArgumentException('Mysql connection parameters must have a password');
		}
		if(!isset($from['db'])){
			throw new \InvalidArgumentException('Mysql connection parameters must have a database');
		}
		if(!isset($from['port'])){
			$from['port'] = 3306;
		}
		if(!isset($from['compression'])){
			$from['compression'] = false;
		}
		return new static($from['host'],$from['user'],$from['pass'],$from['db'],$from['port'],$from['compression']);
	}
}