<?php
namespace Zodream\Database;

use Zodream\Database\Query\Query;

class DB {

    protected static $enableLog = false;

    protected static $logs = [];

    public static function enableQueryLog() {
        self::$logs = [];
        self::$enableLog = true;
    }

    public static function queryLogs() {
        return self::$logs;
    }

    public static function addQueryLog($sql, $bindings = []) {
        if (self::$enableLog) {
            self::$logs[] = [$sql, $bindings];
        }
    }

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