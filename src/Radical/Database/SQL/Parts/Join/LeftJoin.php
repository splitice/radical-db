<?php
namespace Radical\Database\SQL\Parts\Join;

use Radical\Database\SQL\Parts\Internal;

class LeftJoin extends Internal\JoinPartBase {
	const JOIN_TYPE = 'LEFT';
}