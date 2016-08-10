<?php
namespace Radical\Database\SQL\Parts\Internal;

abstract class FunctionalPartBase extends ArrayPartBase {
	const PART_NAME = '';
	
	function __construct($data){
		if(!is_array($data)){
			throw new \Exception('$data must be an array');
		}
		$this->data = $data;
	}
	
	function toSQL(){
		if(count($this->data) == 0){
			return 'FALSE';
		}
		return static::PART_NAME.'('.\Radical\DB::A($this->data).')';
	}
}