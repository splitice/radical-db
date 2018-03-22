<?php
namespace Radical\Database\Model;

use Radical\Core\CoreInterface;
use Radical\Database\DBAL;
use Radical\Database\DynamicTypes\IDynamicType;
use Radical\Database\DynamicTypes\IDynamicValidate;
use Radical\Database\DynamicTypes\INullable;
use Radical\Database\Exception\DatabaseException;
use Radical\Database\IToSQL;
use Radical\Database\Model\Table\TableCache;
use Radical\Database\Model\Table\TableSet;
use Radical\Database\ORM;
use Radical\Database\SQL;
use Radical\Database\SQL\IMergeStatement;
use Radical\Database\SQL\Parts;
use Radical\Exceptions\ValidationException;
use Splitice\EventTrait\THookable;

abstract class Table implements ITable, \JsonSerializable {
    use THookable;

	const ADAPTER = "MySQL";
	
	/**
	 * @return \Radical\Database\DBAL\Adapter\IConnection
	 */
	private static function _adapter(){
		$adapter = '\\Radical\\Database\\DBAL\\Adapter\\'.static::ADAPTER;
		return \Radical\DB::getConnection($adapter);
	}
	
	private static $_instance;
	private static function _instance(){
		if(self::$_instance !== null)
			return self::$_instance;
		
		$adapter = static::_adapter();
		if($adapter === null) return null;
		self::$_instance = $adapter->toInstance();
		
		return self::$_instance;
	}

    /**
     * @var int|string|array
     */
	protected $_id;

	private $_read_only = false;

	public $orm;
	protected $_store = array();


    /**
     * @return int|string|array
     */
	function _getId(){
		//Check if already done
		if($this->_id !== null){
			return $this->_id;
		}
		
		$orm = $this->orm;
		
		//Build ID Array
		$id = array();
		foreach($orm->id as $key){
			//Use the object mapped name to get the field value
			$value = $this->{$orm->mappings[$key]};
			
			//if is referenced link then resolve to field value
			if(is_object($value) && $orm->relations[$key]){
				$value = $value->getSQLField($key);
			}
			
			//store in id as DBName=>value
			$id[$key] = $value;
		}
		
		//Make string if there is only one
		if(count($id) === 1){
			$id = $id[$key];
		}
	
		//Cache to _id
		$this->_id = $id;
	
		return $id;
	}
    function hasIdentifyingKey(){
        //No private key, composite of all values, todo: check null?
        if(!$this->orm->id){
            return count($this->_store) != 0;
        }

        foreach($this->orm->id as $k=>$v){
            $mapped = $this->orm->mappings[$v];
            if(isset($this->_store[$mapped])){
                if($this->_store[$mapped] !== null){
                    return true;
                }
            }
        }

        return false;
    }
	function getIdentifyingSQL(){
		$id = array();

        //No private key, composite of all values
        if(!$this->orm->id){
			return $this->_store;
		}

        //get the id
		foreach($this->orm->id as $k=>$v){
			$mapped = $this->orm->mappings[$v];
			if(isset($this->_store[$mapped]))
				$id[$v] = $this->_store[$mapped];
		}
		if($id) return $id;
	}
	function getIdentifyingKeys(){
		$keys = $this->orm->id;
		foreach($keys as $k=>$v){
			$keys[$k] = $this->orm->mappings[$v];
		}
		return $keys;
	}

    /**
     * @return static
     */
    function refreshTableData($forUpdate = false){
		return static::fromId(static::getIdentifyingSQL(), $forUpdate);
	}

	private function process_field($field){
		if($field{0} == '*'){
			$field = static::TABLE_PREFIX.substr($field,1);
		}elseif($field{0} == '~'){
			$mapped = substr($field,1);
			if(!isset($this->orm->reverseMappings[$mapped])){
				throw new \Exception('Could not find mapping for: '.$mapped);
			}
			$field = $this->orm->reverseMappings[$mapped];
		}
		return $field;
	}

