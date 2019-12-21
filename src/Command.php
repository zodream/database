<?php
namespace Zodream\Database;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/5/14
 * Time: 9:07
 */
use Zodream\Helpers\Time;
use Zodream\Database\Engine\BaseEngine;
use Zodream\Database\Engine\Pdo;
use Zodream\Service\Config;
use Zodream\Service\Factory;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Traits\SingletonPattern;
use Closure;

/**
 * Class Command
 * @package Zodream\Database
 * @method BaseEngine getEngine($name = null)
 */
class Command extends Manager {

    use SingletonPattern;

    protected $table;

    protected $prefix;

    protected $allowCache = true;

    protected $cacheLife = 3600;

    protected $configKey = 'db';

    /**
     * @var BaseEngine[]
     */
    protected $engines = [];

    protected $defaultDriver = Pdo::class;

    public function __construct() {
        parent::__construct();
        if (isset($this->table)) {
            $this->setTable($this->table);
        }
    }

    protected function initEngineEvent($engine) {
        Factory::timer()->record('db init end');
    }

    /**
     * @param BaseEngine $engine
     */
    protected function changeEngineEvent($engine) {
        // 改变缓存状态
        $this->openCache($engine->getConfig('cache_expire'));
    }

    /**
     * ADD TABLE PREFIX
     * @param string $table
     * @return string
     * @throws \Exception
     */
    public function addPrefix($table) {
        if (strpos($table, '`') !== false) {
            return $table;
        }
        preg_match('/([\w_\.]+)( (as )?[\w_]+)?/i', $table, $match);
        $table = count($match) == 2 ? $table : $match[1];
        $alias = '';
        if (count($match) > 2) {
            $alias = $match[2];
        }
        if (strpos($table, '.') !== false) {
            list($schema, $table) = explode('.', $table);
            return sprintf('`%s`.`%s`%s', $schema, $table, $alias);
        }
        if (strpos($table, '!') === 0) {
            return sprintf('`%s`%s', substr($table, 1), $alias);
        }
        $prefix = $this->getEngine()->getConfig('prefix');
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
    public function setTable($table) {
        $this->table = $this->addPrefix($table);
        return $this;
    }

    /**
     * @return Grammars\Grammar
     * @throws \Exception
     */
    public function grammar() {
        return $this->getEngine()->getGrammar();
    }

    /**
     * GET TABLE
     * @return string
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * 更改数据库
     * @param string $database
     * @return $this
     * @throws \Exception
     */
    public function changedDatabase($database) {
        $this->getEngine()->execute('use '.$database);
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
     * @param int $expire
     * @return $this
     */
    public function openCache($expire = 3600) {
        $this->allowCache = $expire !== false;
        $this->cacheLife = $expire;
        return $this;
    }

    /**
     * @param string $sql
     * @return array null
     * @throws \Exception
     */
    public function getCache($sql) {
        if (!$this->allowCache) {
            return null;
        }
        $cache = Factory::cache()->get($this->currentName.$sql);
        if (empty($cache)) {
            return null;
        }
        return unserialize($cache);
    }

    public function setCache($sql, $data) {
        if (!$this->allowCache || Config::isDebug()) {
            return;
        }
        Factory::cache()->set($this->currentName.$sql, serialize($data), $this->cacheLife);
    }

    /**
     * 查询
     * @param string $sql
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function select($sql, $parameters = []) {
        return $this->getArray($sql, $parameters);
    }

    /**
     * 执行事务
     * @param array|callable $args sql语句的数组
     * @return bool
     * @throws \Exception
     */
    public function transaction($args) {
        return $this->getEngine()->transaction($args);
    }

    /**
     * 开始执行事务
     * @return BaseEngine
     * @throws \Exception
     */
    public function beginTransaction() {
        $this->getEngine()->begin();
        return $this->getEngine();
    }

    /**
     * 插入
     * @param $sql
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function insert($sql, $parameters = []) {
        return $this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->getEngine()->insert($sql, $parameters);
        });
    }

    /**
     * 如果行作为新记录被insert，则受影响行的值为1；如果原有的记录被更新，则受影响行的值为2。 如果有多条存在则只更新最后一条
     * @param string $columns
     * @param string $tags
     * @param string $update
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function insertOrUpdate($columns, $tags, $update, $parameters = array()) {
        if (!empty($columns) && strpos($columns, '(') === false) {
            $columns = '('.$columns.')';
        }
        $tags = trim($tags);
        if (strpos($tags, '(') !== 0) {
            $tags = '('.$tags.')';
        }
        return $this->update("INSERT INTO {$this->table} {$columns} VALUES {$tags} ON DUPLICATE KEY UPDATE {$update}", $parameters);
    }

    /**
     *在执行REPLACE后，系统返回了所影响的行数，如果返回1，说明在表中并没有重复的记录，如果返回2，说明有一条重复记录，系统自动先调用了 DELETE删除这条记录，然后再记录用INSERT来insert这条记录。如果返回的值大于2，那说明有多个唯一索引，有多条记录被删除和insert。
     * @param string $columns
     * @param string $tags
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function insertOrReplace($columns, $tags, $parameters = array()) {
        if (!empty($columns) && strpos($columns, '(') === false) {
            $columns = '('.$columns.')';
        }
        $tags = trim($tags);
        if (strpos($tags, '(') !== 0) {
            $tags = '('.$tags.')';
        }
        return $this->update("REPLACE INTO {$this->table} {$columns} VALUES {$tags}", $parameters);
    }

    /**
     * 更新, 如果数据相同会返回0
     * @param $sql
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function update($sql, $parameters = []) {
        return $this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->getEngine()->update($sql, $parameters);
        });
    }

    /**
     * 删除
     * @param null $sql
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function delete($sql = null, $parameters = []) {
        return $this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->getEngine()->delete($sql, $parameters);
        });
    }

    /**
     * @param string $sql
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function execute($sql, $parameters = []) {
        if (preg_match('/^(insert|delete|update|replace|drop|create)\s+/i', $sql)) {
            return $this->run($sql, $parameters, function ($sql, $parameters) {
                return $this->getEngine()->execute($sql, $parameters);
            });
        }
        $args = empty($parameters) ? serialize($parameters) : null;
        if ($cache = $this->getCache($sql.$args)) {
            return $cache;
        }
        $result = $this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->getEngine()->execute($sql, $parameters);
        });
        $this->setCache($sql.$args, $result);
        return $result;
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
        $result = $this->runQueryCallback($query, $bindings, $callback);
        DB::addQueryLog($query, $bindings, Time::elapsedTime($start));
        $error = $this->getError();
        if (!empty($error)) {
            throw new \Exception($error);
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
    public function lastInsertId(): int {
        return $this->getEngine()->lastInsertId();
    }

    /**
     * 改变的行数
     * @return int
     * @throws \Exception
     */
    public function rowCount(): int {
        return $this->getEngine()->rowCount();
    }

    /**
     * @param string $sql
     * @param array $parameters
     * @return array
     * @throws \Exception
     */
    public function getArray($sql, $parameters = array()) {
        $args = empty($parameters) ? serialize($parameters) : null;
        if ($cache = $this->getCache($sql.$args)) {
            return $cache;
        }
        $result = $this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->getEngine()->getArray($sql, $parameters);
        });
        $this->setCache($sql.$args, $result);
        return $result;
    }

    /**
     * @param string $sql
     * @param array $parameters
     * @return object[]
     * @throws \Exception
     */
    public function getObject($sql, $parameters = array()) {
        $args = empty($parameters) ? serialize($parameters) : null;
        if ($cache = $this->getCache($sql.$args)) {
            return $cache;
        }
        $result = $this->run($sql, $parameters, function ($sql, $parameters) {
            return $this->getEngine()->getObject($sql, $parameters);
        });
        $this->setCache($sql.$args, $result);
        return $result;
    }

    /**
     * 获取错误信息
     * @throws \Exception
     */
    public function getError() {
        return $this->getEngine()->getError();
    }
}