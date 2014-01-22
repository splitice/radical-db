<?php
namespace Radical\Database\ORM;

use Radical\Database\Model\TableReference;

class ModelReference {
	static function find($field){
		$prefixLen = 0;
		$ref = null;
		foreach(TableReference::getAll() as $table){
			$prefix = $table->getPrefix();
			$cpLen = strlen($prefix);
			if($prefixLen < $cpLen){
				if(substr_compare($field, $prefix, 0, $cpLen) == 0){
					$prefixLen = $cpLen;
					$ref = $table;
				}
			}
		}
		return $ref;
	}
}