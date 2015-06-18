<?php
/**
 * Created by PhpStorm.
 * User: splitice
 * Date: 05-06-2015
 * Time: 8:10 AM
 */

namespace Radical\Database\DBAL;


class TransactionManager {
    public $inTransaction = false;
    private $onRollback = array();
    private $onCommit = array();
    private $instance;

    function __construct(Instance $instance){
        $this->instance = $instance;
    }

    function registerOnCommit($function){
        if(!$this->instance->inTransaction()){
            $function();
            return false;
        }
        $this->onCommit[] = $function;
        return true;
    }

    function handleOnCommit(){
        $toExecute = $this->onCommit;
        $this->onCommit = array();
        foreach($toExecute as $c){
            $c();
        }
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