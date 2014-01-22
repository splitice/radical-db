<?php
namespace Radical\Database\SQL;

use Radical\Database\Model\TableReferenceInstance;

class ShowCreateTable extends Internal\StatementBase {
	protected $table;

	function __construct($table){
		if($table instanceof TableReferenceInstance){
			$table = $table->getTable();
		}
		$this->table = $table;
	}
	
	function toSQL(){
		return 'SHOW CREATE TABLE `'.$this->table.'`';
	}
}