	function setSQLField($field,$value,$store=false){
		$sql_field = $this->process_field($field);

		//Check can map
		if(!isset($this->orm->mappings[$sql_field])){
			throw new \Exception('SQL field '.$sql_field.' not found');
		}
		
		//Get field name
		$field = $this->orm->mappings[$sql_field];

		//Handle table's
		$flat_value = $value;
		if($flat_value instanceof Table){
			if(!isset($this->orm->relations[$sql_field])){
				throw new \Exception("Invalid relational input to field ". $field);
			}
			$flat_value = $flat_value->getSQLField($this->orm->relations[$sql_field]->getColumn());
		}

		//Validate
		if(!$this->orm->validation->validate($sql_field, $flat_value)){
			throw new ValidationException('Couldnt set '.$field.' to '.$value);
		}
		
		//Set field
		$vRef = &$this->$field;
		if($vRef instanceof IDynamicType){
			$vRef->setValue($value);
		}else{
			$vRef = $value;
		}

		if($store){
		    $this->_store[$sql_field] = $value;
        }
	}
	function getSQLField($field,$object = false) {
		$field = $this->process_field($field);

		//Check can map
		if(!isset($this->orm->mappings[$field])){
			throw new \Exception('SQL field '.$field.' not found');
		}

		//Get field name
		$translated = $this->orm->mappings[$field];
		
		//Get data
		$ret = $this->$translated;
		
		//Want an object?
		if($object && isset($this->orm->relations[$field]) && !is_object($ret)){
			$relation = $this->orm->relations[$field];
			
			$c = $relation->getTableReference();
			if($c){
				$c = $c->getClass();
				$this->$translated = $ret = $c::fromId($ret);
			}
		}
		if(!$object && is_object($ret) && !($ret instanceof IDynamicType)){
			$ret = $ret->_getId();
		}
		return $ret;
	}
	
	protected function _handleResult($in_param){
		$in = $in_param;
		$store = false;
		if(is_object($in)) {
			$in = $in->toArray();
			$store = true;
		}

		foreach($this->orm->mappings as $k=>$v){
			if(isset($in[$k])){
				$this->$v = $in[$k];
				if($store){
					$this->_store[$v] = $in[$k];
				}
			}
		}

		$to_set = array();
		foreach($in as $k=>$v){
		    if(strpos($k, '__')){
		        list($part_key, $part_field) = explode('__', $k, 2);
		        if(!isset($this->$part_key)) continue;
		        if(!isset($to_set[$part_key])) {
                    if (isset($this->orm->reverseMappings[$part_key])) {
                        $key = $this->orm->reverseMappings[$part_key];
                        /** @var SQL\Parse\CreateTable\ColumnReference $relation */
                        $relation = $this->orm->relations[$key];
                        $to_set[$part_key] = $relation->getTableReference()->getNew();
                    }
                }
                /** @var Table $target */
                $target = $to_set[$part_key];
                try {
                    $target->setSQLField($part_field, $v);
                }catch(\Exception $ex){
                    die(var_dump($in));
                }
            }
        }

        foreach($to_set as $k=>$v){
		    if($v instanceof CacheableTable) {
                Table\TableCache::Add($v);
            }
            $this->$k = $v;
        }
	}

	function __construct($in = array(),$prefix = false){
		//Setup object with table specific data
		$table = TableReference::getByTableClass($this);
		$this->orm = $table->getORM();

		//Hook
		$this->hookInit();
		
		//Load data into table
		if($in instanceof DBAL\Row || $prefix){
			$this->_handleResult($in);
		}elseif(is_array($in)){
			foreach($in as $k=>$v){
				$this->$k = $v;
				if(is_object($v)){
                    if($v instanceof Table){
					    $in[$k] = $v->getId();
                    }else{
                        $in[$k] = $v;
                    }
                }
			}
		}else{
			throw new \Exception('Cant create table with this data');
		}
		$this->_dynamicType();

		if($in instanceof DBAL\Row){
			$this->call_action('load_before');
		}
	}

