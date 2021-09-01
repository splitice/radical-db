<?php
namespace Radical\Database\SQL\Parse\Types\Internal;

use Radical\Basic\Validation\IValidator;

interface ISQLType extends IValidator
{
	function getNull();
	function getDefault();
}