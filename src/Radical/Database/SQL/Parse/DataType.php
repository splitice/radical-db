<?php
namespace Radical\Database\SQL\Parse;

class DataType {
	static function getTypes(){
		static $cache = null;
		if($cache === null){
			$cache = \Radical\Core\Libraries::get('Radical\\Database\\SQL\\Parse\\Types\\*');
		}
		return $cache;
	}
	static function fromSQL($type,$size){
		$type = strtolower($type);
		foreach(self::getTypes() as $t){
			if($t::is($type)){
				return new $t($type,$size);
			}
		}
	}
}