	private function _dynamicTypeField($field,$v,$dynamicTypeValue){
		$dT = $dynamicTypeValue['var'];
		if($v === null){
			if(!CoreInterface::oneof($dT, '\\Radical\\Database\\DynamicTypes\\INullable')){
				return;
			}
		}
		if(!($v instanceof IDynamicType)){
			$this->$field = $dT::fromDatabaseModel($v, $dynamicTypeValue['extra'], $this, $field);
		}
	}
	
	private function _dynamicType(){
		//Construct dynamic types
		foreach($this->orm->dynamicTyping as $field=>$value){
			$this->_dynamicTypeField($field, $this->$field, $value);
		}
	}
	
	/* Possible Implementatation - Most classes will override */
	function toSQL($in = null){
		$ret = array();
		foreach($this->orm->mappings as $k=>$mapped){
			$v = null;
			if(isset($this->$mapped)){
				$v = $this->$mapped;
				if(is_object($v) && isset($this->orm->relations[$k])){
					$v = $v->getSQLField($this->orm->relations[$k]->getColumn());
				}
			}
			$ret[$k] = $v;
		}
		return $ret;
	}

    function isDefault($field){
        if(!isset($this->orm->reverseMappings[$field])){
            throw new \InvalidArgumentException('$field is not valid');
        }
        $mapped =  $this->orm->reverseMappings[$field];
        return $this->orm->validation->is_default($mapped, $this->call_get_member($field,array('id')));
    }
	
	function toExport(){
		$data = $this->toSQL();
		$ret = array();
		foreach($this->orm->mappings as $k=>$v){
			$ret[$v] = $data[$k];
		}
		return $ret;
	}

	public function read_only($value){
	    $this->_read_only = $value;
    }
	
	public function jsonSerialize(){
		return $this->toSQL();
	}
	
	function update(){
	    if($this->_read_only) throw new \InvalidArgumentException("Attempt to update read-only model");
        $this->call_action("update_before");
		$inTransaction = \Radical\DB::inTransaction();
		if($inTransaction){
			\Radical\DB::transactionManager()->registerBeforeCommit(function(){$this->validate('update');});
		}else{
			$this->Validate('update');
		}
		$identifying = $this->getIdentifyingSQL();
		$values = $this->toSQL();
		foreach($identifying as $k=>$v){
			if(isset($values[$k]) && $values[$k] == $v){
				unset($values[$k]);
			}
		}
		foreach($values as $k=>$v){
			$mapped = $this->orm->mappings[$k];
			if(!isset($this->_store[$mapped])){
				if($v === null)
					unset($values[$k]);
			}elseif((string)$v == $this->_store[$mapped]){
				unset($values[$k]);
			}
		}
		
		if(count($values)) {
			$this->call_action("update_before_query");
			try {
				\Radical\DB::Update($this->orm->tableInfo['name'], $values, $identifying);
			}catch(\Exception $ex){
				if($inTransaction){
					$this->Validate('update');
				}
				throw $ex;
			}
		}

        $this->call_action("update_after");

		foreach($values as $k=>$v){
			$mapped = $this->orm->mappings[$k];
			$this->_store[$mapped] = $v;
		}
	}
	
	function delete(){
		$inTransaction = \Radical\DB::inTransaction();
		if($inTransaction){
			\Radical\DB::transactionManager()->registerBeforeCommit(function(){$this->validate('delete');});
		}else{
			$self = $this;
			return \Radical\DB::transaction(function() use($self){
				return $this->delete();
			});
		}
		$this->call_action("delete_before");
		try {
			\Radical\DB::Delete($this->orm->tableInfo['name'], $this->getIdentifyingSQL());
		}catch(\Exception $ex){
			if($inTransaction){
				$this->Validate('update');
			}
			throw $ex;
		}
        $this->call_action("delete_after");
	}
	
