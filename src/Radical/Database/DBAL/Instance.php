<?php
namespace Radical\Database\DBAL;
use Radical\Database\DBAL;
use Radical\Database\Exception;
use Radical\Database\IToSQL;
use Radical\Database\Model\TableReference;
use Radical\Database\SQL;
use Splitice\EventTrait\THookable;

class Instance {
	use THookable;

	const QUERY_TIMEOUT = 30;
	
	/* Psudeo Returns */
	const NOT_A_RESULT = null;

    /**
     * @var Adapter\IConnection
     */
	public $adapter;

    /**
     * @var TransactionManager
     */
    public $transactionManager;

	private $instanceId;

	function __construct(Adapter\IConnection $adapter, $host, $user, $pass, $db = null, $port = 3306, $compression=true){
		$this->adapter = new $adapter($host, $user, $pass, $db, $port, $compression);
        $this->transactionManager = new TransactionManager($this);
		$this->instanceId = crc32(rand() . microtime(true) . rand());
		$this->hookInit();
	}
	
	function close(){
		\Radical\DB::$connectionPool->Free($this);
	}
	
	function __call($func,$args){
		return call_user_func_array(array($this->adapter,$func), $args);
	}
	
	/**
	 * @var \mysqli
	 */
	public $isInQuery = false;
	
	/**
	 * Execute MySQL query
	 * @param string $sql
	 * @throws \DB\Exception\QueryError
	 * @return resource|Result
	 */
	function query($sql,$timeout=self::QUERY_TIMEOUT,$is_retry=false) {
		$mysqli = $this->Connect();
		
		//We are now in-query
		$this->isInQuery = $sql;
		
		//if(!\Server::isCLI()){
		//	set_time_limit($timeout);
		//}
		
		//Build SQL if applicable
		if($sql instanceof IToSQL){
			$sql = $sql->toSQL();
		}
		//Do Query
		//echo $sql,"\r\n";
		$res = $this->adapter->Query($sql);
		
		//Query Done
		$this->isInQuery = false;
	
		if ($res === false) { //Failure
			$errno = mysqli_errno( $mysqli );
			if(!$is_retry && ($errno == 2006 || $errno == 2013)){
				$this->reConnect();
				return $this->Q($sql,$timeout,true);
			}else{
				$error = $this->Error();
				file_put_contents('/tmp/last_sql.err', $sql);
				$exception = new Exception\QueryError ($sql, $error);
				if (!$is_retry) {
					if($errno == 1412 || $errno == 1213  || $errno == 1205){
						$exception = null;
					}
					if($exception) {
						$this->call_filter('error_handler', $exception);
					}
					if($exception === null){
						if($this->inTransaction()){
							$exception = new TransactionException($error);
						}else {
							return $this->query($sql, $timeout, true);
						}
					}
				}
				throw $exception;
			}
		} else {
			\Radical\DB::$query_log->addQuery ( $sql ); //add query to log
	
			if ($res === true) { //Not a SELECT, SHOW, DESCRIBE or EXPLAIN
				return static::NOT_A_RESULT;
			} else {
				return new DBAL\Result($res,$this);
			}
		}
	}
	
	/**
	 * Shorthand for Query
	 * 
	 * @param string $sql
	 * @param int $timeout
	 * @return Ambigous <resource, \DB\NOT_A_RESULT, string, unknown>
	 */
	function q($sql,$timeout=self::QUERY_TIMEOUT){
		return $this->Query($sql,$timeout);
	}
	
	function multipleInsert($tbl, $cols, $data, $ignore = false){
		$append = array();
		foreach($data as $d){
			$append[] = '(' . $this->A ( $d ) . ')';
		}
		$append = implode(',', $append);
	
		$sql = 'INSERT ' . ($ignore ? 'IGNORE ' : '') . 'INTO `' . $tbl . '` (`' . implode ( '`,`', $cols ) . '`) VALUES'.$append;
		return ( bool ) $this->Query ( $sql );
	}
	
	/**
	 * Build and Execute a MySQL insert
	 * @param string $tbl table name
	 * @param array $data unescaped data in key=>value format
	 * @return boolean
	 */
	function insert($tbl, $data, $ignore = false) {
		$insert = new SQL\InsertStatement($tbl, $data, $ignore);

		//Execute
		$success = $this->Query ( $insert );

		if($success === false) return false;
		
		//NOT_A_RESULT
		return $this->InsertId();
	}
	
