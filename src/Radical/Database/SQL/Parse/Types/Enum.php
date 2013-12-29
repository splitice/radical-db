<?php
namespace Radical\Database\SQL\Parse\Types;
use Radical\Basic\Validation\IValidator;

class Enum extends Internal\TypeBase implements IValidator {
	const TYPE = 'enum';
	
	function getOptions(){
		$ret = array();
		foreach(explode(',',$this->size) as $v){
			$ret[] = trim($v,' ",\'');
		}
		return $ret;
	}
	
	function validate($value){
		return in_array($value,$this->getOptions()) || $this->_Validate($value);
	}
}