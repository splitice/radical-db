<?php
namespace Radical\Database\SQL\Parse\Types;
use Radical\Basic\Validation\IValidator;
use Radical\Database\SQL\Parse\Types\Internal\IPHPDoctype;

class Int extends ZZUnknown implements IValidator, IPHPDoctype {
	const TYPE = 'int';
	
	static function is($type = null){
		switch($type){
			case 'int':
			case 'smallint':
			case 'mediumint':
			case 'bigint':
				return true;
		}
		return false;
	}
	
	function validate($value){
		if(is_numeric($value)){
			return ((float)(int)$value === (float)$value) || $this->_Validate($value);
		}
		return $this->_Validate($value);
	}

    function getPhpdocType(){
        return 'numeric';
    }
}