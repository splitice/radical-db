<?php
namespace Radical\Database\DBAL;

use Radical\Basic\Cast\ICast;

/**
 * Class Result
 * @package Radical\Database\DBAL
 * @property $num_rows
 */
class Result {
	public $affected_rows;
	
	function __construct(Instance $db){
		$this->affected_rows = $db->adapter->affectedRows();
	}
}