	public function __sleep()
	{
		if($this->_store){
			return array('_store');
		}else{
			$keys = get_object_vars($this);
			unset($keys['orm']);
            unset($keys['actions']);
			$keys = array_keys($keys);
			return $keys;
		}
	}
	
	public function __wakeup()
	{
        $this->hookInit();

		//Recreate ORM
		$table = TableReference::getByTableClass($this);
		$this->orm = $table->getORM();
		
		if($this->_store){
			//Re-get data
			$table = $this->RefreshTableData();
			if($table)
				$this->_handleResult($table->toSQL(true));
			//else
				//throw new \Exception("Init Error");
			
			//Initialize dynamic types
			$this->_dynamicType();
		}
	}
	
	function __toString(){
		$id = $this->_getId();
		if(is_array($id)) $id = implode('|',$id);
		return $id;
	}
	protected function call_get_member($actionPart,$a){
		$relations = $this->orm->relations;
		$rm = $this->orm->reverseMappings;
		if(isset($rm[$actionPart])) {
			$dbName = $rm[$actionPart];
			if (isset($relations[$dbName]) && !is_object($this->$actionPart)) {
				$class = $relations[$dbName]->getTableClass();
				if (isset($a[0]) && $a[0] == 'id') {
					$ret = &$this->$actionPart;
					if (is_object($ret)) {
						$ret = $this->_getId();
					}
				} else {
				    if(isset($a[0]) && $a[0] == 'light'){
				        /** @var Table $ret */
				        $ret = new $class(array($class::ID => $this->$actionPart));
				        $ret->read_only(true);
				        return $ret;
                    }
					$withUpdate = $a == 'update' || $a == 'for_update';
					$this->$actionPart = $class::fromId($this->$actionPart, $withUpdate);
				}
			}
		}
		if(isset($a[0]) && $a[0] == 'id' && is_object($this->$actionPart)){
            if($this->$actionPart instanceof Table){
			    $ret = $this->$actionPart->getId();
            }else{
                $ret = (string)$this->$actionPart;
            }
		}else{
			$ret = &$this->$actionPart;
		}
		return $ret;
	}
	
	protected function _related_cache($name, TableSet $o){
		return $o;
	}
	function _related_cache_get($name){
		
	}

