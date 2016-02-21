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
    private $instance;

    function __construct(Instance $instance){
        $this->instance = $instance;
    }

    function registerOnCommit($function){
        if(!$this->instance->inTransaction()){
            $function($this->transactionCount);
            return false;
        }
        $this->onCommit[] = $function;
        return true;
    }

    function handleOnCommit(){
        $toExecute = $this->onCommit;
        $this->onCommit = array();
        foreach($toExecute as $c){
            $c($this->transactionCount);
        }
        $this->transactionCount++;
    }

    function registerOnRollback($function){
        if(!$this->instance->inTransaction()){
            $function();
            return false;
        }
        $this->onRollback[] = $function;
        return true;
    }

    function handleOnRollback(){
        $toExecute = $this->onRollback;
        $this->onRollback = array();
        foreach($toExecute as $c){
            $c();
        }
    }
}