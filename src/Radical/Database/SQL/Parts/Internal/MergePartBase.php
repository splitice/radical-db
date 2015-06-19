<?php
namespace Radical\Database\SQL\Parts\Internal;

use Radical\Database\IToSQL;
use Radical\Database\SQL\Internal\MergeBase;

abstract class MergePartBase extends MergeBase implements IToSQL {
	function __toString(){
		return $this->toSQL();
	}
}