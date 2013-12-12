<?php
namespace Radical\Database\SQL\Parts\Expression;

use Radical\Database\SQL\Parts\Internal;

class In extends Internal\FunctionalPartBase implements IComparison {
	const PART_NAME = 'IN';
}