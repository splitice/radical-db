<?php
namespace Radical\Database\SQL\Parse\CreateTable;
use Radical\Database\SQL\Parse\DataType;

class ColumnStatement extends Internal\CreateTableStatementBase {
	function __construct($name,$type,$size,$attributes) {
        $default = null;
        if(preg_match('#DEFAULT (?:(\')([^\']+)\')|(CURRENT_TIMESTAMP|NULL)#', $attributes, $m)){
            if(!isset($m[1])){
				if($m[3] == 'NULL'){
					$default = null;
				}else if($m[3] == 'CURRENT_TIMESTAMP'){
					$default = true;
				}else{
					throw new \Exception('Unknown default type: '.$m[2]);
				}
            }else{
                $default = $m[2];
            }
        }
		$attributes = preg_replace('#DEFAULT (\')([^\']+)\'#','', $attributes);

		$type = DataType::fromSQL($type,$size,$default,$attributes);
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