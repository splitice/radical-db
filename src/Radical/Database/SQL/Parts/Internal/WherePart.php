<?php
namespace Radical\Database\SQL\Parts\Internal;

use Radical\Database\SQL\Parts\Expression\Comparison;
use Radical\Database\SQL\Parts\Expression\TableExpression;

abstract class WherePart extends PartBase {
	const SEPPERATOR = 'AND';
	private $expr;
	
	function __construct($expr){
		$this->expr = $expr;
	}
	
	function expr(){
		return $this->expr;
	}
	
	function toSQL($first = false){
		$ret = '';
		if(!$first) $ret = ' '.static::SEPPERATOR.' ';
		$ret .= $this->expr;
		return $ret;
	}
	
	static function fromAssign($a,$b,$op = '=',$autoNull = true){
		if(is_array($a)){
			$a = new TableExpression($a[1],$a[0]);
		}
		return new static(new Comparison($a, $b,$op,$autoNull));
	}
}