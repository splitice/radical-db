<?php
namespace Radical\Database\Exception;
use Radical\Core\ErrorHandling\Errors\Internal\ErrorException;

abstract class DatabaseException extends ErrorException {
	function __construct($message, $heading = 'Database Error') {
		parent::__construct($message,$heading);
	}
}