<?php
namespace Radical\Database\ORM;

use Radical\Core\Server;
use Radical\Database\DBAL\Fetch;
use Radical\Database\Model\TableReferenceInstance;

/**
 * Global cache of table ORMs
 * 
 * ORM details are decently expensive to compute so we dont
 * want to be recreating them for each table row reference (object).
 * 
 * @author SplitIce
 *
 */

class Cache {
	static $data = null;
	static $changed = false;
	
	/**
	 * Resolves the $table parameter to a scalar key
	 * 
	 * @param string|TableReferenceInstance $table
	 * @return string
	 */
	private static function key($table){
		global $BASEPATH;
		if($table instanceof TableReferenceInstance){
			//We only need the class name as our key
			return $table->getClass().$BASEPATH;
		}elseif(!is_string($table)){
			throw new \Exception('Invalid key specified');
		}
		return $table.$BASEPATH;
	}
	
	/**
	 * Get a table specific ORM from the cache
	 * 
	 * @param string|TableReferenceInstance $table the key
	 * @return ModelData
	 */
	static function get($table){
		self::init();
		$table = self::key($table);
		if(isset(self::$data[$table])){
			return self::$data[$table];
		}
	}
	
	/**
	 * Set a table specific ORM from the cache
	 *
	 * @param string|TableReferenceInstance $table the key
	 * @param ModelData $orm the value to store
	 */
	static function set($table, ModelData $orm){
		$table = self::key($table);
		if($orm instanceof Model){
			//Dumb down the class, we dont need to store any additional data
			$orm = $orm->toModelData();
		}
		self::$data[$table] = $orm;
		self::$changed = true;
	}
	
	private static $pool;

	static function init(){
		if(self::$data !== null) {
			return;
		}
		self::$pool = \Radical\Cache\PooledCache::get('radical_orm','Memory');

        if(Server::isProduction()){
        	$key = \Radical\DB::getInstance()->getDb().'_'.crc32(__FILE__);
            self::$data = self::$pool->get($key);
            register_shutdown_function(function() use($key){
                Cache::save($key);
            });
        }

        if(!is_array(self::$data))
            self::$data = array();
	}
	static function save($key = null){
		if(self::$changed){
			if($key === null){
				$key = \Radical\DB::getInstance()->getDb().'_'.crc32(__FILE__);
			}
            if(Server::isProduction()) {
                self::$pool->set($key, self::$data);
            }
		}
	}
}
