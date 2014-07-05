<?php
namespace Radical\Database\DynamicTypes;

use Radical\DB;

class Currency extends Decimal {
	function __toString(){
		return number_format($this->value,2);
	}
    function toSQL(){
        return DB::e($this->value);
    }
}