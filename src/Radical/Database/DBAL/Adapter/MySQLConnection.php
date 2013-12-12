<?php
namespace Radical\Database\DBAL\Adapter;

use Radical\Database\DBAL\Instance;
use Radical\Database\Exception;

class MySQLConnection implements IConnection {
	/**
	 * @var \mysqli
	 */
	private $mysqli;
	
	//
	private $host;
	private $user;
	private $pass;
	public $db;
	private $port;
	private $compression;
	
	function __construct($host, $user, $pass, $db = null, $port = 3306, $compression=true){
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->db = $db;
		$this->port = $port;
		$this->compression = $compression;
	}
	
	/**
	 * Connect to database
	 * 
	 * @throws Exception\ConnectionException
	 * @return \mysqli
	 */
	function connect(){
		if($this->isConnected()){
			return $this->mysqli;
		}
		
		$this->mysqli = mysqli_init();
		
		//Connect - With compression
		$connection_status = mysqli_real_connect ( $this->mysqli, $this->host, 
				$this->user, $this->pass, $this->db, $this->port,
				null, $this->compression?MYSQLI_CLIENT_COMPRESS:0 );
		
		if (! $connection_status) {
			throw new Exception\ConnectionException ( $this->__toString(), $this->Error() );
		}
		
		return $this->mysqli;
	}
	
	function commit(){
		return $this->mysqli->commit();
	}
	
	function rollback(){
		return $this->mysqli->rollback();
	}
	
	function ping(\mysqli $mysqli=null){
		if(!$mysqli){
			$mysqli = $this->Connect();
		}

		//Ping
		return $mysqli->ping();		
	}
	
	/**
	 * is the MySQL server connected?
	 * @return boolean
	 */
	private $_connectCache;
	private $_connectHit;
	function isConnected() {
		if(php_sapi_name() == 'fpm-fcgi') return $this->mysqli;//Web requests are short
		
		$ret = false;
		if($this->_connectHit <= ($t = time())){
			if($this->_connectCache == \CLI\Threading\Thread::current()){
				$ret = true;
			}
		}
		if(!$ret){
			$ret = ($this->mysqli && $this->mysqli->ping());
		}
		if($ret){
			$this->_connectCache = \CLI\Threading\Thread::current();
			$this->_connectHit = $t+30;//Persume we can hold a connection for 30s
		}
		
		return $ret;
	}
	
	function toInstance(){
		return new Instance($this,$this->host,$this->user,$this->pass,$this->db,$this->port,$this->compression);
	}
	
	function reConnect(){
		$this->Close();
		$this->Connect();
	}
	
	function close(){
		if($this->mysqli){
			mysqli_close($this->mysqli);
			$this->mysqli = null;
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
		
		return $this->Connect()->query ( $sql );
	}
	
	function prepare($sql){
		$sql = trim($sql);
		if(!$sql){
			throw new \Exception('Empty Query');
		}
	
		return new MySQL\PreparedStatement($sql);
	}
	
	function escape($string){
		return $this->Connect()->real_escape_string($string);
	}
	
	/**
	 * Return the last MySQL error
	 */
	function error() {
		return $this->mysqli->error;
	}
	
	/**
	 * Return the number of affected rows of the last MySQL query
	 */
	function affectedRows() {
		return mysqli_affected_rows ( $this->Connect() );
	}
	
	/**
	 * @return string
	 */
	function __toString(){
		return 'mysqli://' . $this->user . '@' . $this->host . ':' . $this->port . ($this->compression?'z':'') . '/' . $this->db;
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