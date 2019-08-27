<?php
namespace Zodream\Database;


use Zodream\Database\Events\QueryExecuted;
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

    /**
     * @param $sql
     * @param array $bindings
     * @param null $time
     * @throws \Exception
     */
    public static function addQueryLog($sql, $bindings = [], $time = null) {
        event(new QueryExecuted($sql, $bindings, $time, Command::getInstance()->getCurrentName()));
        if (self::$enableLog) {
            self::$logs[] = compact('sql', 'bindings', 'time');
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