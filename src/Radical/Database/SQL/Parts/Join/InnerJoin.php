<?php
namespace Radical\Database\SQL\Parts\Join;

use Radical\Database\SQL\Parts\Internal;

class InnerJoin extends Internal\JoinPartBase {
	const JOIN_TYPE = 'INNER';
}