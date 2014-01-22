<?php
namespace Radical\Database\Exception;
class TimeoutException extends DatabaseException {
	function __construct($sql) {
		parent::__construct ( 'Query "' . $sql . '" timed out.' );
	}
}