	/**
	 * Build and Execute a MySQL update
	 * @param string $tbl table name
	 * @param array $data unescaped data in key=>value format
	 * @param array $where Where conditions
	 * @return boolean
	 */
	function update($tbl, $data, $where) {
		$update = new SQL\UpdateStatement($tbl, $data, $where);
		
		//Execute
		return ( bool ) $this->Query ( $update );
	}
	
	function found_rows() {
		$res = $this->Query ( 'SELECT FOUND_ROWS()' );
		return $this->Fetch ( $res, DBAL\Fetch::FIRST );
	}
	
	/**
	 * Delete row[s] from a mysql database
	 * @param string $tbl table name
	 * @param string $where where condition
	 */
	function delete($tbl, $where) {
		$delete = new SQL\DeleteStatement($tbl, $where);
		$this->Query ( $delete );
	}
	
	function tableExists($table){
		return TableReference::getByTableClass(__CLASS)->exists();
	}
	
	/**
	 * Escape a value into SQL format
	 * @param string|int $str
	 * @return string
	 */
	function escape($str) {
		if ($str === null) {
			return 'NULL';
		}
		if(is_numeric($str) && ((int)$str) == $str){
			return $str;
		}
		if (is_array ( $str )) {
			throw new \BadMethodCallException('cant escape an array');
		}
		if (is_object ( $str )) {
			if(method_exists($str, 'toEscaped')){//depreciated
				return $str->toEscaped();
			}elseif($str instanceof IToSQL){
				return $str->toSQL();
			}elseif(method_exists($str, '__toString')){
				$str = (string)$str;
			}else{
				throw new \BadMethodCallException('cant escape this object, non escapable');
			}
		}
		return '\'' . $this->adapter->Escape ( $str ) . '\'';
	}
	
	function e($str){
		return $this->Escape($str);
	}
	
	/**
	 * Return the AUTO_INCREMENT value of the last MySQL insert
	 */
	function insertId() {
		$mysqli = $this->Connect();
		return $mysqli->insert_id;
	}
	
	function fetch(DBAL\Result $res, $format = DBAL\Fetch::ASSOC, $cast=null){
		return $res->Fetch($format,$cast);
	}
	
	/**
	 * Perform MySQL fetch and execute $callback on it returning the result
	 * @param mysqli_result $res
	 * @param function $callback
	 * @param DB\Fetch:: $format
	 * @return Array <int, mixed>
	 */
	function fetchCallback($res, $callback, $format = DBAL\Fetch::ALL_ASSOC) {
		return $res->FetchCallback($callback,$format);
	}
	
	
	function numRows($res){
		return mysqli_num_rows($res);
	}
	
	/* Start / End transaction */
	function transactionStart(){
		$result = $this->adapter->beginTransaction();
        if(!$result){
            throw new TransactionException("Transaction BEGIN failed");
        }
		$this->transactionManager->inTransaction = true;
	}
	function transactionCommit(){
		$result = $this->adapter->commit();
        $this->transactionManager->inTransaction = false;
		$this->transactionManager->transactionCount++;
        if(!$result){
			throw new TransactionException("Transaction COMMIT failed");
		}
        $this->transactionManager->handleOnCommit();
	}
	function transactionRollback(){
        $result = $this->adapter->rollback();
        $this->transactionManager->inTransaction = false;
		$this->transactionManager->transactionCount++;
        if(!$result){
            throw new TransactionException("Transaction ROLLBACK failed");
        }
        $this->transactionManager->handleOnRollback();
	}
	function transactionId(){
		return $this->instanceId . '-' . $this->transactionManager->transactionCount;
	}
    function inTransaction(){
        return $this->transactionManager->inTransaction;
    }

	private function transactionTooLong(){
		$e = new \Exception();
		file_put_contents('/tmp/transaction_long.txt',$e->getTraceAsString());
	}
	
	function transaction($method, $retries = 5, $auto_de_nest = true){
        if($this->inTransaction() && $auto_de_nest){
            return $method();
        }

        $ex = null;

        for($i=0;$i<$retries;$i++) {
            try {
				$start = microtime(true);
                $this->transactionStart();
                $ret = $method();
                $this->transactionCommit();
				$end = microtime(true);
				if(($start + 1.5) < $end){
					$this->transactionTooLong();
				}
                return $ret;
            } catch (TransactionException $ex) {
                $this->transactionRollback();
            } finally {
                $this->transactionRollback();
            }

            //Delay for retry 2 onwards to try and reduce thrashing. 100ms per retry
            for($f = $i - 1; $f > 0; --$f){
                usleep(100000);
            }
        }

        // $i == $retries
        throw new TransactionException("Maximum of ".$retries.' exceeded',0,$ex);
	}
}