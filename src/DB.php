<?php
declare(strict_types=1);
namespace Zodream\Database;


use Zodream\Database\Contracts\BuilderGrammar;
use Zodream\Database\Contracts\Engine;
use Zodream\Database\Contracts\Information;
use Zodream\Database\Contracts\SchemaGrammar;
use Zodream\Database\Contracts\SqlBuilder;
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

    public static function enableQueryLog(): void {
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
    public static function addQueryLog(string $sql, array $bindings = [], float $time = 0): void {
        event(new QueryExecuted($sql, $bindings, $time, static::db()->getCurrentName()));
        if (self::$enableLog) {
            self::$logs[] = compact('sql', 'bindings', 'time');
        }
    }

    /**
     *
     * @param mixed $table
     * @param string $connection
     * @return Builder
     */
    public static function table(mixed $table, string $connection = ''): SqlBuilder {
        return (new Builder())->from(Utils::wrapTable($table, $connection));
    }

    /**
     * 判断表是否存在
     * @param mixed $table
     * @return bool
     */
    public static function tableExist(mixed $table): bool {
        $res = static::db()->first(static::schemaGrammar()->compileTableExist($table));
        return !empty($res);
    }

    public static function __callStatic($name, $arguments) {
        return call_user_func_array([static::db(), $name], $arguments);
    }

    public static function lock(array|string $tables, string $lockType = ''): void {
        static::db()->execute(static::schemaGrammar()->compileTableLock($tables, $lockType));
    }

    public static function unlock(): void {
        static::db()->execute(static::schemaGrammar()->compileTableUnlock(''));
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