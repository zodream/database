<?php
declare(strict_types=1);
namespace Zodream\Database;


use Zodream\Database\Contracts\BuilderGrammar;
use Zodream\Database\Contracts\Engine;
use Zodream\Database\Contracts\Information;
use Zodream\Database\Contracts\SchemaGrammar;
use Zodream\Database\Engine\BaseEngine;
use Zodream\Database\Events\QueryExecuted;
use Zodream\Database\Query\Builder;
use Zodream\Infrastructure\Contracts\Database;

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

    protected static bool $enableLog = false;

    protected static array $logs = [];

    public static function enableQueryLog() {
        self::$logs = [];
        self::$enableLog = true;
    }

    public static function queryLogs(): array {
        self::$enableLog = false;
        return self::$logs;
    }

    /**
     * @param string $sql
     * @param array $bindings
     * @param float $time
     * @throws \Exception
     */
    public static function addQueryLog(string $sql, array $bindings = [], float $time = 0) {
        event(new QueryExecuted($sql, $bindings, $time, static::db()->getCurrentName()));
        if (self::$enableLog) {
            self::$logs[] = compact('sql', 'bindings', 'time');
        }
    }

    /**
     *
     * @param $table
     * @param string $connection
     * @return Builder
     */
    public static function table($table, string $connection = '') {
        return (new Builder())->from(Utils::formatName($table));
    }

    public static function __callStatic($name, $arguments) {
        return call_user_func_array([static::db(), $name], $arguments);
    }

    public static function db(): Database {
        return app('db');
    }

    public static function engine(): Engine {
        return static::db()->engine();
    }

    public static function grammar(): BuilderGrammar {
        return static::engine()->grammar();
    }

    public static function schemaGrammar(): SchemaGrammar {
        return static::engine()->schemaGrammar();
    }

    public static function information(): Information {
        return static::engine()->information();
    }
}