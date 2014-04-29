<?php
namespace Radical\Database\ORM;

use Radical\Database\Model\TableReference;
use Radical\Database\Model\TableReferenceInstance;
use Radical\Database\SQL\Parse\CreateTable;

class Model extends ModelData {	
	function __construct(TableReferenceInstance $table){
		$this->table = $table;
		$this->tableInfo = $table->Info();
		$structure = CreateTable::fromTable($table);
		$this->engine = $structure->engine;
		
		//Work out which fields are IDs
		if(isset($structure->indexes['PRIMARY'])){
			$this->id = $structure->indexes['PRIMARY']->getKeys();
		}
		
		//Build mapping translation array
		$this->mappings = $this->getMappings($structure)->translationArray();
		
		//This is the auto increment field, if it exists
		foreach($this->id as $col){
			if($structure[$col]->hasAttribute('AUTO_INCREMENT')){
				//Store the auto increment field in ORM format
				$this->autoIncrement = $this->mappings[$col];
				$this->autoIncrementField = $col;

				//There can only be one AUTO_INCREMENT field per table (also it must be in the PKey)
				break;
			}
		}
		
		//build relation array
		if($this->engine == 'innodb'){
			foreach($structure->relations as $r){
                $reference = $r->getReference();
				$this->relations[$r->getField()] = $reference;
                //$mapper = new Mappings($reference->getTableReference()->info(),CreateTable::fromTable($reference->getTable()));
                //$this->relationReverseMappings[$r->getField()] = $mapper->translateToObjective($reference->getColumn());
			}
		}elseif($this->engine == 'myisam'){
			$this->relations = MyIsam::fieldReferences($structure,$this->table);
		}else{
			throw new \Exception('Unknown database engine type: '.$this->engine);
		}
		
		//Work out reverse references
		$tableName = $this->tableInfo['name'];
		foreach(TableReference::getAll() as $ref){
			if($ref->exists()){
				$rStruct = CreateTable::fromTable($ref->getTable());
				foreach($rStruct->relations as $relation){
					$reference = $relation->getReference();
					$rTable = $reference->getTable();
					
					if($rTable == $tableName){
						$this->references[] = array('from_table'=>$ref,'from_field'=>$relation->getField(),'to_field'=>$reference->getColumn());
					}
				}
			}
		}

		//Dnamic Typing data
		$this->dynamicTyping = new DynamicTyping\Instance($table);
		$this->dynamicTyping = $this->dynamicTyping->map;

		//Validation
		$this->validation = new Validation($structure,$this->dynamicTyping);
		
		parent::__construct($this->mappings);
		
		//Store into cache
		Cache::Set($table,$this);
	}
	
	/**
	 * Make an instance of the parent class. Usually this
	 * is used for storage.
	 * 
	 * @return \Model\Database\ORM\ModelData
	 */
	function toModelData(){
		$r = new ModelData($this->mappings);
		foreach($this as $k=>$v)
			$r->$k = $v;
		return $r;
	}
}