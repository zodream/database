<?php
declare(strict_types=1);
namespace Zodream\Database\Contracts;

interface Table {

    public function setSchema(Schema $schema): Table;
    public function schema(): Schema;

    public function name(string $name): Table;
    public function getName(): string;
    public function charset(string $charset): Table;
    public function getCharset(): string;
    public function getCollation(): string;
    public function collation(string $collation): Table;
    public function comment(string $comment): Table;
    public function getComment(): string;
    public function engine(string $engine): Table;
    public function getEngine(): string;
    public function getPrimaryKey(): string;
    public function getForeignKeys(): array;
    public function getChecks(): array;
    public function getIndexes(): array;
    public function getAiBegin(): int;

    public function ai(Column|string $column, int $begin = 1): Table;
    public function pk(Column|string $column): Table;
    public function fk(string $name, Column|string $column, Column $fkColumn, string $delete = 'NO ACTION', string $update = 'NO ACTION'): Table;
    public function index(string $name, Column|string $column, string $order = ''): Table;
    public function unique(string $name, Column|string $column, string $order = ''): Table;
    public function check(string $name, string $constraint = ''): Table;

    public function column(Column|string $column): Column;
    public function columns(): array;
    public function id(string $column = 'id'): Column;

    /**
     * 自动增长的数字类型主键
     * @param string $column
     * @return Column
     */
    public function increments(string $column): Column;
    public function bool(string $name): Column;
    public function enum(string $name, array $items = []): Column;

    /**
     * 数字类型
     * @param string $name
     * @param int $length < 3 自动为 tinyint
     * @return Column
     */
    public function int(string $name, int $length = 11): Column;

    /**
     * 无符号的数字类型
     * @param string $name
     * @param int $length < 3 自动为 tinyint; < 6 为 short; > 19 为 long
     * @return Column
     */
    public function uint(string $name, int $length = 11): Column;

    /**
     * 比较短的数字类型相当于 smallint
     * @param string $name
     * @param int $length
     * @return Column
     */
    public function short(string $name, int $length = 4): Column;

    /**
     * 长的数字类型相当于 bigint
     * @param string $name
     * @param int $length
     * @return Column
     */
    public function long(string $name, int $length = 20): Column;

    /**
     * @param string $name
     * @param int $length
     * @param int $d 小数点后的位数
     * @return Column
     */
    public function float(string $name, int $length = 8, int $d = 2): Column;
    public function double(string $name, int $length = 16, int $d = 10): Column;
    public function decimal(string $name, int $length = 16, int $d = 10): Column;
    public function string(string $name, int $length = 255): Column;
    public function char(string $name, int $length = 10): Column;
    public function blob(string $name): Column;
    public function date(string $name): Column;
    public function datetime(string $name): Column;
    public function time(string $name): Column;
    public function timestamp(string $name): Column;
}