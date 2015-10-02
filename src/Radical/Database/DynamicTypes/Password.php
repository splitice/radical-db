<?php
namespace Radical\Database\DynamicTypes;

use Radical\Database\Model\ITable;

class Password extends StringType {
	function getAlgo(){
		$algo = 'Raw';
		if($this->extra){
			$algo = $this->extra[0];
		}
		return '\\Radical\\Basic\\Cryptography\\'.$algo;
	}
	function compare($with){
		$class = $this->getAlgo();
		return $class::Compare($with,$this->value);
	}
	function setValue($value){
		$class = $this->getAlgo();
		parent::setValue($class::Hash($value));
	}

    static function fromUserModel($value,array $extra,ITable $model){
        $t = static::fromDatabaseModel($value, $extra, $model);
        $t->setValue($value);
        return $t;
    }
}