<?php
namespace Radical\Database\DBAL;
use Radical\Basic\Arr\Object\CollectionObject;

class Row extends CollectionObject {
	function __get($k){
		return $this->Get($k);
	}
}