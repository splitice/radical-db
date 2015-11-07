<?php
namespace Radical\Database\SQL\Parse\Types;
use Radical\Basic\Validation\IValidator;
use Radical\Database\SQL\Parse\Types\Internal\IPHPDoctype;

class Integer extends ZZUnknown implements Internal\ISQLType, IPHPDoctype {
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

	function getDefault(){
		return parent::getDefault() || strpos($this->extra,'AUTO_INCREMENT') !== false;
	}
	
	function validate($value){
		if(is_numeric($value)){
			return ((float)(int)$value === (float)$value);
		}
		return $this->_Validate($value);
	}

    function getPhpdocType(){
        return 'int';
    }
}