<?php
namespace Radical\Database\SQL\Parts\Expression;

use Radical\Database\DBAL\Instance;
use Radical\Database\IToSQL;
use Radical\Database\SQL\Parts\Internal;

class Between extends Internal\PartBase implements IComparison {
	private $a;
	private $b;
	
	function __construct($a,$b){
		$this->a = $a;
		$this->b = $b;
	}
	function e(Instance $db,$value){
		if(is_object($value)){
			if($value instanceof IToSQL){
				$value = $value->toSQL();
			}
		}
		return $db->Escape($value);
	}
	function toSQL(){
		$db = \Radical\DB::getInstance();
		return ' BETWEEN '.$this->E($db,$this->a).' AND '.$this->E($db,$this->b);
	}
}