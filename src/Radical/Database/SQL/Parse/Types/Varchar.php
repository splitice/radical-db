<?php
namespace Radical\Database\SQL\Parse\Types;
use Radical\Basic\Validation\IValidator;
use Radical\Database\SQL\Parse\Types\Internal\IPHPDoctype;

class Varchar extends ZZUnknown implements Internal\ISQLType, IPHPDoctype {
	const TYPE = 'varchar';

    static function is($type = null){
        switch($type){
            case self::TYPE:
                return true;
        }
        return false;
    }
	
	function validate($value){
		return (strlen($value) <= $this->size) || $this->_Validate($value);
	}

    function getPhpdocType(){
        return 'string';
    }
}