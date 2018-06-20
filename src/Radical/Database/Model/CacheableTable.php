<?php
namespace Radical\Database\Model;

use Radical\Database\Model\Table\TableSet;

class CacheableTable extends WeakCacheableTable {
	private $related_cache = array();
	function _related_cache($name, TableSet $o){
		$o->cache(true);
		$this->related_cache[$name] = $o;
		return $o;
	}
	function _related_cache_get($name){
		return isset($this->related_cache[$name])?$this->related_cache[$name]:null;
	}
}