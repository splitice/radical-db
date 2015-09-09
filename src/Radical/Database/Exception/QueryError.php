<?php
namespace Radical\Database\Exception;
class QueryError extends DatabaseException {
	private $error;

	/**
	 * @return mixed
	 */
	public function getError()
	{
		return $this->error;
	}

	function __construct($sql,$error='Unknown') {
		parent::__construct ( 'Error executing "'.substr($sql,0,75).'", Error: '.$error );
		$this->error = $error;
	}
}