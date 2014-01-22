<?php
namespace Radical\Database\Model;

class TableReference {	
	protected static function getClasses(){
		return \Radical\Core\Libraries::get(\Radical\Core\Libraries::getProjectSpace('DB\\*'));
	}
	
	private static $_name = array();
	
	/**
	 * @param string $tableClass
	 * @return \Model\Database\Model\TableReferenceInstance
	 */
	static function getByTableName($tableName){
		if(!self::$_name){
			foreach(static::getClasses() as $class){
				if(class_exists($class))
					self::$_name[$class::TABLE] = $class;
			}
		}
		if(isset(self::$_name[$tableName])){
			return static::_create(self::$_name[$tableName]);
		}
	}
	private static $ins = array();
	protected static function _create($class){
		//Class Reference ID
		if($class instanceof Table){
			$class = get_class($class);
		}
		
		//Check instance cache
		if(isset(static::$ins[$class])){
			return static::$ins[$class];
		}
		
		//Create table instance
		$c = get_called_class().'Instance';
		$c = new $c($class);
		
		//We have a in memory cache
		static::$ins[$class] = $c;
		
		//Return instance
		return $c;
	}
	static function getAll(){
		$ret = array();
		foreach(static::getClasses() as $class){
			if(class_exists($class))
				$ret[] = static::_create($class);
		}
		return $ret;
	}
	
	/**
	 * @param string $tableClass
	 * @return \Model\Database\Model\TableReferenceInstance
	 */
	static function getByTableClass($tableClass){
		try {
			return static::_create($tableClass);
		}catch(\Exception $ex){
		}
	}
}