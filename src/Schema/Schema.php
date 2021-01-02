<?php
namespace Zodream\Database\Schema;


/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/6/25
 * Time: 9:19
 */

use Zodream\Database\Command;
use Zodream\Infrastructure\Contracts\Database;

class Schema {

    protected $schema = 'zodream';

    protected $charset = 'utf8mb4';

    protected $collationName = 'utf8mb4_general_ci'; // 校对集

    public function __construct($schema = null) {
        $this->setSchema($schema);
    }

    /**
     * @return Database|Command
     */
    protected function command() {
        return app('db');
    }

    public function setSchema($schema = null) {
        if (empty($schema)) {
            $schema = $this->command()->getEngine()->getConfig('database');
        }
        $this->schema = $schema;
        return $this;
    }

    public function getSchema() {
        return $this->schema;
    }

    /**
     * 编码
     * @param string $charset
     * @return $this
     */
    public function setCharset($charset = 'UTF8') {
        $this->charset = $charset;
        return $this;
    }

    /**
     * 校对集
     * @param string $collationName
     * @return $this
     */
    public function setCollationName($collationName) {
        $this->collationName = $collationName;
        return $this;
    }

    public function create() {
        return $this->command()
            ->execute(sprintf('CREATE SCHEMA IF NOT EXISTS `%s` DEFAULT CHARACTER SET %s COLLATE %s',
                $this->schema,
                $this->charset, $this->collationName));
    }

    public function update() {
        return $this->command()
            ->execute(sprintf('ALTER SCHEMA `%s`  DEFAULT CHARACTER SET %s  DEFAULT COLLATE %s',
                $this->schema,
                $this->charset, $this->collationName));
    }

    public function delete() {
        return $this->command()
            ->execute('DROP DATABASE `'.$this->schema.'`');
    }

    public function clear() {
        $tables = $this->getAllTable();
        return $this->command()->execute('DROP TABLE `'.implode('`,`', $tables).'`');
    }

    /**
     * 获取所有数据库名
     * @return array
     */
    public static function getAllDatabase() {
        return db()->fetch('SHOW DATABASES');
    }

    /**
     * 获取表名
     * @param bool $hasStatus
     * @return array
     */
    public function getAllTable($hasStatus = false) {
        $this->command()
            ->changedSchema($this->schema);
        if ($hasStatus) {
            return $this->command()
                ->select('SHOW TABLE STATUS');
        }
        $tables = $this->command()
            ->select('SHOW TABLES');
        foreach ($tables as &$table) {
            $table = current($table);
        }
        return $tables;
    }

    /**
     * @param string $name
     * @return Table
     */
    public function table($name) {
        return (new Table($name))
            ->setSchema($this);
    }

    /**
     * 合并多个表， 请保证没有重复字段
     * @param string $table
     * @param string|Query $sql
     * @return mixed
     */
    public function mergeTable($table, $sql) {
        return $this->command()->execute('CREATE TABLE '.$this->addPrefix($table).' AS '.$sql);
    }

    /**
     * 新建表
     * @param string $table
     * @param callable $callback
     * @return mixed
     */
    public static function createTable($table, callable $callback) {
        $table = new Table($table);
        call_user_func($callback, $table);
        return $table->create();
    }

    /**
     * 重命名表
     * @param $table
     * @param $newName
     * @return mixed
     */
    public static function renameTable($table, $newName) {
        return (new Table($table))->rename($newName);
    }

    /**
     * 删除表
     * @param string|array $tables
     * @return bool
     */
    public static function dropTable($tables) {
        $table = new Table('');
        foreach ((array)$tables as $item) {
            $table->setTableName($item)->drop();
        }
        return true;
    }
}