<?php
namespace Radical\Database\DBAL\Adapter\MySQL;

use Radical\Database\DBAL\Adapter\MySQL\IMysqlConnector;
use Radical\Database\DBAL\Adapter\MySQLConnection;
use Radical\Database\DBAL\TransactionException;
use Radical\Database\Exception\ConnectionException;

class MysqlMultiPicker implements IMysqlConnector
{
	const MAX_RETRIES = 3;
	private $retries = self::MAX_RETRIES;
	private $errored = false;
	private $ignored = array();
	private $last_used = null;
	private $servers;

	private $last_connected;
	private $last_connected_thread;
    private $last_connected_time;
	private $mysqli;

	private $is_init = false;

	private $db;
	
	function __construct($servers, $db)
	{
		$this->servers = $servers;
		$this->db = $db;
	}

	function pick($in_transaction)
	{
	    // prepare
		if(!$this->is_init) {
			$this->init();
			$this->is_init = true;

		}
		$last_used = $this->last_used;

		if ($this->errored) {
		    // If the last query errored ignore that server going forward
			$this->ignored[json_encode($last_used)] = true;
			$error = $this->errored;
			$this->errored = false;

			if ($in_transaction) {
				throw new TransactionException("Server executing transaction errored, restart transaction", 0, new \RuntimeException($error));
			}
		} else {
			$this->retries = self::MAX_RETRIES;
			if ($last_used) {
				//No longer faulty, clear the ignored table
				if (isset($this->ignored[json_encode($last_used)])) {
					unset($this->ignored[json_encode($last_used)]);
				}

				return $this->last_used = $last_used;
			}
		}

		$count = count($this->servers);

		//Choose smartly
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$hash = crc32($_SERVER['REMOTE_ADDR']);
			$key = $hash % $count;
		} else {
			$key = rand(0, $count - 1);
		}

		//Well, theres no saving us now!
		if (count($this->ignored) >= $count) {
			return $this->last_used = $this->servers[$key];
		}

		//Find a server we want!
		do {
			$server = $this->servers[$key % $count];
			$key++;
		} while (isset($this->ignored[json_encode($server)]));

		return $this->last_used = $server;
	}

	/**
	 * @param $string
	 * @return bool true to retry
	 */
	function handle_error($string)
	{
		if ($string == 'WSREP has not yet prepared node for application use' || $string == 'No route to host' || $string == 'Connection refused') {
			$this->do_error();
		}
		return false;
	}

	function filter_handler(&$err)
	{
		if (self::handle_error($err)) {
			$this->errored = $err;
			$err = null;
		}
	}

	public function init()
	{
		try {
			$ins = \Radical\DB::getInstance();
			if ($ins) {
				$ins->register_filter('error_handler', array($this, 'filter_handler'));
			}
		} catch (\Exception $ex) {
			//Doesnt matter
		}
	}

	/**
	 * is the MySQL server connected?
	 * @return boolean
	 */
	function isConnected() {
		if(PHP_SAPI === 'fpm-fcgi') return $this->mysqli != null;//Web requests are short

		return ($this->mysqli && @$this->mysqli->ping());
	}

	function getDb(){
		return $this->db;
	}

	function selectDb($db)
	{
		$this->db = $db;
		return $this->mysqli->select_db($db);
	}

	function getConnection(MySQLConnection $connection = null, $inTransaction)
	{
		$first = true;
		$continue = true;
		$server = null;
		$mysqli = null;

		$potentials = [];
		do {
		    // Do server pick
			$server = $this->pick($inTransaction);
			if(in_array($server, $potentials)){
			    throw new \RuntimeException('Unable to find suitable server');
            }
            $potentials[] = $server;

			if ($first && $server == $this->last_connected && $this->mysqli) {
			    $now = time();
			    if($this->isConnected()) {//($this->last_connected_thread == getmypid() && ($this->last_connected_time + 2)) >= $now ||
                    $this->last_connected_time = $now;
			        return $this->mysqli;
                }
			}
			$first = false;

			$mysqli = mysqli_init();
			$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);

			//Connect - With compression
			$connection_status = @$mysqli->real_connect($server['host'],
				$server['user'], $server['pass'], $this->db, $server['port'],
				null, empty($server['compression']) ? 0: MYSQLI_CLIENT_COMPRESS );

            $mysqli->set_charset ('utf8');
			if (!$connection_status) {
				if (!$this->do_error()) {
					$continue = false;
				} else {
					$mysqli->close();
                    $mysqli = null;
				}
			}else{
                $res = $mysqli->query('SHOW GLOBAL STATUS LIKE "wsrep_cluster_size"');
                $row = $res->fetch_assoc();
                if($row['Value'] != 1) {
                    $continue = false;
                }

                if(!$continue){
                    $res = $mysqli->query('SHOW GLOBAL STATUS LIKE "wsrep_local_state"');
                    $row = $res->fetch_assoc();
                    if($row['Value'] != 4) {
                        $continue = true;
                        $this->ignored[json_encode($server)] = true;
                    }
                }
			}
		}while($continue);

		if (! $connection_status) {
			$this->mysqli = null;
			if(!$connection) throw new \RuntimeException('Connection Error');
			throw new ConnectionException ( $connection->__toString(), $connection->error() );
		}

		$this->mysqli = $mysqli;
		if($mysqli) {
            $this->last_connected = $server;
            $this->last_connected_time = time();
            $this->last_connected_thread = getmypid();
        }

		return $this->mysqli;
	}

    function onClose(\mysqli $connection){
	    if($connection == $this->mysqli) $this->mysqli = null;
    }

	function __toString()
	{
		return 'cluster';
	}

	private function do_error()
	{
		$this->errored = true;
		return ($this->retries--) > 0;
	}
}