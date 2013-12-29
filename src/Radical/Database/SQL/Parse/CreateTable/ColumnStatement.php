<?php
namespace Radical\Database\SQL\Parse\CreateTable;
use Radical\Database\SQL\Parse\DataType;

class ColumnStatement extends Internal\CreateTableStatementBase {
	protected $default;
	
	function __construct($name,$type,$size,$attributes) {
		$type = DataType::fromSQL($type,$size);
		parent::__construct($name,$type,$attributes);
	}
	
	function getFormElement($type){
		$reference = $this->relation;
		if($reference){
			$reference = $reference->getReference();
		}
		
		$element = $type->getFormElement($this->name,$this->default,$reference);
		if($this->hasAttribute('AUTO_INCREMENT')){
			$element->attributes['placeholder'] = 'NULL';
		}
		return $element;
	}
	
	protected $relation;
	function addRelation($relation){
		$this->relation = $relation;
	}
	
	/**
	 * @return the $relation
	 */
	public function getRelation() {
		return $this->relation;
	}
}