<?php
namespace Radical\Database\Model\Table;

use Radical\Database\DBAL\Fetch;
use Radical\Database\Search\Adapter\ISearchAdapter;
use Radical\Database\SQL;
use Radical\Database\SQL\IStatement;
use Radical\Database\SQL\SelectStatement;

class TableSet extends \Radical\Basic\Arr\Object\IncompleteObject {
	/**
	 * @var SQL\SelectStatement
	 */
	public $sql;
	public $tableClass;
	
	function __construct(SQL\SelectStatement $sql,$tableClass){
		$this->sql = $sql;
		$this->tableClass = $tableClass;
	}
	function search($text,ISearchAdapter $adapter){
		$sql = clone $this->sql;
		$table = constant($this->tableClass.'::TABLE');//TODO: Cleanup
		$adapter->Filter($text, $sql, $table);
		return new static($sql,$this->tableClass);
	}
	function filter(IStatement $merge){
		$sql = clone $this->sql;
		$merge->mergeTo($sql);
		return new static($sql,$this->tableClass);
	}
	function delete(){
		$sql = $this->sql->mergeTo(new SQL\DeleteStatement());
		$sql->Execute();
	}
	function update($value){
		$sql = $this->sql->mergeTo(new SQL\UpdateStatement());
		$sql->values($value);
		$sql->Execute();
	}
	private function query(){
		return \Radical\DB::Query($this->sql);
	}
	function yieldData(){
		//This is the second time, lets cache this time
		if($this->data === null && $this->count){
			return new \ArrayIterator($this->getData());
		}

		return $this->_yieldData();
	}
	function _yieldData(){
		if($this->data){
			foreach($this->data as $d){
				yield $d;
			}
			return;
		}

		//Execute
		$res = $this->query();
		$tc = $this->tableClass;

		$count = 0;
		while($row = $res->fetch()){
			$obj = $tc::fromSQL($row);
			$count ++;
			yield $obj;
		}
		$this->count = $count;
	}
	function getData(){
		//Execute
		$res = $this->query();

		//Table'ify
		return $res->FetchCallback(array($this->tableClass,'fromSQL'));
	}
	function reset(){
		$this->data = null;
		$this->count = null;
	}

	/**
	 * @return TableSet|$this
	 */
	function new_clone(){
		$t = clone $this;
		$t->reset();
		return $t;
	}
	public function count(){
		return $this->getCount();
	}
	
	private $count;
	function setSQLCount(SelectStatement $sql){
		$this->count = $sql;
	}
	function getCount(){
		if($this->count !== null){
			if($this->count instanceof SelectStatement){
				$this->count = $this->count->query()->fetch(Fetch::FIRST);
			}
			return $this->count;
		}
		if($this->data){
			return ($this->count = count($this->data));
		}
		
		$this->count = $this->sql->getCount();
			
		return $this->count;
	}
    function __clone(){
        $this->sql = clone $this->sql;
    }
}