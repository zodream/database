<?php
declare(strict_types=1);
namespace Zodream\Database\Contracts;

interface Schema {

    public function name(string $name): Schema;
    public function getName(): string;
    public function charset(string $charset): Schema;
    public function getCharset(): string;
    public function getCollation(): string;
    public function collation(string $collation): Schema;

    public function tables(): array;
    public function table(Table|string $table): Table;
}