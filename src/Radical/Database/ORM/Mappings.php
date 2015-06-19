<?php
namespace Radical\Database\ORM;

use Radical\Database\SQL\Parse\CreateTable;
use Radical\Database\SQL\Parse\CreateTable\ColumnReference;

class Mappings {
	/**
	 * @var array
	 */
	private $tableInfo;
	/**
	 * @var \Radical\Database\SQL\Parse\CreateTable
	 */
	private $structure;
	
	function __construct(array $tableInfo, CreateTable $structure){
		$this->tableInfo = $tableInfo;
		$this->structure = $structure;
	}
	function stripPrefix($databaseField){
		$tblPrefix = $this->tableInfo['prefix'];
		$pLen = strlen($tblPrefix);
		$nLen = strlen($databaseField);
		if($nLen > $pLen){
			if(0 == substr_compare($databaseField, $tblPrefix, 0, $pLen)){
				return substr($databaseField,$pLen);
			}
		}
		return null;
	}
	private function isReference($databaseField){
		$structure = $this->structure;
		if($structure->engine == 'innodb'){

			$relation = $structure[$databaseField]->getRelation();
			if(!$relation) return false;
			
			$reference = $relation->getReference();
			//Get related mapping object and translate to Objective from that
			if(!$reference){
				throw new \Exception('Couldnt parse relation reference');
			}
			
			return $reference;
		}
		
		$ref = ModelReference::Find($databaseField);
		if($ref->getName() == $this->tableInfo['name']){
			return false;
		}
		return new ColumnReference($ref->getTable(), $databaseField);
	}
	function translateToObjective($databaseField,$prefixed = true){
		//strip the prefix if requested
		if($prefixed){
			$structure = $this->structure;
			
			//Check field exists in schema
			if(!isset($structure[$databaseField])){
				throw new \Exception('Database field doesnt exist in schema');
			}
			
			//Check if is reference
			$translated = $this->stripPrefix($databaseField);
			$relation = $this->isReference($databaseField);
			if($relation){				
				$rTableRef = $relation->getTableReference();
				if(!$rTableRef){
					throw new \Exception('No table for reference');
				}
				
				if(!$translated){				
					$rInfo = $rTableRef->Info();
					if($rInfo['name'] == $this->tableInfo['name']) $translated = $this->stripPrefix($databaseField);
					else $translated = rtrim($rInfo['prefix'],'_');
				}
			}else{
				//Not reference, process normally.
				if($translated === null){//Must be reference
					throw new \Exception('Database field "'.$databaseField.'" must be a reference.');
				}
			}
		}else{
			$translated = $databaseField;
		}
		
		//translate database format (underscores) to objective format (camel case)
		for($i=0,$f=strlen($translated);$i<$f;++$i){
			if($translated{$i} == '_'){
				$translated = substr($translated,0,$i).ucfirst(substr($translated,$i+1));
				--$f;
			}
		}
		
		//return the translated result
		return $translated;
	}
	function translateToDatabase($objectiveField,$prefix=true){
		//translate camelcase to underscore format
		for($i=0,$f = strlen($objectiveField);$i<$f;++$i){
			if(ctype_upper($objectiveField{$i})){
				$objectiveField = substr($objectiveField,0,$i).'_'.strtolower($objectiveField{$i}).substr($objectiveField,$i+1);
				++$f;
				++$i;
			}
		}
		
		//Add prefix if wanted
		if($prefix){
			$objectiveField = $this->tableInfo['prefix'].$objectiveField;
		}
	}
	function translationArray(){
		$ret = array();
		foreach($this->structure as $field=>$column){
			try {
				$ret[$field] = $this->translateToObjective($field);
			}catch(\Exception $ex){
				
			}
		}
		return $ret;
	}
}