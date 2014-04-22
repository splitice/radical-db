<?php
namespace Radical\Database\SQL\Parts\Internal;

use Radical\Basic\Arr\Object\CollectionObject;
use Radical\Database\IToSQL;

abstract class ArrayPartBase extends CollectionObject implements IToSQL {
    /**
     * @param null|array $data
     */
    function __construct($data = null){
		parent::__construct();
		if($data !== null) $this->_Set(null,$data);
	}
	function __toString(){
		return $this->toSQL();
	}
}