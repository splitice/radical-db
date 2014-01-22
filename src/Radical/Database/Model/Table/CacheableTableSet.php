<?php
namespace Radical\Database\Model\Table;

use Radical\Database\SQL\IStatement;

class CacheableTableSet extends TableSet {
	function __construct(IStatement $sql,$tableClass){
		parent::__construct($sql,$tableClass);
		TableCache::Add($this, $this->sql);
	}
	function getData(){
		//Execute		
		$res = \Radical\DB::Query($this->sql);
		
		//Table'ify
		$tableClass = $this->tableClass;
		return $res->FetchCallback(function($obj) use($tableClass){
			return TableCache::Add($tableClass::fromSQL($obj));
		});
	}
}