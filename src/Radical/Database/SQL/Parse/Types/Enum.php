<?php
namespace Radical\Database\SQL\Parse\Types;
use Radical\Basic\Validation\IValidator;

class Enum extends Internal\TypeBase implements Internal\ISQLType {
	const TYPE = 'enum';

    static function is($type = null){
        switch($type){
            case self::TYPE:
                return true;
        }
        return false;
    }
	
	function getOptions(){
		return $this->getValues();
	}
    function getValues(){
        return array_map(function($v){ return trim($v, "'"); }, explode(',', $this->size));
    }
	
	function validate($value){
		return in_array($value,$this->getOptions()) || $this->_Validate($value);
	}
}