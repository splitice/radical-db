<?php
namespace Radical\Database\Model\Table;


use Radical\Database\Model\Table;

class TableHelper
{
	/**
	 * @param TableSet|Table[] $arr
	 * @param callable $func
	 */
	public static function has($arr, $func){
		foreach($arr as $a){
			if($func($a)){
				return true;
			}
		}
		return false;
	}

	/**
	 * @param TableSet|Table[] $arr
	 * @param string|Table $id
	 * @return bool
	 */
	public static function hasId($arr, $id){
		if($id instanceof Table){
			$id = $id->getId();
		}
		return self::has($arr, function(Table $t) use($id) { return $t->getId() == $id; });
	}

	/**
	 * @param TableSet}Table[] $arr
	 * @return string[]
	 */
	public static function idMap($arr){
		$ret = array();
		foreach($arr as $a){
			$ret[$a->getId()] = $a;
		}
		return $ret;
	}

	/**
	 * @param TableSet}Table[] $arr
	 * @return string[]
	 */
	public static function ids($arr){
		return array_keys(self::idMap($arr));
	}

	/**
	 * @param TableSet|Table[]|mixed $arr
	 */
	public static function isCollection($arr){
		if($arr instanceof TableSet){
			return true;
		}
		if(is_array($arr)){
			$first = array_shift($arr);
			return $first instanceof Table;
		}
		return false;
	}
}