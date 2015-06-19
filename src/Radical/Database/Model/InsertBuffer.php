<?php
namespace Radical\Database\Model;

use Radical\Database\IToSQL;

class InsertBuffer {
	private $table;
	private $data = array();
	private $buffer_count;
	
	function __construct(TableReferenceInstance $table, $buffer_count = 2000){
		$this->table = $table;
		$this->buffer_count = $buffer_count;
	}
	
	function add(IToSQL $table){
		$this->data[] = $table->toSQL();
		if(count($this->data) > $this->buffer_count){
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