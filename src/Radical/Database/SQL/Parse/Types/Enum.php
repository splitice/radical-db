<?php
namespace Radical\Database\SQL\Parse\Types;
use Radical\Basic\Validation\IValidator;

class Enum extends Internal\TypeBase implements IValidator {
	const TYPE = 'enum';

    static function is($type = null){
        switch($type){
            case self::TYPE:
                return true;
        }
        return false;
    }
	
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