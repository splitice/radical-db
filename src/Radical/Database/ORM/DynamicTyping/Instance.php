<?php
namespace Radical\Database\ORM\DynamicTyping;

use lithium\analysis\Inspector;
use Radical\Database\Model\TableReferenceInstance;

class Instance {
	public $map = array();
	
	function __construct(TableReferenceInstance $table){
		$class = $table->getClass();
		$this->map = $this->getMap($class);
	}
	private function getMap($class){
		$properties = Inspector::properties($class,array('public'=>false));
		
		//parse out fields
		$fields = array();
		/** @var \ReflectionProperty $p */
		foreach($properties as $p){
			if(($p->getModifiers() & \ReflectionProperty::IS_PROTECTED) == \ReflectionProperty::IS_PROTECTED){
				$name = $p->getName();
				if($name{0} != '_'){
					$fields[$name] = Docblock::comment($p->getDocComment());
				}
			}
		}
		
		//Parse out types
		$ret = array();
		foreach($fields as $field => $data){
			if(isset($data['tags']['var'])){
				$ret[$field] = $this->dynamicType($data['tags']['var']);
			}
		}
		
		return $ret;
	}
	private function dynamicType($var){
		$var = explode(' ',$var);
		$extra = array_slice($var,1);
		$var = $var[0];
		
		//Prefix if not given
		if((strpos($var, '\\') === false) || ($var{0} != '\\' && !class_exists($var))){
			$var = '\\Radical\\Database\\DynamicTypes\\'.$var;
		}
		
		return compact('var','extra');
	}
}