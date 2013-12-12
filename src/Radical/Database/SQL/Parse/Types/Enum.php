<?php
namespace Radical\Database\SQL\Parse\Types;
use Radical\Basic\Validation\IValidator;

use Web\Form\Element;

class Enum extends Internal\TypeBase implements IValidator {
	const TYPE = 'enum';
	
	function getOptions(){
		$ret = array();
		foreach(explode(',',$this->size) as $v){
			$ret[] = trim($v,' ",\'');
		}
		return $ret;
	}
	
	function getFormElement($name,$value){
		$options = array();
		foreach($this->getOptions() as $o){
			$selected = ($o == $value);
			$options[] = new Element\Select\Option($o,$o,$selected);
		}
		return new Element\SelectBox($name,$options);
	}
	
	function validate($value){
		return in_array($value,$this->getOptions()) || $this->_Validate($value);
	}
}