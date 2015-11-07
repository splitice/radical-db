<?php
namespace Radical\Database\Model;

use Radical\Core\CoreInterface;
use Radical\Database\ORM;
use Radical\Database\SQL\DeleteStatement;
use Radical\Database\SQL\InsertStatement;
use Radical\Database\SQL\LockTable;
use Radical\Database\SQL\UnLockTable;
use Radical\Database\SQL\UpdateStatement;

class TableReferenceInstance {
	protected $class;
	
	function __construct($class){
		if($class instanceof ITable){
			$class = get_class($class);
		}else{
			if(!class_exists($class)){
				$class2 = \Radical\Core\Libraries::getProjectSpace('DB\\'.$class);
				if(class_exists($class2)){
					$class = $class2;
				}else{
					throw new \Exception($class.' class does not exist');
				}
			}
			if(!CoreInterface::oneof($class,'\\Radical\\Database\\Model\\ITable')){
				throw new \Exception($class.' is not a Database Table object');
			}
		}
		$this->class = $class;
	}
	
	function getTableManagement(){
		//Generate Table Management Class name
		$class = explode('\\',$this->class);
		$count = count($class);
		$class[$count] = $class[$count-1];
		$class[$count-1] = 'Management';
		$class = implode('\\',$class);
		
		//If it exist, return instance of class
		if(class_exists($class)){
			return new $class($this);
		}
		
		//Else return instance of default table manager
		return new Table\TableManagement($this);
	}
	
	function getORM(){
		return ORM\Manager::getModel($this);
	}
	
	/**
	 * @return the $class
	 */
	public function getClass() {
		return $this->class;
	}
	
	function getName(){
		$e = explode('\\',$this->class);
		return array_pop($e);
	}
	
	function __toString(){
		return $this->class;
	}
	
	function info(){
		$class = $this->class;
		$info = array();
		$info['name'] = $class::TABLE;
		$info['prefix'] = $class::TABLE_PREFIX;
		
		return $info;
	}
	
	function getTable(){
		$class = $this->class;
		return $class::TABLE;
	}
	function getPrefix(){
		$class = $this->class;
		return $class::TABLE_PREFIX;
	}
	
	function lock($mode = 'write'){
		$sql = new LockTable($this, $mode);
		$sql->Execute();
	}
	function unlock(){
		$sql = new UnLockTable();
		$sql->Execute();
	}
	function exists(){
		$sql = 'show tables like '.\Radical\DB::E($this->getTable());
		$res = \Radical\DB::Q($sql);
		if($res->Fetch()) {
			return true;
		}
		return false;
	}

	/**
	 * @return Table
	 */
	function getNew(){
		$class = $this->getClass();
		return new $class;
	}

	/**
	 * @return \Radical\Database\SQL\SelectStatement
	 */
	function select($fields = '*',$type = ''){
		$class = '\\Radical\\Database\\SQL\\'.$type.'SelectStatement';
		return new $class($this->getTable(),$fields);
	}
	
	function update($values = array(),$where = array()){
		return new UpdateStatement($this->getTable(),$values,$where);
	}
	function insert($values = array(), $ignore = false){
		return new InsertStatement($this->getTable(),$values,$ignore);
	}
	function delete($where = array()){
		return new DeleteStatement($this->getTable(),$where);
	}
	
	function __call($method,$arguments){
		return call_user_func_array(array($this->class,$method), $arguments);
	}
}