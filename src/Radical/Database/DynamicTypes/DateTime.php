<?php
namespace Radical\Database\DynamicTypes;

use Radical\Database\Model\ITable;
use Radical\DB;

class DateTime extends \Radical\Basic\DateTime\DateTime implements IDynamicType {
	protected $extra;
	
	/**
	 * @param string $value
	 */
	public function setValue($value) {
        if(is_string($value) && !is_numeric($value)){
            $value = strtotime($value);
        }
		$this->timestamp = $value;
	}

	function __construct($value,$extra = null){
		$this->extra = $extra;
		parent::__construct($value);
	}
	function __toString(){
		return (string)$this->toSQLFormat();
	}
    function toSQL(){
        return DB::E($this->toSQLFormat());
    }
	static function fromDatabaseModel($value,array $extra,ITable $model){
		if(is_int($value)){
			return new static($value);
		}
		return parent::fromSQL($value);
	}
	static function fromUserModel($value,array $extra,ITable $model){
		return static::fromDatabaseModel($value, $extra, $model);
	}
}