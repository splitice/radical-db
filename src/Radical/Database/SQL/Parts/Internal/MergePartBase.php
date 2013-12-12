<?php
namespace Radical\Database\SQL\Parts\Internal;

use Radical\Database\SQL\Internal\MergeBase;
use Radical\Database\IToSQL;

abstract class MergePartBase extends MergeBase implements IToSQL {
	function __toString(){
		return $this->toSQL();
	}
}