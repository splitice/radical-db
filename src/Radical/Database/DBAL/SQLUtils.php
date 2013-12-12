<?php
namespace Radical\Database\DBAL;

abstract class SQLUtils {
	abstract static function e($str);
	abstract static function getInstance(Adapter\IConnection $connection = null);
		
	static function oField($col,$fields){
		$ret = 'FIELD('.$col;
		foreach($fields as $f){
			$ret .= ','.static::E($f);
		}
		$ret .= ')';
		return $ret;
	}
	
	static function whereAND($data){
		if(is_string($data)){
			return $data;
		}
		$ret = array();
		foreach($data as $k=>$v){
			$ret[] = $k.'='.static::E($v);
		}
		return implode(' AND ',$ret);
	}
	
	static function whereOR($data){
		if(is_string($data)){
			return $data;
		}
		$ret = array();
		$key_itterate = false;
		$keys = array_keys($data);
		foreach($data as $v){
			if(is_array($v)){
				$key_itterate = $v;
				break;
			}
		}
		if(!$key_itterate){
			return self::whereAND($data);
		}
		foreach($key_itterate as $k=>$v){
			$rdata = array();
			foreach($keys as $ak){
				if(is_array($data[$ak])){
					$rdata[$ak] = $data[$ak][$k];
				}else{
					$rdata[$ak] = $data[$ak];
				}
			}
			$ret[] = self::whereAND($rdata);
		}
		return implode(' OR ',$ret);
	}
	
	/**
	 * Generate an escaped comma sepperated string from a data array
	 * suitable for INSERTs
	 * @param array $array
	 * @return string
	 */
	static function A(array $array) {
		$db = static::getInstance();
		foreach ( $array as $k => $v ) {
			$array [$k] = $db->Escape ( $v );
		}
		return implode ( ',', $array );
	}
	
	static function selectFields($fields){
		$ret = array();
		foreach($fields as $k=>$f){
			if(!is_numeric($k)){
				$f .= ' AS `'.$k.'`';
			}
			$ret[] = $f;
		}
		return implode(',',$ret);
	}
}