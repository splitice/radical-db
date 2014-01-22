<?php
namespace Radical\Database\SQL\Internal;

use Radical\Database\SQL\IStatement;

abstract class StatementBase extends MergeBase implements IStatement {
	function execute(){
		$sql = $this->toSQL();
		return \Radical\DB::Q($sql);
	}
	function query(){
		return $this->Execute();
	}
	function __toString(){
		return $this->toSQL();
	}
}