<?php
namespace Radical\Database\Model;


class ORMTransaction
{
	static function transaction($function, ...$updating){
		foreach($updating as $k=>$v){
			if($v instanceof Table){
				$updating[$k] = $v->refreshTableData(true);
			}
		}

		$instance = \Radical\DB::getInstance();
		return $instance->transaction(function() use($args, $function){
			return $function(...$updating);
		});
	}
}