    /**
	 * @param $className
	 * @param $a
     * @throws \BadMethodCallException
     * @returns \Radical\Database\Model\Table\TableSet
     */
    protected function call_get_related($className, $a){
		//Cacheable table provides this
		$ret = $this->_related_cache_get($className);
		if($ret !== null){
			return $ret;
		}
		
		$forUpdate = isset($a[0]) && ($a[0] == 'update' || $a[0] == 'for_update');

        $preload = function(TableReferenceInstance $table, TableSet $result) use($a){
            if(isset($a[0]) && is_array($a[0])){
                $result->prejoin($a[0]);
            }
            return $result;
        };
		
		//Get Class
		try{
			//Use schema
			$found = false;
			$where = new Parts\Where();
			$from_table = null;
			foreach($this->orm->references as $ref){
				if($ref['from_table']->getName() == $className){
					$from_table = $ref['from_table'];
                    $field = $this->getSQLField($ref['to_field']);
                    if($field !== null){
						$compare = new Parts\Expression\Comparison($ref['from_field'], $field);
						if($found){
							$compare = new Parts\WhereOR($compare);
						}
						$where[] = $compare;
                    }
					$found = true;
				}
			}
			if($found) {
				if ($where->count() == 0) {
					$where[] = 'FALSE';
				}elseif($where->count() != 1){
					$where = new Parts\Where($where);
				}

				return $preload($ref['from_table'], $this->_related_cache($className, $from_table->getAll($where, $forUpdate)));
			}

			
			//Fallback, not schema related so try a fetch
			$relationship = TableReference::getByTableClass($className);
			if(isset($relationship)){//Is a relationship
				//Fallback to attempting to get
                if($this->hasIdentifyingKey()){
                    $select = $this->getIdentifyingSQL();
                }else{
                    $select = new Parts\Where();
                    $select[] = 'FALSE';
                }
				return $preload($relationship, $this->_related_cache($className,$relationship->getAll($select, $forUpdate)));
			}
		}catch(\Exception $ex){
			throw new \BadMethodCallException('Relationship doesnt exist: unable to relate');
		}
		
		throw new \BadMethodCallException('Relationship doesnt exist: unkown table');
	}
	public function fields(){
		return array_keys($this->orm->reverseMappings);
	}
	protected function call_set_value($actionPart,$value){
        $hookData = array('actionPart' => $actionPart, 'value' => $value);
        $this->call_action('call_set_before', $hookData);
		if(isset($this->orm->reverseMappings[$actionPart])){		
			//Is this key a dynamic type?
			if(isset($this->orm->dynamicTyping[$actionPart])){
				if($value instanceof IDynamicType){//Have we been given a dynamic type?
					$this->$actionPart = $value;
				}elseif(is_object($this->$actionPart) && $this->$actionPart instanceof IDynamicType){//Do we already have the key set as a dynamic type?
					if($value !== null || $this->$actionPart instanceof INullable){//can be set, set value
						$this->$actionPart->setValue($value);
					}else{//Else replace (used for null)
						$this->$actionPart = $value;
					}
				}elseif($value !== null || CoreInterface::oneof($this->orm->dynamicTyping[$actionPart]['var'], 'Radical\\Database\\DynamicTypes\\INullable')){
					$var = $this->orm->dynamicTyping[$actionPart]['var'];
					$this->$actionPart = $var::fromUserModel($value,$this->orm->dynamicTyping[$actionPart]['extra'],$this,$actionPart);
				}else{//else set to null
					$this->$actionPart = null;
				}
			}else{
				$this->$actionPart = $value;
			}
            $this->call_action('call_set_after', $hookData);
			return $this;
		}else{
			throw new \BadMethodCallException('no field exists for set call');
		}
	}
	function __call($m,$a){
		if($m === 'getId'){
			return $this->_getId();
		}
		if(0 === substr_compare($m,'get',0,3)){//if starts with is get*
			//get the action part
			$actionPart = substr($m,3);
			$className = $actionPart;
			$actionPart{0} = strtolower($actionPart{0});
			
			//if we have the action part from the database
			if(isset($this->orm->reverseMappings[$actionPart])){
				return $this->call_get_member($actionPart,$a);
			}elseif($actionPart{strlen($actionPart)-1} == 's'){//Get related objects (foward)
				//Remove the pluralising s from the end
				$className = substr($className,0,-1);
				
				return $this->call_get_related($className, $a);
			}else{
				throw new \Exception('Cant get an array of something that isnt a model - '.get_called_class().'::'.$actionPart);
			}
		}elseif(0 === substr_compare($m,'set',0,3)){
			$actionPart = substr($m,3);
			$actionPart{0} = strtolower($actionPart{0});
			if(count($a) != 0){
				return $this->call_set_value($actionPart, $a[0]);
			}else{
				throw new \BadMethodCallException('set{X}(value) called without argument');
			}
		}
		throw new \BadMethodCallException('Not a valid function: '.$m);
	}
	protected static function _getAll($sql = '', $forUpdate = false){
		$obj = static::_select();
		if(is_array($sql)){
			$obj = static::_fromFields($sql, $forUpdate);
		}elseif($sql instanceof Parts\Where){
			$obj = static::_select()
				->for_update($forUpdate)
				->where($sql);
		}elseif($sql instanceof IToSQL){
			if($sql instanceof IMergeStatement){
				$obj = $sql->mergeTo(static::_select());
			}else{
				$obj = static::_select()->where($sql);
			}
			$obj->for_update($forUpdate);
		}elseif($sql){
			throw new \Exception('Invalid SQL Type');
		}
		
		return $obj;
	}
	
