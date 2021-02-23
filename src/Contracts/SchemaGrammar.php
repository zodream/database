<?php
declare(strict_types=1);
namespace Zodream\Database\Contracts;

interface SchemaGrammar {

    public function compileCopy(SqlBuilder|string $builder, Table|string $distTable): string;

    public function compileSchemaAll(): string;
    public function compileTableAll(bool $full = false): string;
    public function compileColumnAll(Table|string $table, bool $full = false): string;
    public function compileTableSql(Table|string $table): string;

    public function compileSchemaCreate(Schema $schema): string;
    public function compileSchemaUpdate(Schema $schema): string;
    public function compileSchemaDelete(Schema|string $schema): string;
    public function compileSchemaUse(Schema|string $schema): string;

    public function compileTableQuery(Table|string $table): string;
    public function compileTableMerge(Table|string $table, SqlBuilder|string $builder): string;
    public function compileTableCreate(Table $table): string;
    public function compileTableUpdate(Table $table, array $newColumns = [], array $updateColumns = [], array $dropColumns = []): string;
    public function compileTableDelete(Table|string $table): string;
    public function compileTableTruncate(Table|string $table): string;
    public function compileTableLock(Table|string $table): string;
    public function compileTableUnlock(Table|string $table): string;
    public function compileTableAnalyze(Table|string $table): string;
    public function compileTableCheck(Table|string $table): string;
    public function compileTableOptimize(Table|string $table): string;
    public function compileTableRepair(Table|string $table): string;
    public function compileTableRename(Table|string $table, string $newName): string;

    public function compileColumnQuery(Table|string $table, Column|string $column): string;
    public function compileColumnCreate(Column $column): string;
    public function compileColumnUpdate(Column $column): string;
    public function compileColumnDelete(Column|string $column): string;
}