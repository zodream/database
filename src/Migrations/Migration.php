<?php
declare(strict_types=1);
namespace Zodream\Database\Migrations;

use Zodream\Database\Contracts\SchemaGrammar;
use Zodream\Database\Contracts\Migration as MigrationInterface;
use Zodream\Database\Schema\Table;
use Zodream\Infrastructure\Contracts\Database;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/7/27
 * Time: 22:20
 */
abstract class Migration implements MigrationInterface {

    /**
     * @var callable[]
     */
    protected array $tables = [];
    private bool $mode = false;

    /**
     * 追加数据
     * @param $table
     * @param null $func
     * @return $this
     */
    public function append(string|array $table, callable $func = null) {
        if (!is_array($table)) {
            $table = [$table => $func];
        }
        foreach ($table as $key => $item) {
            if (!is_callable($item)) {
                continue;
            }
            if (strpos($key, '\\', 1) !== false && is_callable($key.'::tableName')) {
                $key = call_user_func($key.'::tableName');
            }
            $this->tables[$key] = $item;
        }
        return $this;
    }

    public function up() {
        $this->autoUp();
    }

    public function down() {
        $this->mode = true;
        $this->up();
        $this->mode = false;
        $this->dropTable();
    }

    /**
     * 生成测试数据
     */
    public function seed() {}

    /**
     * 自动确定是否为创建表
     */
    protected function autoUp() {
        if ($this->mode) {
            return;
        }
        $this->createTable();
    }

    /**
     * 执行生成命令
     */
    protected function createTable() {
        $help = $this->db()->engine()->information();
        foreach ($this->tables as $table => $func) {
            $item = new Table($table);
            call_user_func($func, $item);
            $help->updateTable($item, autoLoad: true);
        }
    }

    /**
     * 执行删除命令
     */
    protected function dropTable() {
        $db = $this->db();
        $grammar = $this->grammar();
        foreach ($this->tables as $table => $_) {
            /** @var string $table */
            $db->execute($grammar->compileTableDelete($table));
        }
    }

    protected function db(): Database {
        return app('db');
    }

    protected function grammar(): SchemaGrammar {
        return $this->db()->engine()->schemaGrammar();
    }
}