	/* Static Functions */
	/**
	 * This function gets all rows that match a specific query
	 * or all if $sql is left blank.
	 * 
	 * $sql can be an array() of tablecolumns e.g post_id
	 * $sql can be an instance of \Radical\Database\SQL\Parts\Where
	 * $sql can be any class that implements IToSQL including a query built with the query builder
	 * 
	 * ```
	 * foreach(Post::getAll() as $post){
	 * 		echo $post->getId(),'<br />';
	 * }
	 * //or
	 * $posts = Post::getAll(array('category_id'=>1));
	 * echo 'Posts: ',$post->getCount(),'<br />';
	 * foreach($posts as $post){
	 * 		echo $post->getId(),'<br />';
	 * }
	 * //etc
	 * ```
	 * 
	 * @param mixed $sql
	 * @throws \Exception
	 * @return \Radical\Database\Model\Table\TableSet|static[]
	 */
	static function getAll($sql = '', $forUpdate = false){
		$obj = static::_getAll($sql, $forUpdate);
		
		return new Table\TableSet($obj, get_called_class());
	}

	/**
	 * This function gets all rows that have one of the specified IDs
	 *
	 * @param array $ids
	 * @throws \Exception
	 * @return \Radical\Database\Model\Table\TableSet|static[]
	 */
	static function getAllIds($ids, $forUpdate = false){
		if(!count($ids)){
			//Ugly :(
			return static::getAll(new Parts\Expression\Comparison('1','0','=',true,true));
		}
		$orm = ORM\Manager::getModel(TableReference::getByTableClass(get_called_class()));

		if(count($orm->id) == 1){
			$expr = new Parts\Expression\Comparison($orm->id[0], new Parts\Expression\In($ids));
			return static::getAll($expr, $forUpdate);
		}else{
			$ret = array();
			foreach($ids as $id){
				$ret[] = static::fromId($id, $forUpdate);
			}
			return $ret;
		}
	}

	private static function _select(){
		return new SQL\SelectStatement(static::TABLE, static::TABLE.'.*');
	}
	private static function _fromFields(array $fields, $forUpdate){
		$table = TableReference::getByTableClass(get_called_class());
		$orm = ORM\Manager::getModel($table);

		if(!$orm)
			throw new \Exception('Table doesnt exist: '.$table->getTable().' ('.$table->getName().')');
			
		//prefix
		$prefixedFields = array();
		foreach($fields as $k=>$f){
			if($k{0} == '*') {
				$k = static::TABLE_PREFIX.substr($k,1);
			}
			if($f instanceof Table){
				$f = $f->getId();
			}
			$prefixedFields[static::TABLE.'.'.$k] = $f;
		}
		
		//Build SQL
		$where = new Parts\Where($prefixedFields);

		$sql = static::_select()
					->for_update($forUpdate)
					->where($where);

		return $sql;
	}
	
	/**
	 * Gets a row that matches the `$fields` supplied.
	 * Returns null if nothing found.
	 * 
	 * @param array $fields
     * @return static
	 */
	static function fromFields(array $fields, $forUpdate = false){
		$res = \Radical\DB::Query(static::_fromFields($fields, $forUpdate));
		$row = $res->Fetch();
		if($row){
			return static::fromSQL($row);
		}
	}
	
