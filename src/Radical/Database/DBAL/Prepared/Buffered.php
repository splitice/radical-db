<?php
namespace Radical\Database\DBAL\Prepared;

use Radical\Database\DBAL\Adapter\PreparedStatement;

class Buffered extends Common {
	function __construct($statement,PreparedStatement $p){
		$statement->store_result();
		parent::__construct($statement,$p);
	}
}