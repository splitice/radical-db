<?php
namespace Radical\Database\Model;

use Radical\Database\Model\Table\TableSet;

class WeakCacheableTable extends Table {
    public function getIdKey(){
        return static::_idString($this->getId());
    }

    static function _idString($id){
        if(is_object($id)){
            $id = (array)$id;
        }
        if(is_array($id)) {
            ksort($id);
            $id = implode('|',$id);
        }
        $id .= '|'.static::TABLE;
        return $id;
    }

    /**
     * @param mixed $id
     * @return static
     */
    static function fromId($id, $forUpdate = false){
		//Check Cache
		$cache_string = static::_idString($id);
		$ret = Table\TableCache::Get($cache_string);

		//If is cached
		if($ret){
			return $ret;
		}
		
		$ret = parent::fromId($id);
		if($ret)
			Table\TableCache::Add($ret);
		
		return $ret;
	}


    /**
     * @param string $sql
     * @return Table\CacheableTableSet|Table\TableSet|static[]
     */
    static function getAll($sql = '', $forUpdate = false){
		if(\Radical\Core\Server::isCLI())
			return parent::getAll($sql);
		
		$obj = static::_getAll($sql);
		
		$cached = Table\TableCache::Get($obj);
		if($cached){
			return $cached;
		}else{
			return new Table\CacheableTableSet($obj, get_called_class());
		}
	}
}