	/**
	 * Gets a row from ID.
	 * If the primary key spans multiple columns then accepts
	 * input only as an of column => value etc `array('key_name1'=> ...)`
	 * Else also accepts input as a scalar value
	 * 
	 * ```
	 * $post = Post::fromId(1);
	 * ```
	 * 
	 * @param mixed $id
	 * @throws \Exception
	 * @return static
	 */
	static function fromId($id, $forUpdate = false){
		$orm = ORM\Manager::getModel(TableReference::getByTableClass(get_called_class()));
		
		//Base SQL
		$sql = static::_select();

        if($forUpdate){
            $sql->for_update($forUpdate);
        }
		
		//Build
		if($id instanceof Parts\Where){
			$sql->where($id);
		}else{
			$idk = $orm->id;
			
			if(is_array($id)){
				if(count($id) != count($idk)){
					throw new \Exception('Number of inputs doesnt match '.count($id).' != '.count($idk));
				}
				if(isset($id[0])){
					$idNew = array();
					foreach($idk as $k=>$v){
						$idk[$k] = $v;
					}
					$id = $idk;
				}
				
				$sql = static::_fromFields($id, $forUpdate);
			}else{
				//Input = String, Needed Array
				if(count($orm->id) > 1){
					throw new \Exception('Needs more than one value for the ID of '.$orm->tableInfo['name']);
				}
				$sql->where(array($idk[0]=>$id));
			}
		}
		
		$res = \Radical\DB::Query($sql);
		$row = $res->Fetch();
		if($row){
			return new static($row);
		}
	}

	function changed($field){
		$theValue = $this->$field;
		if($theValue instanceof IDynamicType){
			$theValue = $theValue->toSQL();
		}
		if(empty($this->_store[$field]) == empty($theValue)){
			return false;
		}
		return $this->_store[$field] != $theValue;
	}

	function validate($operation = null){
		if($operation == 'delete') return;
		foreach($this->orm->dynamicTyping as $k=>$v){
			$v = $this->$k;
			if($v instanceof IDynamicValidate)
				$v->DoValidate((string)$v,$k);
		}
	}
	
	/**
	 * Returns a table made up of $res values.
	 * Usually used in creation/insert.
	 * 
	 * @param mixed $res
	 * @param bool $prefix array is prefixed or not
	 * @return static
	 */
	static function fromSQL($res,$prefix=false){
		return new static($res,$prefix);
	}

    /**
     * @return static
     */
    static function new_empty(){
		return new static(array());
	}
	
	/* (non-PHPdoc)
	 * @see \Radical\Database\Model\ITable::Insert()
	 */
	function insert($ignore = -1){
        if($this->_read_only) throw new \InvalidArgumentException("Attempt to insert read-only model");
        $this->call_action("insert_before", $this);

		$inTransaction = \Radical\DB::inTransaction();
		if($inTransaction){
			\Radical\DB::transactionManager()->registerBeforeCommit(function(){$this->validate('insert');});
		}else{
			$this->Validate('insert');
		}
		
		if($ignore instanceof InsertBuffer){
			$ignore->add($this);
			return;
		}
		
		//Build & Do SQL
		$data = $this->toSQL();
		foreach($data as $k=>$v){
			if($v === null){
				unset($data[$k]);
			}
		}

		//Validate required fields
		$missing = $this->orm->validation->what_is_missing($data);
		if($missing !== null){
			throw new ValidationException('Missing field '.$missing);
		}

		try {
			$id = \Radical\DB::Insert($this->orm->tableInfo['name'], $data, $ignore ? $ignore : null);

		}catch(\Exception $ex){
			if($inTransaction){
				$this->Validate('insert');
			}
			throw $ex;
		}
		if ($id === false) {
			throw new \RuntimeException('Unable to insert into table '.$this->orm->tableInfo['name']);
		}

		foreach($data as $k=>$v){
			if($v !== null && $v instanceof IDynamicType){
				$v = $v->toSQL();
			}
			$this->_store[$this->orm->mappings[$k]] = $v;
		}
		
		//Is an auto incrememnt returned?
		if($id){
			$autoInc = $this->orm->autoIncrement;
			
			//Is auto increment column
			if($autoInc){
				//Set auto increment column
				$this->$autoInc = $id;
				
				//Set store
				$this->_store[$autoInc] = $id;
			}
		}

        $this->call_action("insert_after", $this);
	}
	
	static function exists(){
		return \Radical\DB::tableExists(static::TABLE);
	}

    /**
     * @param $data
     * @param bool $prefix
     * @return static
     */
    static function create($data,$prefix=false,$insert=-1){
		$res = static::fromSQL($data,$prefix);
		$res->Insert($insert);
		return $res;
	}
}