<?php
namespace Radical\Database\DynamicTypes;

class Password extends String {
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
}