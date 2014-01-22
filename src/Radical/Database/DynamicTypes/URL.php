<?php
namespace Radical\Database\DynamicTypes;

class URL extends String implements IDynamicValidate {
	function validate($value){
		$url = \Radical\Utility\Net\URL::fromURL($value);
		if(!$url)
			return false;
		return true;
	}
	function getUrl(){
		return \Radical\Utility\Net\URL::fromURL($this->value);
	}
}