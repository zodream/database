<?php
namespace Zodream\Database;

use Zodream\Database\Query\Query;

class DB {
    /**
     *
     * @param $table
     * @return Query
     */
    public static function table($table) {
        return (new Query())->from($table);
    }

    public static function __callStatic($name, $arguments) {
        return call_user_func_array([Command::getInstance(), $name], $arguments);
    }
}