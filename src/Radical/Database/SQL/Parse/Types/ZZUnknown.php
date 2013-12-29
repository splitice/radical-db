<?php
namespace Radical\Database\SQL\Parse\Types;
use Web\Form\Element;
use Radical\Database\SQL\Parse\CreateTable\ColumnReference;

class ZZUnknown extends Internal\TypeBase {
	const MAX_RELATED = 500;
	
	static function is($type = null){
		return true;
	}
	
	function getFormElement($name,$value,ColumnReference $relation = null){
		if($relation){
			$class = $relation->getTableClass();
			if($class){	
				if($class::getAll()->getCount() <= static::MAX_RELATED){
					$options = array();
					foreach($class::getAll() as $o){
						$ov = $o->getSQLField($relation->getColumn());
						$selected = ($ov == $value);
						$n = (string)$o;
						$options[] = new Element\Select\Option($ov,$n,$selected);
					}
					return new Element\SelectBox($name,$options);
				}
			}
		}
		return new Element\TextInput($name,$value);
	}
}