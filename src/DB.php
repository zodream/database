<?php
declare(strict_types=1);
namespace Zodream\Database;


use Zodream\Database\Engine\BaseEngine;
use Zodream\Database\Events\QueryExecuted;
use Zodream\Database\Query\Builder;

/**
 * Class DB
 * @package Zodream\Database
 * @method static bool transaction($args)
 * @method static BaseEngine beginTransaction()
 * @method static array fetch($sql, $parameters = [])
 * @method static int insert($sql, $parameters = [])
 * @method static int insertOrUpdate($columns, $tags, $update, $parameters = array())
 * @method static int insertOrReplace($columns, $tags, $parameters = array())
 * @method static int update($sql, $parameters = [])
 * @method static int delete($sql = null, $parameters = [])
 */
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
        event(new QueryExecuted($sql, $bindings, $time, app('db')->getCurrentName()));
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
        return call_user_func_array([app('db'), $name], $arguments);
    }
}