<?php
declare(strict_types=1);
namespace Zodream\Database\Contracts;

use Zodream\Database\Model\Model;
use Zodream\Database\Relation;

interface EntityCreator {

    public function tableName(): string;

    /**
     * 创建，不格式化
     */
    public function create(array $data): Model;
    /**
     * 转换
     */
    public function parse(array $data): Model;

    /**
     * 获取关联关系
     */
    public function binding(string $name): Relation;

    public function callScope(string $name, array $arguments): void;

    public function qualifyColumn(string $column): string;

    public function lock(string $lockType = ''): void;

    /**
     * 生成查询语句
     */
    public function builder(): SqlBuilder;
}