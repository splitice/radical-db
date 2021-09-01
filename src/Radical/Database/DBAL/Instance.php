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

	function __construct(Adapter\IConnection $adapter, ...$args){
		$this->adapter = new $adapter(...$args);
        $this->transactionManager = new TransactionManager($this);
		$this->instanceId = crc32(rand() . microtime(true) . rand());
		$this->hookInit();
	}

	function multiQuery($sql){
		$res = $this->adapter->multiQuery($sql);
		if(!$res) {
			$error = $this->Error();
			if ($this->inTransaction()) {
				if ($errno == 1205 || $errno == 1213 || $errno == 1689) {
					throw new TransactionException($error);
				}
			}

			throw new Exception\QueryError ($sql, $error);
		}
	}

	function close($real = false){
		if($real){
			$this->adapter->close();
		}else {
			\Radical\DB::$connectionPool->Free($this->adapter);
		}
	}
	
	function __call($func,$args){
	    return $this->adapter->$func(...$args);
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
			if(!$is_retry && ($errno == 2006 || $errno == 2013 || ($errno == 1213 && !$this->inTransaction()))){
				$this->reConnect();
				return $this->Q($sql,$timeout,true);
			}

			$error = $this->Error();
			if (!$is_retry) {
				$this->call_filter('error_handler', $error);
				if($error === null){
					return $this->query($sql, $timeout, true);
				}
			}
			if($this->inTransaction()){
				if($errno == 1205 || $errno == 1213 || $errno == 1689) {
					throw new TransactionException($error);
				}
			}

			throw new Exception\QueryError ($sql, $error);
		}

		\Radical\DB::$query_log->addQuery ( $sql ); //add query to log

		if($res === null) {
			return null;
		}

		if ($res === true) { //Not a SELECT, SHOW, DESCRIBE or EXPLAIN
			return new DBAL\Result($this);
		}

		return new DBAL\RowResult($res,$this);
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

	function selectDb($db){
		$this->adapter->selectDb($db);
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
	function update($tbl, $data, $where, $limit = null) {
		$update = new SQL\UpdateStatement($tbl, $data, $where, $limit);
		
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
	function escape($str, $forceString = false) {
		if ($str === null) {
			return 'NULL';
		}
		if(!$forceString && is_numeric($str) && ((int)$str) == $str){
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

	/* Start / End savepoint */
	function savepointStart(){
		if(!$this->transactionManager->inTransaction){
			throw new TransactionException("To create a savepoint you must be in transaction");
		}
		if(!$this->adapter->savepointStart('s'.($this->transactionManager->savepoints++))){
			throw new BeforeTransactionException("Failed to create savepoint, last error: " . $this->adapter->error());
		}
	}
	function savepointRollback(){
		if(!$this->adapter->savepointRollback('s'.--$this->transactionManager->savepoints)){
			throw new TransactionException("Failed to rollback savepoint, last error: " . $this->adapter->error());
		}
		$this->transactionManager->handleOnRollback();
		$this->transactionManager->clearAfterCommitOrRollback();
	}
	function savepointCommit(){
		try {
			$this->transactionManager->handleBeforeCommit();
		}catch(\Exception $ex){
			throw new Exception\BeforeCommitException('An exception occured before commit', 0, $ex);
		}
		if(!$this->adapter->savepointCommit('s'.--$this->transactionManager->savepoints)){
			throw new TransactionException("Failed to commit savepoint, last error: " . $this->adapter->error());
		}
		$this->transactionManager->handleOnCommit();
		$this->transactionManager->clearAfterCommitOrRollback();
	}
	
	/* Start / End transaction */
	function transactionStart(){
		$result = $this->adapter->beginTransaction();
        if(!$result){
            throw new BeforeTransactionException("Transaction BEGIN failed");
        }
		$this->transactionManager->inTransaction = true;
	}
	function transactionCommit(){
		try {
			$this->transactionManager->handleBeforeCommit();
		}catch(\Exception $ex){
			throw new Exception\BeforeCommitException('An exception occured before commit', 0, $ex);
		}
		$result = $this->adapter->commit();
        $this->transactionManager->inTransaction = false;
        if(!$result){
			throw new TransactionException("Transaction COMMIT failed");
		}
        $this->transactionManager->handleOnCommit();
		$this->transactionManager->clearAfterCommitOrRollback();
	}
	function transactionRollback(){
        $result = $this->adapter->rollback();
        $this->transactionManager->inTransaction = false;
        if(!$result){
            throw new TransactionException("Transaction ROLLBACK failed");
        }
        $this->transactionManager->handleOnRollback();
		$this->transactionManager->clearAfterCommitOrRollback();
	}
	function transactionId(){
		return $this->instanceId . '-' . $this->transactionManager->transactionCount;
	}
    function inTransaction(){
        return $this->transactionManager->inTransaction;
    }

    private function setPreviousException(\Exception $e, \Exception $prev) {
        $reflection = new \ReflectionClass($e);
        while( ! $reflection->hasProperty('previous') ) {
            $reflection = $reflection->getParentClass();
        }
        $prop = $reflection->getProperty('previous');
        $prop->setAccessible('true');
        $prop->setValue($e, $prev);
        $prop->setAccessible('false');
        return $e;
    }

	function transaction($method, $retries = 10, $auto_de_nest = false){
        $savepoint = false;
		if($this->inTransaction()) {
			if ($auto_de_nest) {
				return $method();
			}
			$savepoint = true;
		}

        $ex = null;

        for($i=0;$i<$retries;$i++) {
            try {
				if($savepoint){
					$this->savepointStart();
				}else {
					$this->transactionStart();
				}
                $ret = $method();
            }
            catch(BeforeTransactionException $ex) {
                throw $ex;
            }
            catch(TransactionException $ex){
                try {
                    if ($savepoint) {
                        $this->savepointRollback();
                    } else {
                        $this->transactionRollback();
                    }
                } catch(\Exception $inner){
                    $this->setPreviousException($inner, $ex);
                    throw $inner;
                }
                continue;
			}
			catch (\Exception $ex) {
                try {
                    if($savepoint) {
                        $this->savepointRollback();
                    }else{
                        $this->transactionRollback();
                    }
                    throw $ex;
                } catch(\Exception $inner){
                    $this->setPreviousException($inner, $ex);
                    throw $inner;
                }
            }

            try {
                if($savepoint){
                    $this->savepointCommit();
                }else {
                    $this->transactionCommit();
                }
                return $ret;
            }
            catch(Exception\BeforeCommitException $ex){
                if($savepoint) {
                    $this->savepointRollback();
                }else{
                    $this->transactionRollback();
                }
                throw $ex->getPrevious();
            }
            catch(BeforeTransactionException $ex){
                throw $ex;
            }

            //Delay for retry 2 onwards to try and reduce thrashing. 100ms per retry
            for($f = $i - 1; $f > 0; --$f){
                usleep(100000);
            }
        }

        // $i == $retries
        throw new TransactionException("Maximum of ".$retries.' retrues exceeded',0,$ex);
	}
}