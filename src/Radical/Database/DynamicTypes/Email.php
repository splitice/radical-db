<?php
namespace Radical\Database\DynamicTypes;

class Email extends String implements IDynamicValidate {
	function validate($value){
		$email = \Radical\Utility\Net\eMail::fromAddress($value);
		if(!$email)
			return false;
		return true;
	}
	function getEmail(){
		return \Radical\Utility\Net\eMail::fromAddress($this->value);
	}
}