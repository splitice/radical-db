<?php
namespace Radical\Database\Model\Table;

use Radical\Cache\Object\WeakRef;
use Radical\Database\Model\Table;

class TableCache {
	const MAX_ENTRIES = 256;
	private static $i = 0;
	static $cache;
	
	private static function init(){
		if(!self::$cache){
			self::$cache = new WeakRef();
		}
	}
	private static function _Add($key,$value){
		self::init();
		self::$cache->Set($key,$value);
	}
	static function Add($object){
		if($object instanceof TableSet){
			self::_Add($object->sql, $object);
		}elseif($object instanceof Table){
			self::_Add($object->getIdKey(), $object);
		}else{
			throw new \Exception('Couldnt add the object to TableCache, object is an instance of '.get_class($object));
		}
		if(++self::$i % self::MAX_ENTRIES == 0){
			self::$cache->gc(true);
		}
		return $object;
	}
	static function get($key){
		self::init();
		return self::$cache->Get($key);
	}
}