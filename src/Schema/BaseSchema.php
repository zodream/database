<?php
namespace Zodream\Database\Schema;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/6/25
 * Time: 9:38
 */
use Zodream\Database\Command;

abstract class BaseSchema {

    /**
     * @var Command
     */
    private $_command;

    /**
     * @return Command
     */
    protected function command() {
        if (!$this->_command instanceof Command) {
            $this->_command = Command::getInstance();
        }
        return $this->_command;
    }

    public function addPrefix($table) {
        return $this->command()->addPrefix($table);
    }

    /**
     * @return string
     */
    abstract public function getSql();

    public function __toString() {
        return $this->getSql();
    }

    public function getError() {
        return $this->command()->getError();
    }
}