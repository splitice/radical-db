<?php
namespace Radical\Database\SQL\Parts\Internal;

use Radical\Database\IToSQL;

abstract class PartBase implements IToSQL {
	function __toString(){
		return $this->toSQL();
	}
}