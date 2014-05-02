<?php
namespace Radical\Database\ORM;

use Radical\Basic\Validation\IValidator;

class Validation {
    /**
     * @var IValidator[]
     */
    private $data = array();
	
	function __construct($structure){
		foreach($structure as $field=>$v){
			$type = $v->getType();
			$this->data[$field] = $type;
		}
	}
	
	function validate($field,$value){
		if(!isset($this->data[$field])){
			return true;
		}

        $type = $this->request_data($field);
        if($type instanceof IValidator){
            return $type->Validate($value);
        }

        return true;//No validation
	}
	
	function request_data($field){
		return isset($this->data[$field])?$this->data[$field]:null;
	}

    function is_default($field, $value){
        $data = $this->request_data($field);
        if($data === null){
            throw new \InvalidArgumentException('$field is not a valid database field');
        }
        return $data->getDefault() == $value;
    }
}