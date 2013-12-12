<?php
namespace Radical\Database\Model;

use Radical\Database\IToSQL;
interface ITable extends IToSQL {
	function toSQL($in = null);
	function update();
	function delete();
	function getIdentifyingSQL();
	function insert();
	static function exists();
	//static function create($data,$prefix=false);
	
	/* Static Functions */
	static function getAll($sql = '');
	static function fromFields(array $fields);
	static function fromId($id);
	static function fromSQL($res);
}