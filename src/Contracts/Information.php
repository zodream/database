<?php
declare(strict_types=1);
namespace Zodream\Database\Contracts;

interface Information {

    public function version(): string;
    public function schemaList(): array;
    public function tableList(Schema|string $schema, bool $full = false): array;
    public function columnList(Table|string $table, bool $full = false): array;
    public function foreignKeys(Table $table): array;

    public function table(Table|string $table, bool $full = false): ?Table;
    public function tableCreateSql(Table|string $table): string;
    public function column(Table|string $table, Column|string $column): ?Column;

    /**
     * 通过对比修改表格
     * @param Table $table
     * @param Table|null $oldTable
     * @param bool $autoLoad
     * @return Table
     */
    public function updateTable(Table $table, Table|null $oldTable = null, bool $autoLoad = false): Table;

}