<?php
declare(strict_types=1);
namespace Zodream\Database\Concerns;

interface Migration {

    /**
     * 安装事件
     */
    public function up();

    /**
     * 卸载事件
     */
    public function down();

    /**
     * 写入数据
     */
    public function seed();
}