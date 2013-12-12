<?php
namespace Radical\Database\ORM;

use Radical\Basic\Validation\IValidator;

class Validation {
	private $data = array();
	
	function __construct($structure){
		foreach($structure as $field=>$v){
			$type = $v->getType();
			if($type instanceof IValidator){
				$this->data[$field] = $type;
			}
		}
	}
	
	function validate($field,$value){
		if(!isset($this->data[$field])){
			return true;//No validation
		}
		return $this->data[$field]->Validate($value);
	}
	
	function request_data($field){
		return isset($this->data[$field])?$this->data[$field]:null;
	}
}