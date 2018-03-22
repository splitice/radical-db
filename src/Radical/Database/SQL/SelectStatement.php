<?php
namespace Radical\Database\SQL;

use Radical\Basic\Cast;
use Radical\Basic\StringHelper\Number;
use Radical\Database\DBAL;
use Radical\Database\SQL\Parts\From;

/*
http://dev.mysql.com/doc/refman/5.5/en/select.html

SELECT
    [ALL | DISTINCT | DISTINCTROW ]
      [HIGH_PRIORITY]
      [STRAIGHT_JOIN]
      [SQL_SMALL_RESULT] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT]
      [SQL_CACHE | SQL_NO_CACHE] [SQL_CALC_FOUND_ROWS]
    select_expr [, select_expr ...]
    [FROM table_references
    [WHERE where_condition]
    [GROUP BY {col_name | expr | position}
      [ASC | DESC], ... [WITH ROLLUP]]
    [HAVING where_condition]
    [ORDER BY {col_name | expr | position}
      [ASC | DESC], ...]
    [LIMIT {[offset,] row_count | row_count OFFSET offset}]
    [PROCEDURE procedure_name(argument_list)]
    [INTO OUTFILE 'file_name'
        [CHARACTER SET charset_name]
        export_options
      | INTO DUMPFILE 'file_name'
      | INTO var_name [, var_name]]
    [FOR UPDATE | LOCK IN SHARE MODE]]
 */


class SelectStatement extends Internal\StatementBase {
	public $fields = array();

    /**
     * @var From
     */
    protected $from;

    protected $for_update;


    /**
     * @param null $table
     * @param string|string[] $fields
     */
    function __construct($table = null, $fields = '*'){
		if($table !== null && !is_array($table)){
			$table = array($table);
		}
		$this->from = new From($table);
		
		$this->fields($fields);
	}

    /**
     * @param mixed $fields
     * @return SelectStatement
     */
    function fields($fields = null){
		if($fields === null){
			return $this->fields;
		}else{
			if(is_string($fields)){
				$fields = array($fields);
			}
			$this->fields = $fields;
		}
		return $this;
	}

    /**
     * @param mixed $returned
     * @return SelectStatement
     */
	private function _R($returned){
		//Ensure chaining is to the right object (Encapsulation)
		if($returned === $this->from) return $this;
		return $returned;
	}
	
	function from($table = null,$tablePrefix = null){
		return $this->_R($this->from->table($table,$tablePrefix));
	}
	
	/* Joins */
	function left_join($table, $alias, $on = null){
		return $this->join($table, $alias, $on, 'left');
	}
	function right_join($table, $alias, $on = null){
		return $this->join($table, $alias, $on, 'right');
	}
	function inner_join($table, $alias, $on = null){
		return $this->join($table, $alias, $on, 'inner');
	}
	function join($table, $alias, $on = null, $type = 'left'){
		return $this->_R(call_user_func_array(array($this->from,__FUNCTION__), func_get_args()));
	}
	
	function joins(){
		return $this->_R($this->from->joins());
	}

    /**
     * @param mixed $where
     * @return SelectStatement
     */
    function where($where = null){
		return $this->_R($this->from->where($where));
	}
    /**
     * @param mixed $where
     * @return SelectStatement
     */
	function where_and($where){
		return $this->_R($this->from->where_and($where));
	}
    /**
     * @param mixed $where
     * @return SelectStatement
     */
	function where_or($where){
		return $this->_R($this->from->where_or($where));
	}

    /**
     * @param mixed $where
     * @return SelectStatement
     */
    function having($where = null){
        return $this->_R($this->from->having($where));
    }
    /**
     * @param mixed $where
     * @return SelectStatement
     */
    function having_and($where){
        return $this->_R($this->from->having_and($where));
    }
    /**
     * @param mixed $where
     * @return SelectStatement
     */
    function having_or($where){
        return $this->_R($this->from->having_or($where));
    }

    /**
     * @param mixed $group
     * @return SelectStatement
     */
	function group($group = null){
		return $this->_R($this->from->group($group === null ? null : func_get_args()));
	}
    /**
     * @param mixed $group
     * @return SelectStatement
     */
	function group_by($group){
		return $this->group($group);
	}

	function order_by($order_by,$order = null){
		return $this->_R($this->from->order_by($order_by,$order));
	}
	
	function limit($start = null,$end = null){
		return $this->_R($this->from->limit($start,$end));
	}
	
	function remove_limit(){
		return $this->_R($this->from->remove_limit());
	}
	function remove_joins(){
		return $this->_R($this->from->remove_joins());
	}
	function remove_order_by(){
		return $this->_R($this->from->remove_order_by());
	}

    function for_update($for_update = true){
        $this->for_update = $for_update;
        return $this;
    }
	
	function __clone(){
        if($this->from)
		    $this->from = clone $this->from;
	}

	function getCountSql(){
		//Check for entry
		$count = clone $this;
		if($count->for_update){
			$count->for_update = false;
		}
		$count->fields('COUNT(*)');
		$count->remove_limit();
		//$count->remove_joins();
		$count->remove_order_by();
		return $count;
	}
	
	function getCount(){
		$count = $this->getCountSql();
		$res = \Radical\DB::Query($count);
		return $res->Fetch(DBAL\Fetch::FIRST,new Cast\Integer());
	}
	
	function toSQL(){
		$fields = $this->fields;
		array_walk($fields, function ($value,$key) use(&$fields){
			if(!Number::is($key))
				$fields[$key] = ($value.' AS '.$key);
		});
		$ret = 'SELECT '.implode(', ',$fields).' '.$this->from;
        if($this->for_update){
            $ret .= ' FOR UPDATE';
        }
		return $ret;
	}
}