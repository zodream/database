<?php
declare(strict_types=1);
namespace Zodream\Database\Concerns;

interface Information {

    public function version(): string;
    public function schemaList(): array;
    public function tableList(Schema|string $schema, bool $full = false): array;
    public function columnList(Table|string $table): array;

    public function table(Table|string $table, bool $full = false): Table;
    public function column(Table|string $table, Column|string $column): Column;

}