<?php
namespace Radical\Database\Model\Table;

use Radical\Database\DBAL\Fetch;
use Radical\Database\Model\TableReference;
use Radical\Database\Search\Adapter\ISearchAdapter;
use Radical\Database\SQL;
use Radical\Database\SQL\IStatement;
use Radical\Database\SQL\SelectStatement;

class TableSet extends \Radical\Basic\Arr\Object\IncompleteObject {
	/**
	 * @var SQL\SelectStatement
	 */
	public $sql;
	public $tableClass;
	/**
	 * @var SQL\SelectStatement
	 */
	public $count;
	private $exists = null;
	private $resultAdjustment;
	
	function __construct(SQL\SelectStatement $sql,$tableClass){
		$this->sql = $sql;
		$this->tableClass = $tableClass;
	}
	function search($text,ISearchAdapter $adapter){
		$sql = clone $this->sql;
		$table = constant($this->tableClass.'::TABLE');//TODO: Cleanup
		$adapter->Filter($text, $sql, $table);
		return new static($sql,$this->tableClass);
	}
	function filter(IStatement $merge){
		$sql = clone $this->sql;
		$merge->mergeTo($sql);
		return new static($sql,$this->tableClass);
	}
	function delete(){
		$sql = $this->sql->mergeTo(new SQL\DeleteStatement());
		$sql->Execute();
	}
	function update($value){
		$sql = $this->sql->mergeTo(new SQL\UpdateStatement());
		$sql->values($value);
		$sql->Execute();
	}
	private function query(){
		return \Radical\DB::Query($this->sql);
	}
	function prejoin($relationships){
        $to_preload = array();
        foreach($relationships as $e){
            if(is_string($e)){
                $table = TableReference::getByTableClass($this->tableClass);
            } elseif (is_array($e)){
                $table = $e[0];
                $e = $e[1];
            }
            $orm = $table->getORM();
            if(isset($orm->reverseMappings[$e])) {
                $dbName = $orm->reverseMappings[$e];
                if (isset($orm->relations[$dbName])){
                    $prefix = '';
                    foreach($to_preload as $k=>$v){
                        if($v['relationship']->getTable() == $table->getTable()){
                            $prefix = $v['prefix'];
                            $prefix .= $k.'__';
                        }
                    }
                    $to_preload[$e] = array('relationship'=>$orm->relations[$dbName], 'table'=>$table, 'prefix'=>$prefix);
                }
            }
        }

        /**
         * @var string $k
         * @var SQL\Parse\CreateTable\ColumnReference $v
         */
        foreach($to_preload as $k=>$_v){
            $v = $_v['relationship'];
            $table = $_v['table'];
            $prefix = $_v['prefix'];
            $orm = $table->getORM();
            $this->sql->left_join($v->getTable(), $v->getTable(),$table->getTable().'.'.$orm->reverseMappings[$k].'='.$v->getTable().'.'.$v->getColumn());
            $target_table = $v->getTableReference();
            foreach($target_table->getORM()->reverseMappings as $dbName){
                $this->sql->fields[] = $v->getTable().'.'.$dbName.' AS '.$prefix.$k.'__'.$dbName;
            }
        }

        return $this;
    }
    function resultProcess($function){
	    if($this->resultAdjustment){
	        $existing = $this->resultAdjustment;
            $this->resultAdjustment = function($r) use($function, $existing){
                $existing($r);
                $function($r);
            };
        }else {
            $this->resultAdjustment = $function;
        }
    }
    protected function _resultProcess($r){
	    if($this->resultAdjustment){
	        $ra = $this->resultAdjustment;
	        try {
                $ra($r);
            }catch(\Exception $ex){
	            //ignore
            }
        }
        return $r;
    }
	function yieldData(){
		//This is the second time, lets cache this time
		if($this->data === null && $this->count){
			return new \ArrayIterator($this->getData());
		}

		return $this->_yieldData();
	}
	function _yieldData(){
		if($this->data !== null){
			foreach($this->data as $d){
				yield $d;
			}
			return;
		}

        /*$obj = $this->sql;
        echo (string)$obj,"\n";
        // && strpos((string)$obj,'server.*') && !strpos((string)$obj,'IN')
        if(strpos((string)$obj,'GROUP BY server.server_id')) {
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            exit;
        }*/

		//Execute
		$res = $this->query();
		$tc = $this->tableClass;

		$count = 0;
		while($row = $res->fetch()){
			$obj = $tc::fromSQL($row);
			$count ++;
			yield $this->_resultProcess($obj);
		}
		$this->count = $count;
	}
	function setData($value){
	    $this->data = $value;
    }
	function getData(){
	    if($this->data) return $this->data;

		//Execute
		$res = $this->query();

		//Table'ify
        $tableClass = $this->tableClass;
		return $res->FetchCallback(function($result) use ($tableClass){
		    return $this->_resultProcess($tableClass::fromSQL($result));
        });
	}
	function preload(){
		if(!$this->data){
			$this->data = $this->getData();
			$this->count = count($this->data);
		}
		if($this->count === null){
			$this->count = count($this->data);
		}
		return $this->count();
	}
	function reset(){
		$this->data = null;
		$this->count = null;
	}

	/**
	 * @return TableSet|$this
	 */
	function new_clone(){
		$t = clone $this;
		$t->reset();
		return $t;
	}
	public function count(){
		return $this->getCount();
	}

	function setSQLCount(SelectStatement $sql){
		$this->count = $sql;
	}
	public function buildCountSql(){
		$this->count = $this->sql->getCountSql();
	}
	private function buildExistsSql(){
        //Check for entry
        $count = clone ($this->sql);
        if($count->for_update){
            $count->for_update = false;
        }
        $count->fields('TRUE');
        $count->remove_limit();
        //$count->remove_joins();
        $count->remove_order_by();
        return $count;
    }
    function exists(){
	    if($this->exists !== null) return $this->exists;
        if(is_numeric($this->count)){
            return $this->count != 0;
        }
        if($this->data){
            return count($this->data) != 0;
        }

        $sql = $this->buildExistsSql();
        $res = \Radical\DB::Query($sql);
        $this->exists = $res->fetch(Fetch::FIRST);
        return $this->exists;
    }
	function getCount(){
		if(is_numeric($this->count)){
			return $this->count;
		}
		if($this->count === null && !$this->data){
			if($group = $this->sql->group()){
				return $this->preload();
			}
			$this->buildCountSql();
		}
		if($this->data){
			return ($this->count = count($this->data));
		}
		if($this->count instanceof SelectStatement){
			$this->count = $this->count->query()->fetch(Fetch::FIRST);
		}
		return $this->count;
	}
    function __clone(){
        $this->sql = clone $this->sql;
    }
}