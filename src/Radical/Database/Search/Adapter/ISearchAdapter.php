<?php
namespace Radical\Database\Search\Adapter;

use Radical\Database\Model\TableReferenceInstance;
use Radical\Database\Model\ITable;
use Radical\Database\SQL\SelectStatement;

interface ISearchAdapter {
	function filter($text, SelectStatement $sql, $table);
	function search($text, TableReferenceInstance $table);
}