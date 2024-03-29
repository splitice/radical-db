<?php
namespace Radical\Database\DynamicTypes;
use Radical\Database\Model\ITable;
use Radical\DB;

class Set extends DynamicType implements IDynamicType,IDynamicValidate {
	protected $value;
	protected $extra;
	protected $keys;
	
	function __construct($value, $extra, $keys){
		$this->keys = $keys;
		$this->extra = $extra;
		$this->setValue($value);
	}
	function has($name){
		if(is_array($name)){
			foreach($name as $n){
				if(!$this->has($n)){
					return false;
				}
			}
			return true;
		}
		return in_array($name, $this->value);
	}
	function validate($value){
		if(!$value)
			return true;
		
		foreach(explode(',',$value) as $v){
			if(!in_array($v, $this->keys)){
				return false;
			}
		}
		return true;
	}
	function getKeys(){
        return $this->keys;
    }
	function getSet(){
		return $this->value;
	}
	
	function set($name, $value){
		$found = array_search($name, $this->value);
		if($value && $found === false){
			$this->value[] = $name;
		}else if(!$value && $found !== false){
			unset($this->value[$found]);
		}
	}
	
	static function fromDatabaseModel($value, array $extra, ITable $model, $field = null){
        if($field === null){
            throw new \Exception("A field must be supplied to instanciate a Set type");
        }
		$data = $model->orm->validation->request_data($model->orm->reverseMappings[$field]);
		$keys = $data->getValues();
		return new static($value,$extra, $keys);
	}
	static function fromUserModel($value, array $extra, ITable $model, $field = null){
		return static::fromDatabaseModel($value, $extra, $model, $field);
	}
	
	function __toString(){
		if(!is_array($this->value)){
			throw new \Exception('Unexpected value format');
		}
		return (string)implode(',',$this->value);
	}
	function toSQL(){
		return DB::e($this->__toString());
	}
	function setValue($value){
		if(is_string($value)){
			if(empty($value)){
				$this->value = array();
			}else{
				$this->value = explode(',', $value);
			}
		}elseif(is_array($value)){
			$this->value = $value;
		}elseif(@count($value) == 0){
            $this->value = array();
        }else{
			throw new \Exception('Unexpected value format got type '.gettype($value));
		}
	}
}