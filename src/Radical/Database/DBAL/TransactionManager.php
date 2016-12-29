<?php
/**
 * Created by PhpStorm.
 * User: splitice
 * Date: 05-06-2015
 * Time: 8:10 AM
 */

namespace Radical\Database\DBAL;


class TransactionManager {
    public $transactionCount = 0;
    public $inTransaction = false;
    private $onRollback = array();
    private $onCommit = array();
    private $beforeCommit = array();
    public $savepoints = 0;
    private $instance;

    function __construct(Instance $instance){
        $this->instance = $instance;
    }

    function registerOnCommit($function, $name = null){
        if(!$this->instance->inTransaction()){
            $function($this->transactionCount);
            return false;
        }
        if($name !== null){
        	$this->onCommit[$name] = $function;
		}else {
			$this->onCommit[] = $function;
		}
        return true;
    }

    function registerBeforeCommit($function, $name = null){
        if(!$this->instance->inTransaction()){
            $function($this->transactionCount);
            return false;
        }
		if($name !== null){
			$this->beforeCommit[$name] = $function;
		}else {
			$this->beforeCommit[] = $function;
		}
        return true;
    }

    function handleBeforeCommit(){
        $toExecute = $this->beforeCommit;
        $this->beforeCommit = array();
        foreach($toExecute as $c){
            $c($this->transactionCount);
        }
    }

    function handleOnCommit(){
        $toExecute = $this->onCommit;
        $this->onCommit = array();
        foreach($toExecute as $c){
            $c($this->transactionCount);
        }
        $this->transactionCount++;
    }

    function registerOnRollback($function, $name = null){
        if(!$this->instance->inTransaction()){
            $function();
            return false;
        }
		if($name !== null){
			$this->onRollback[$name] = $function;
		} else {
			$this->onRollback[] = $function;
		}
        return true;
    }

    function handleOnRollback(){
        $toExecute = $this->onRollback;
        $this->onRollback = array();
        foreach($toExecute as $c){
            $c();
        }
    }

    function clearAfterCommitOrRollback(){
        $this->onRollback = $this->onCommit = $this->beforeCommit = array();
    }
}