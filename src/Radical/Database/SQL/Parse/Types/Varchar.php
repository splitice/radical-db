<?php
namespace Radical\Database\SQL\Parse\Types;
use Radical\Basic\Validation\IValidator;

class Varchar extends ZZUnknown implements IValidator {
	const TYPE = 'varchar';
	
	function validate($value){
		return (strlen($value) <= $this->size) || $this->_Validate($value);
	}
}