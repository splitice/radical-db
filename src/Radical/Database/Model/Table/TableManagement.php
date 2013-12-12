<?php
namespace Radical\Database\Model\Table;
use Radical\Database\Model\TableReferenceInstance;
use Radical\Database\SQL;
use Radical\Database\DBAL\Fetch;

class TableManagement {
	public $SHOW_ADMIN = true;
	protected $table;
	function __construct(TableReferenceInstance $table){
		$this->table = $table;
	}
	
	private $createTable;
	private function getCreateTable(){
		if($this->createTable){
			return $this->createTable;
		}
		$res = new SQL\ShowCreateTable($this->table);
		$createTable = $res->Execute()->Fetch(Fetch::NUM);
		$createTable = $createTable[1];
		
		$this->createTable = new SQL\Parse\CreateTable($createTable);
		
		return $this->createTable;
	}
	function getRelations(){
		$ct = $this->getCreateTable();
		return $ct->relations->toArray();
	}
	function getColumns(){
		$ct = $this->getCreateTable();
		return $ct->toArray();
	}
	
	function getWhere(){
		if(isset($this->where)) return $this->where;
	}
	
	/**
	 * @return the $table
	 */
	public function getTable() {
		return $this->table;
	}

}