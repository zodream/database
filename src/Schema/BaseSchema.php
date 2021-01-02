<?php
namespace Zodream\Database\Schema;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/6/25
 * Time: 9:38
 */

use Zodream\Database\Command;
use Zodream\Infrastructure\Contracts\Database;

abstract class BaseSchema {

    /**
     * @return Database|Command
     */
    protected function command() {
        return app('db');
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