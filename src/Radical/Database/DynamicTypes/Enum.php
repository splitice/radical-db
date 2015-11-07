<?php
namespace Radical\Database\DynamicTypes;
use Radical\Database\Model\ITable;
use Radical\DB;

class Enum extends DynamicType implements IDynamicType,IDynamicValidate {
	protected $value;
	protected $extra;
	protected $keys;
	
	function __construct($value, $extra, $keys){
		$this->keys = $keys;
		$this->extra = $extra;
		$this->setValue($value);
	}
	function validate($value){
		if(!in_array($value, $this->keys)){
			return false;
		}
		return true;
	}
	function getKeys(){
        return $this->keys;
    }
	function getValue(){
		return $this->value;
	}
	
	static function fromDatabaseModel($value, array $extra, ITable $model, $field = null){
        if($field === null){
            throw new \Exception("A field must be supplied to instanciate a Set type");
        }
		$data = $model->orm->validation->request_data($model->orm->reverseMappings[$field]);
		die(var_dump($data));
		$keys = $data->getValues();
		return new static($value,$extra, $keys);
	}
	static function fromUserModel($value, array $extra, ITable $model, $field = null){
		return static::fromDatabaseModel($value, $extra, $model, $field);
	}
	
	function __toString(){
		return $this->value;
	}
	function toSQL(){
		return DB::e($this->__toString());
	}
	function setValue($value){
		$this->value = $value;
	}
}