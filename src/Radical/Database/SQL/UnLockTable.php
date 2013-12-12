<?php
namespace Radical\Database\SQL;

use Radical\Database\Model\TableReferenceInstance;

class UnLockTable extends Internal\StatementBase {
	function toSQL(){
		return 'UNLOCK TABLES';
	}
}