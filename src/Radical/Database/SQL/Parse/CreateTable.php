<?php
namespace Radical\Database\SQL\Parse;
use Radical\Basic\Arr\Object\CollectionObject;
use Radical\Database\DBAL;
use Radical\Database\SQL\ShowCreateTable;

class CreateTable extends CollectionObject {
	public $indexes;
	public $relations;
	public $engine = 'innodb';
	
	function __construct($sql){
		if(preg_match('#ENGINE=([a-zA-Z]+)#', $sql, $m)){
			$this->engine = strtolower($m[1]);
		}
		
		//Extract Main part
		preg_match('#^(?:[^\(]+)\((.+)\)([^\)]+)$#s', $sql, $matches);
		$sql = trim($matches[1]);
		
		$this->indexes = new CollectionObject();
		$this->relations = new CollectionObject();
		
		$_matches = array();
		//$sql = preg_replace('#\)([^\)]+)$#', ';$1', $sql);
		preg_match_all('/^\s*(CONSTRAINT|KEY)?\s*`([^`]+)`\s*((?:FOREIGN KEY)|(?:[^\(^\,^$^\s]*))\s*(?:\(\s*([^\)]*)\s*\))?([^\,^\;]*)(?:\,|$)/m', $sql, $_matches, PREG_SET_ORDER);
		foreach($_matches as $m){
			$special = $m[1];
			$attributes = trim($m[5]);
			$type = trim($m[3]);
			if($type == 'FOREIGN KEY') {
				$field = trim($m[4],' `');
				$relation = new CreateTable\ForeignStatement($m[2], $field, $type, $attributes);
				$this->relations->Add($m[2],$relation);
				$this[$field]->addRelation($relation);
			}else if($special == 'KEY'){
				$this->indexes->Add($m[2],new CreateTable\IndexStatement($m[2], $type, $m[4], $attributes));
			}else{
				$this->Add($m[2],new CreateTable\ColumnStatement($m[2], $type, $m[4], $attributes));
			}
		}
		if(preg_match("/^\\s*PRIMARY KEY\\s*\\(\\s*([^\\)]*)\\s*\\)(?: USING BTREE)?(?:\\,|$)/m", $sql, $_matches)){
			$this->indexes->Add('PRIMARY',new CreateTable\PrimaryKey($_matches[1]));
		}
	}
	
	static function fromTable($table){
		$query = new ShowCreateTable($table);
		try {
			$res = \Radical\DB::Q($query->toSQL());
			$data = $res->Fetch(DBAL\Fetch::NUM);
		}catch(\Exception $ex){
			throw new \Exception('Couldnt get Create Table ('.$ex->getMessage().') for '.$table,null,$ex);
		}
		return new static($data[1]);
	}
}