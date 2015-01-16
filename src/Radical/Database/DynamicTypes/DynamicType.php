<?php
namespace Radical\Database\DynamicTypes;

use Radical\Exceptions\ValidationException;

abstract class DynamicType {
	function doValidate($value, $field){
		if(!method_exists($this, 'validate'))
			return false;
		
		if(!$this->validate($value)) throw new ValidationException($field);
	}
}