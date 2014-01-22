<?php
namespace Radical\Database\SQL\Parse\Types;

class ZZUnknown extends Internal\TypeBase {
	const MAX_RELATED = 500;
	
	static function is($type = null){
		return true;
	}
}