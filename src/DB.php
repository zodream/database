<?php
namespace Zodream\Database;


use Zodream\Database\Query\Builder;

class DB {

    protected static $enableLog = false;

    protected static $logs = [];

    public static function enableQueryLog() {
        self::$logs = [];
        self::$enableLog = true;
    }

    public static function queryLogs() {
        self::$enableLog = false;
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
     * @param null $connection
     * @return Builder
     */
    public static function table($table, $connection = null) {
        return (new Builder())->from($table);
    }

    public static function __callStatic($name, $arguments) {
        return call_user_func_array([Command::getInstance(), $name], $arguments);
    }
}