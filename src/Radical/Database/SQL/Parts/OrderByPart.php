<?php
namespace Radical\Database\SQL\Parts;

class OrderByPart extends Internal\PartBase {
	private $expr;
	private $order;
	
	function __construct($expr,$order = 'ASC'){
		$this->expr = $expr;
		$this->order = $order;
	}
	
	protected function expr($set=null){
		if($set === null){
			return $this->expr;
		}
		$this->expr = $set;
		return $this;
	}
	
	protected function order($set=null){
		if($set === null){
			return $this->order;
		}
		
		//Ensure uppercase
		$set = strtoupper($set);
		
		//Validate
		if($set != 'ASC' && $set != 'DESC'){
			throw new \Exception('Invalid sort order');
		}
		
		//Set
		$this->order = $set;
		return $this;
	}
	
	function toSQL(){
		return $this->expr.' '.$this->order;
	}
}