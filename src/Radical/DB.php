<?php
namespace Radical;
use Radical\Database\DBAL;
use Radical\Database\DBAL\Adapter;
use Radical\Database\DBAL\Handler;
use Radical\Database\SQL\SelectStatement;

/**
 * Database Interface Class
 * @author SplitIce
 *
 */
class DB extends DBAL\SQLUtils {	
	static $connectionPool;

	static $query_log;
	
	static $connectionDetails;
	
	static $isInQuery = false;
	
	function __construct(){
		throw new \BadMethodCallException('DB is an abstract class');
	}
	
	static function init(){
		if(!static::$query_log){
			static::$query_log = new Handler\QueryLog ();
		}
		
		if(!static::$connectionPool){
			static::$connectionPool = new Handler\ConnectionPool();
		}
	}
	
	/**
	 * Connect to a MySQL database
	 * @param string $host
	 * @param string $user
	 * @param string $pass
	 * @param string $db
	 * @throws \Model\Database\Exception\ConnectionException
	 */
	static function connect(Adapter\IConnection $connection) {
		static::Init();
		
		static::$connectionDetails = $connection;
		
		return static::getInstance($connection);
	}
	
	/**
	 * @param \Radical\Database\DBAL\Adapter\IConnection $connection
	 * @return \Radical\Database\DBAL\Instance
	 */
	static function getInstance(Adapter\IConnection $connection = null){
		if($connection === null){
			if(static::$connectionDetails === null){
				global $_SQL;
				if(isset($_SQL)){
					static::Connect($_SQL);
				}
			}
			$connection = static::$connectionDetails;
		}
		
		if(!static::$connectionPool){
			throw new Database\Exception\ConnectionException('[n/a]');
		}
		
		//Get Database Instance from connection details
		return static::$connectionPool->GetInstance($connection);
	}

	static function reConnect(){
		$ins = static::getInstance();
		$ins->Close(true);
		$ins->Connect();
	}
	
	static function multiQuery($sql = null){
		if($sql !== null){
			static::getInstance()->multiQuery($sql);
			return;
		}
		return new DBAL\MultiQuery(self::getInstance());
	}
	

	/**
	 * Convert MySQL timestamp to php integer timestamp
	 * @param string $d
	 * @return number
	 */
	static function timeStamp($d) {
		return strtotime ( $d );
	}

	static function toTimeStamp($i) {
	    if(!is_numeric($i)) throw new \RuntimeException("$i is not numeric");
		return gmdate ( "Y-m-d H:i:s", $i );
	}

	static function bin($x) {
		return new DBAL\Binary($x);
	}

	public static function __callStatic($method,$argument){
		$instance = static::getInstance();
		if(!method_exists($instance,$method)){
			throw new \BadMethodCallException('Database method: "'.$method.'" doesnt exist');
		}
		return $instance->$method(...$argument);
	}

    public static function ___callStatic($method, $argument){
        return static::getInstance()->$method(...$argument);
    }
	
	/* Predefined Methods */
	
	static function close($real = false){
		static::getInstance()->Close($real);
	}
	
	static function tableExists() {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Return the number of affected rows of the last MySQL query
	 */
	static function affectedRows() {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Execute MySQL query
	 * @param string $sql
	 * @throws \Radical\Database\Exception\QueryError
	 * @return \Radical\Database\DBAL\Result
	 */
	static function query($sql,$timeout=DBAL\Instance::QUERY_TIMEOUT,$is_retry=false) {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Shorthand for Query
	 *
	 * @param string $sql
	 * @param int $timeout
	 * @return \Radical\Database\DBAL\RowResult
	 */
	static function q($sql,$timeout=DBAL\Instance::QUERY_TIMEOUT){
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	static function multipleInsert($tbl, $cols, $data, $ignore = false){
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Build and Execute a MySQL insert
	 * @param string $tbl table name
	 * @param string $data unescaped data in key=>value format
	 * @return boolean
	 */
	static function insert($tbl, $data, $ignore = false) {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Build and Execute a MySQL update
	 * @param string $tbl table name
	 * @param array $data unescaped data in key=>value format
	 * @param array $where Where conditions
	 * @return boolean
	 */
	static function update($tbl, $data, $where, $limit = null) {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	static function fOUND_ROWS() {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Delete row[s] from a mysql database
	 * @param string $tbl table name
	 * @param string $where where condition
	 */
	static function delete($tbl, $where) {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Escape a value into SQL format
	 * @param string|int $str
	 * @return string
	 */
	static function escape($str) {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	static function e($str){
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Return the last MySQL error
	 */
	static function error() {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Return the AUTO_INCREMENT value of the last MySQL insert
	 */
	static function insertId() {
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	static function fetch(DBAL\Result $res, $format = DBAL\Fetch::ASSOC, $cast=null){
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/**
	 * Perform MySQL fetch and execute $callback on it returning the result
	 * @param mysqli_result $res
	 * @param function $callback
	 * @param Database\Fetch:: $format
	 * @return Array <int, mixed>
	 */
	static function fetchCallback($res, $callback, $format = DBAL\Fetch::ALL_ASSOC)
    {
        return static::___callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * @param $res
     * @return integer
     */
	static function numRows($res){
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	
	/* Start / End transaction */
	static function transactionStart(){
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	static function transactionCommit(){
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
	static function transaction($what){
		return static::___callStatic(__FUNCTION__, func_get_args());
	}
    static function inTransaction(){
        return static::___callStatic(__FUNCTION__, func_get_args());
    }
    static function transactionManager(){
        $instance = static::getInstance();
        if($instance){
            return $instance->transactionManager;
        }
    }
	
	/* Sql Builders */
	static function select($table = null, $fields = '*'){
		return new SelectStatement($table,$fields);
	}
}