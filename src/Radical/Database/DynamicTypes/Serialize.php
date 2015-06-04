<?php
namespace Radical\Database\DynamicTypes;

use Radical\Database\Model\ITable;

class Serialize extends String {
	function setValue($value){
		parent::setValue(serialize($value));
	}
    function setValueSerialized($value){
        parent::setValue($value);
    }
    function getValue(){
        return unserialize($this->value);
    }

    static function fromUserModel($value,array $extra,ITable $model){
        $t = static::fromDatabaseModel($value, $extra, $model);
        $t->setValue($value);
        return $t;
    }
}