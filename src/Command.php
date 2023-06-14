<?php
declare(strict_types=1);
namespace Zodream\Database;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/5/14
 * Time: 9:07
 */

use Zodream\Database\Engine\Pdo;
use Zodream\Helpers\Time;
use Zodream\Database\Contracts\Engine;
use Zodream\Helpers\Str;
use Closure;
use Zodream\Infrastructure\Contracts\Database;

/**
 * Class Command
 * @package Zodream\Database
 */
class Command extends Manager implements Database {

    protected $table;

    protected $prefix;

    protected bool $allowCache = true;

    protected int|bool $cacheLife = 3600;

    protected string $configKey = 'database.connections';

    /**
     * @var Engine[]
     */
    protected array $engines = [];

    protected string $defaultDriver = Pdo::class;

    public function __construct() {
        parent::__construct();
        if (!empty($this->table)) {
            $this->setTable($this->table);
        }
    }
    
    public function engine(string $name = ''): Engine {
        return $this->getEngine($name);
    }

    protected function initEngineEvent($engine) {
        timer('db init end');
    }

    /**
     * @param Engine $engine
     */
    protected function changeEngineEvent($engine) {
        // 改变缓存状态
        $this->openCache($engine->config('cache_expire'));
    }

    /**
     * ADD TABLE PREFIX
     * @param string $table
     * @return string
     * @throws \Exception
     */
    public function addPrefix(string $table): string
    {
        if (str_contains($table, '`')) {
            return $table;
        }
        preg_match('/([\w_\.]+)( (as )?[\w_]+)?/i', $table, $match);
        $table = count($match) == 2 ? $table : $match[1];
        $alias = '';
        if (count($match) > 2) {
            $alias = $match[2];
        }
        if (str_contains($table, '.')) {
            list($schema, $table) = explode('.', $table);
            return sprintf('`%s`.`%s`%s', $schema, $table, $alias);
        }
        if (str_starts_with($table, '!')) {
            return sprintf('`%s`%s', substr($table, 1), $alias);
        }
        $prefix = $this->engine()->config('prefix');
        if (empty($prefix)) {
            return sprintf('`%s`%s', $table, $alias);
        }
        return sprintf('`%s`%s', $prefix.
            Str::firstReplace($table, $prefix), $alias);
    }

    /**
     * 设置表
     * @param string $table
     * @return $this
     * @throws \Exception
     */
    public function setTable(string $table) {
        $this->table = $this->addPrefix($table);
        return $this;
    }

    /**
     * GET TABLE
     * @return string
     */
    public function getTable(): string {
        return $this->table;
    }

    /**
     * 更改数据库
     * @param string $schema
     * @return $this
     */
    public function changedSchema(string $schema): Database {
        $engine = $this->engine();
        if (empty($schema)) {
            $schema = $engine->config('database');
        }
        $engine->execute($engine->schemaGrammar()->compileSchemaUse($schema));
        return $this;
    }

    /**
     * 拷贝（未实现）
     */
    public function copy() {
        //return $this->select(null, '* INTO table in db');
    }

    /**
     * 开启缓存
     * @param int|bool $expire
     * @return $this
     */
    public function openCache(int|bool $expire = 3600) {
        $this->allowCache = $expire !== false;
        $this->cacheLife = $expire;
        return $this;
    }

    /**
     * @param string $sql
     * @return array null
     * @throws \Exception
     */
    public function getCache(string $sql) {
        if (!$this->allowCache) {
            return null;
        }
        $cache = cache()->get($this->currentName.$sql);
        if (empty($cache)) {
            return null;
        }
        return unserialize($cache);
    }

    public function setCache(string $sql, $data) {
        if (!$this->allowCache || app()->isDebug()) {
            return;
        }
        cache()->set($this->currentName.$sql, serialize($data), $this->cacheLife);
    }

    /**
     * 查询
     * @param string $sql
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function fetch(string $sql, array $parameters = []) {
        return $this->runOrCache($sql, $parameters, function ($sql, $parameters) {
            return $this->engine()->fetch($sql, $parameters);
        });
    }

    public function fetchMultiple(string $sql, array $parameters = [])
    {
        return $this->runOrCache($sql, $parameters, function ($sql, $parameters) {
            return $this->engine()->fetchMultiple($sql, $parameters);
        });
    }

    /**
     * 执行事务
     * @param array|callable $args sql语句的数组
     * @return bool
     * @throws \Exception
     */
    public function transaction($args) {
        return $this->engine()->transaction($args);
    }

    /**
     * 开始执行事务
     * @return Engine
     * @throws \Exception
     */
    public function beginTransaction(): Engine {
        $this->engine()->transactionBegin();
        return $this->engine();
    }

    /**
     * 插入
     * @param string $sql
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function insert(string $sql, array $parameters = []) {
        return $this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->engine()->insert($sql, $parameters);
        });
    }

    /**
     * 更新, 如果数据相同会返回0
     * @param $sql
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function update(string $sql, array $parameters = []): int {
        return (int)$this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->engine()->update($sql, $parameters);
        });
    }

    /**
     * 删除
     * @param string $sql
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function delete(string $sql, array $parameters = []): int {
        return $this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->engine()->delete($sql, $parameters);
        });
    }

    /**
     * @param string $sql
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function execute(string $sql, array $parameters = []) {
        return $this->runOrCache($sql, $parameters, function ($sql, $parameters) {
            return $this->engine()->execute($sql, $parameters);
        });
    }

    /**
     * @param $query
     * @param $bindings
     * @param Closure $callback
     * @return mixed
     * @throws \Exception
     */
    protected function run($query, $bindings, \Closure $callback) {
        $start = Time::millisecond();
        $result = null;
        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (\Exception $ex) {
            if (app()->isDebug()) {
                throw $ex;
            }
        } finally {
            DB::addQueryLog($query, $bindings, Time::elapsedTime($start));
        }
        return $result;
    }

    protected function runQueryCallback($query, $bindings, Closure $callback) {
        return $callback($query, $bindings);
    }

    /**
     * 获取最后修改的id
     * @return int
     *
     * @throws \Exception
     */
    public function lastInsertId(): int|string {
        return $this->engine()->lastInsertId();
    }

    /**
     * 改变的行数
     * @return int
     * @throws \Exception
     */
    public function rowCount(): int {
        return $this->engine()->rowCount();
    }

    public function insertBatch(string $sql, array $parameters = [])
    {
        return $this->engine()->insertBatch($sql, $parameters);
    }

    public function updateBatch(string $sql, array $parameters = [])
    {
        return $this->engine()->updateBatch($sql, $parameters);
    }

    public function executeScalar(string $sql, array $parameters = [])
    {
        return $this->runOrCache($sql, $parameters, function ($sql, $parameters) {
            return $this->engine()->executeScalar($sql, $parameters);
        });
    }

    public function first(string $sql, array $parameters = [])
    {
        return $this->runOrCache($sql, $parameters, function ($sql, $parameters) {
            return $this->engine()->first($sql, $parameters);
        });
    }

    protected function runOrCache(string $sql, array $parameters, \Closure $callback) {
        if (!$this->engine()->grammar()->cacheable($sql)) {
            return $this->run($sql, $parameters, $callback);
        }
        $args = empty($parameters) ? serialize($parameters) : '';
        if ($cache = $this->getCache($sql.$args)) {
            return $cache;
        }
        $result = $this->run($sql, $parameters, $callback);
        $this->setCache($sql.$args, $result);
        return $result;
    }
}