<?php
namespace Radical\Database\SQL\Parts;

class Where extends Internal\FilterPartBase {
	const PART_NAME = 'WHERE';

	function _Set($k, $v)
	{
		if($v instanceof Where){
			$this->_Set($k, '('.$v->getInner().')');
		}else {
			return parent::_Set($k, $v);
		}
	}
}