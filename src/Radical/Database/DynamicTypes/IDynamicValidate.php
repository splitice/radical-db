<?php
namespace Radical\Database\DynamicTypes;

use Exceptions\ValidationException;
use Radical\Basic\Validation\IValidator;

interface IDynamicValidate extends IValidator {
	/**
	 * Perform validation.
	 *
	 * @throws ValidationException if $value in invalid
	 * @param mixed $value
	 * @param string $field
	 */
	function doValidate($value,$field);
}