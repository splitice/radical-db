<?php
namespace Radical\Database\DBAL\Adapter\MySQL;


use Radical\Database\DBAL\Adapter\MySQLConnection;

interface IMysqlConnector
{
	/**
	 * @param MySQLConnection $connection
	 * @return \mysqli
	 */
	function getConnection(MySQLConnection $connection, $inTransaction);
	function onClose(\mysqli $connection);
	function isConnected();
	function getDb();
	function selectDb($db);
	function __toString();
}