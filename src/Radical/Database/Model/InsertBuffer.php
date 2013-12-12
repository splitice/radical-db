<?php
namespace Radical\Database\Model;

use Radical\Database\IToSQL;
class InsertBuffer {
	private $table;
	private $data = array();
	
	function __construct(TableReferenceInstance $table){
		$this->table = $table;
	}
	
	function add(IToSQL $table){
		$this->data[] = $table->toSQL();
		if(count($this->data) > 10000){
			$this->insert();
		}
	}
	
	function insert(){
		if($this->data){
			try {
				$ret = $this->table->insert($this->data)->query();
			}catch(\Exception $ex){
				$this->data = array();
				throw $ex;
			}

			$this->data = array();
			return $ret;
		}
	}
}