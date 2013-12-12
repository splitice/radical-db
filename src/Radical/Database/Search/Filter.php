<?php
namespace Radical\Database\Search;

use Radical\Database\Model\Table\TableSet;

class Filter {
	static function Apply(TableSet $result, Search $search){
		$results = $search->Execute();
	}
}