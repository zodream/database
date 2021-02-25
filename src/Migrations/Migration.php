<?php
declare(strict_types=1);
namespace Zodream\Database\Migrations;

use Zodream\Database\Contracts\Migration as MigrationInterface;
use Zodream\Database\DB;
use Zodream\Database\Schema\Table;
use Zodream\Database\Contracts\Table as TableInterface;

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
        $this->dropTables();
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
        $this->createTables();
    }

    /**
     * 执行生成命令
     */
    protected function createTables() {
        $help = DB::information();
        foreach ($this->tables as $table => $func) {
            $item = new Table($table);
            call_user_func($func, $item);
            $help->updateTable($item, autoLoad: true);
        }
    }

    /**
     * 执行删除命令
     */
    protected function dropTables() {
        static::dropTable(array_keys($this->tables));
    }

    /**
     * 执行删除命令
     * @param string|array $tables
     */
    public static function dropTable(string|array $tables) {
        $db = DB::db();
        $grammar = DB::schemaGrammar();
        foreach ((array)$tables as $table) {
            $db->execute($grammar->compileTableDelete($table));
        }
    }

    /**
     * 执行生成命令
     * @param string $table
     * @param callable $cb
     */
    public static function createTable(string $table, callable $cb) {
        $item = new Table($table);
        call_user_func($cb, $item);
        DB::information()->updateTable($item, autoLoad: true);
    }

    /**
     * 更新某一列
     * @param TableInterface|string $table
     * @param array $newColumns
     * @param array $updateColumns
     * @param array $dropColumns
     */
    public static function updateTable(TableInterface|string $table, array $newColumns = [], array $updateColumns = [], array $dropColumns = []) {
        DB::db()->execute(DB::schemaGrammar()->compileTableUpdate(
            $table instanceof TableInterface ? $table : new Table($table), $newColumns, $updateColumns, $dropColumns));
    }
}