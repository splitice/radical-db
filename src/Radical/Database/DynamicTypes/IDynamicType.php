<?php
namespace Radical\Database\DynamicTypes;

use Radical\Database\IToSQL;
use Radical\Database\Model\ITable;

interface IDynamicType extends IToSQL {
	public function setValue($value);
	function __toString();
	static function fromDatabaseModel($value,array $extra,ITable $model);
	static function fromUserModel($value,array $extra,ITable $model);
}