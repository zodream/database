<?php
declare(strict_types=1);
namespace Zodream\Database\Migrations;

use Zodream\Database\Schema\Schema;
use Zodream\Database\Concerns\Migration as MigrationInterface;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/7/27
 * Time: 22:20
 */
abstract class Migration implements MigrationInterface {

    protected $tables = [];
    private $mode = false;

    /**
     * 追加数据
     * @param $table
     * @param null $func
     * @return $this
     */
    public function append($table, $func = null) {
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
     * 自动确定是否为创建表
     */
    public function autoUp() {
        if ($this->mode) {
            return;
        }
        $this->createTable();
    }

    /**
     * 执行生成命令
     */
    public function createTable() {
        foreach ($this->tables as $table => $func) {
            Schema::createOrUpdateTable($table, $func);
        }
    }

    /**
     * 执行删除命令
     */
    public function dropTable() {
        Schema::dropTable(array_keys($this->tables));
    }

    /**
     * 生成测试数据
     */
    public function seed() {}

}