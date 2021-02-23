<?php
declare(strict_types=1);
namespace Zodream\Database\Contracts;

interface Column {
    public function name(string $name): Column;
    public function getName(): string;
    public function charset(string $charset): Column;
    public function getCharset(): string;
    public function getCollation(): string;
    public function collation(string $collation);
    public function comment(string $comment): Column;
    public function getComment(): string;

    public function setTable(Table $table): Column;

    public function getType(): string;
    public function getTypeOption();
    public function getTypeLength(): int|array;
    public function getTypeIsUnsigned(): bool;
    public function getNullable(): bool;
    public function getDefault();

    public function getOldName(): string;
    public function table(): Table;
    public function getPreviousName(): string;


    public function bool(): Column;
    public function enum(array $items): Column;
    public function int(int $length = 11): Column;
    public function tinyint(int $length = 1): Column;
    public function bit(): Column;
    public function uint(int $length = 11): Column;
    public function short(int $length = 4): Column;
    public function long(int $length = 20): Column;
    public function float(int $length = 8, int $d = 2): Column;
    public function double(int $length = 16, int $d = 10): Column;
    public function decimal(int $length = 16, int $d = 10): Column;
    public function string(int $length = 255): Column;
    public function char(int $length = 10): Column;
    public function varchar(int $length = 255): Column;
    public function text(): Column;
    public function json(): Column;
    public function mediumText(): Column;
    public function longText(): Column;
    public function blob(): Column;
    public function mediumBlob(): Column;
    public function longBlob(): Column;
    public function date(): Column;
    public function year(): Column;
    public function datetime(): Column;
    public function time(): Column;
    public function timestamp(): Column;

    public function nullable(): Column;
    public function unsigned(): Column;
    public function default(string|int|float $value): Column;
    public function ai(int $begin = 1): Column;
    public function pk(bool $isAi = false): Column;
    public function unique(string $name = '', string $order = ''): Column;
    public function index(string $name = '', string $order = ''): Column;
    public function fk(Column $column, string $name = ''): Column;
}