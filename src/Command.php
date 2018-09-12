<?php
namespace Zodream\Database;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/5/14
 * Time: 9:07
 */
use Zodream\Infrastructure\Base\ConfigObject;
use Zodream\Database\Engine\BaseEngine;
use Zodream\Database\Engine\Pdo;
use Zodream\Service\Config;
use Zodream\Service\Factory;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Traits\SingletonPattern;

class Command extends ConfigObject {

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

    protected $currentName = '__default__';

    public function __construct() {
        $this->loadConfigs();
        $this->getCurrentName();
        if (isset($this->table)) {
            $this->setTable($this->table);
        }
    }

    /**
     * ADD 2D ARRAY
     * @param array $args
     * @return $this
     */
    public function setConfigs(array $args) {
        if (!is_array(current($args))) {
            $args = [
                $this->currentName => $args
            ];
        }
        foreach ($args as $key => $item) {
            if (array_key_exists($key, $this->configs)) {
                $this->configs[$key] = array_merge($this->configs[$key], $item);
            } else {
                $this->configs[$key] = $item;
            }
        }
        return $this;
    }

    /**
     * @param string|array|BaseEngine $name
     * @param array|BaseEngine|null $configs
     * @return BaseEngine
     * @throws \Exception
     */
    public function addEngine($name, $configs = null) {
        if (!is_string($name) && !is_numeric($name)) {
            $configs = $name;
            $name = $this->currentName;
        }
        if (array_key_exists($name, $this->engines)) {
            $this->engines[$name]->close();
        }
        if ($configs instanceof BaseEngine) {
            return $this->engines[$name] = $configs;
        }
        if (!array_key_exists('driver', $configs) || !class_exists($configs['driver'])) {
            $configs['driver'] = Pdo::class;
        }
        $class = $configs['driver'];
        $this->engines[$name] = new $class($configs);
        Factory::timer()->record('db init end');
        return $this->engines[$name];
    }

    /**
     * GET DATABASE ENGINE
     * @param string $name
     * @return BaseEngine
     * @throws \Exception
     */
    public function getEngine($name = null) {
        if (is_null($name)) {
            $name = $this->getCurrentName();
        }
        if (array_key_exists($name, $this->engines)) {
            return $this->engines[$name];
        }
        if (!$this->hasConfig($name)) {
            throw new \InvalidArgumentException(
                sprintf(
                __('%s DOES NOT HAVE CONFIG!')
                , $name)
            );
        }
        $engine = $this->addEngine($name, $this->getConfig($name));
        // 改变缓存状态
        $this->openCache($engine->getConfig('cache_expire'));
        return $engine;
    }

    public function getCurrentName() {
        if (!array_key_exists($this->currentName, $this->configs)) {
            $this->currentName = key($this->configs);
        }
        return $this->currentName;
    }

    public function getConfig($name = null) {
        if (is_null($name)) {
            $name = $this->getCurrentName();
        }
        return array_key_exists($name, $this->configs) ? $this->configs[$name] : [];
    }

    public function hasConfig($name = null) {
        if (is_null($name)) {
            return empty($this->configs);
        }
        return array_key_exists($name, $this->configs);
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
        $table = $match[1];
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
        return $this->select(null, '* INTO table in db');
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
    public function select($sql, $parameters = array()) {
        DB::addQueryLog($sql, $parameters);
        return $this->getArray($sql, $parameters);
    }

    /**
     * 执行事务
     * @param array $args sql语句的数组
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
    public function insert($sql, $parameters = array()) {
        DB::addQueryLog($sql, $parameters);
        return $this->getEngine()->insert($sql, $parameters);
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
     * 更新
     * @param $sql
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function update($sql, $parameters = array()) {
        DB::addQueryLog($sql, $parameters);
        return $this->getEngine()->update($sql, $parameters);
    }

    /**
     * 删除
     * @param null $sql
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function delete($sql = null, $parameters = array()) {
        DB::addQueryLog($sql, $parameters);
        return $this->getEngine()->delete($sql, $parameters);
    }

    /**
     * @param string $sql
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function execute($sql, $parameters = array()) {
        if (preg_match('/^(insert|delete|update|replace|drop|create)\s+/i', $sql)) {
            return $this->getEngine()->execute($sql, $parameters);
        }
        $args = empty($parameters) ? serialize($parameters) : null;
        if ($cache = $this->getCache($sql.$args)) {
            return $cache;
        }
        DB::addQueryLog($sql, $parameters);
        $result = $this->getEngine()->execute($sql, $parameters);
        $this->setCache($sql.$args, $result);
        return $result;
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
        $result = $this->getEngine()->getArray($sql, $parameters);
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
        $result = $this->getEngine()->getObject($sql, $parameters);
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