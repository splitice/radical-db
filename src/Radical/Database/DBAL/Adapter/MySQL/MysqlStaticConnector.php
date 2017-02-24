<?php
namespace Radical\Database\DBAL\Adapter\MySQL;


use Radical\Database\DBAL\Adapter\MySQLConnection;
use Radical\Database\Exception\ConnectionException;

class MysqlStaticConnector implements IMysqlConnector
{
	/**
	 * @var \mysqli
	 */
	private $mysqli;

	private $host;
	private $user;
	private $pass;
	private $db;
	private $port;
	private $compression;

	function __construct($host, $user, $pass, $db = null, $port = 3306, $compression=true)
	{
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->db = $db;
		$this->port = $port;
		$this->compression = $compression;
	}

	function getDb()
	{
		return $this->db;
	}

	function selectDb($db)
	{
		$this->db = $db;
		return $this->mysqli->select_db($db);
	}

	/**
	 * is the MySQL server connected?
	 * @return boolean
	 */
	function isConnected() {
		if(php_sapi_name() == 'fpm-fcgi') return $this->mysqli != null;//Web requests are short

		return ($this->mysqli && @$this->mysqli->ping());
	}

	function getConnection(MySQLConnection $connection, $inTransaction)
	{
		if($this->isConnected()){
			return $this->mysqli;
		}

		$this->mysqli = mysqli_init();

		$this->mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);

		//Connect - With compression
		$connection_status = mysqli_real_connect ( $this->mysqli, $this->host,
			$this->user, $this->pass, $this->db, $this->port,
			null, $this->compression?MYSQLI_CLIENT_COMPRESS:0 );

		if (! $connection_status) {
			$this->mysqli = null;
			throw new ConnectionException ( $connection->__toString(), $connection->error() );
		}

		return $this->mysqli;
	}

	function __toString()
	{
		return $this->user . '@' . $this->host . ':' . $this->port . ($this->compression?'z':'') . '/' . $this->db;
	}
}