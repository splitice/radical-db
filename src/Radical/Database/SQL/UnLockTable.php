<?php
namespace Radical\Database\SQL;


class UnLockTable extends Internal\StatementBase {
	function toSQL(){
		return 'UNLOCK TABLES';
	}
}