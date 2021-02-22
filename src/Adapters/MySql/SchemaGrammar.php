<?php
declare(strict_types=1);
namespace Zodream\Database\Adapters\MySql;

use Zodream\Database\Concerns\Column;
use Zodream\Database\Concerns\Schema;
use Zodream\Database\Concerns\SchemaGrammar as GrammarInterface;
use Zodream\Database\Concerns\SqlBuilder;
use Zodream\Database\Concerns\Table;

class SchemaGrammar implements GrammarInterface {

    public function compileSchemaAll(): string
    {
        // TODO: Implement compileSchemaAll() method.
    }

    public function compileTableAll(bool $full = false): string
    {
        // TODO: Implement compileTableAll() method.
    }

    public function compileColumnAll(string|Table $table, bool $full = false): string
    {
        // TODO: Implement compileColumnAll() method.
    }

    public function compileTableSql(string|Table $table): string
    {
        // TODO: Implement compileTableSql() method.
    }

    public function compileSchemaCreate(Schema $schema): string
    {
        // TODO: Implement compileSchemaCreate() method.
    }

    public function compileSchemaUpdate(Schema $schema): string
    {
        // TODO: Implement compileSchemaUpdate() method.
    }

    public function compileSchemaDelete(string|Schema $schema): string
    {
        // TODO: Implement compileSchemaDelete() method.
    }

    public function compileSchemaUse(string|Schema $schema): string
    {
        // TODO: Implement compileSchemaUse() method.
    }

    public function compileTableMerge(string|Table $table, SqlBuilder|string $builder): string
    {
        // TODO: Implement compileTableMerge() method.
    }

    public function compileTableCreate(Table $table): string
    {
        // TODO: Implement compileTableCreate() method.
    }

    public function compileTableUpdate(Table $table): string
    {
        // TODO: Implement compileTableUpdate() method.
    }

    public function compileTableDelete(string|Table $table): string
    {
        // TODO: Implement compileTableDelete() method.
    }

    public function compileTableTruncate(string|Table $table): string
    {
        // TODO: Implement compileTableTruncate() method.
    }

    public function compileTableLock(string|Table $table): string
    {
        // TODO: Implement compileTableLock() method.
    }

    public function compileTableUnlock(string|Table $table): string
    {
        // TODO: Implement compileTableUnlock() method.
    }

    public function compileTableAnalyze(string|Table $table): string
    {
        // TODO: Implement compileTableAnalyze() method.
    }

    public function compileTableCheck(string|Table $table): string
    {
        // TODO: Implement compileTableCheck() method.
    }

    public function compileTableOptimize(string|Table $table): string
    {
        // TODO: Implement compileTableOptimize() method.
    }

    public function compileTableRepair(string|Table $table): string
    {
        // TODO: Implement compileTableRepair() method.
    }

    public function compileTableRename(string|Table $table, string $newName): string
    {
        // TODO: Implement compileTableRename() method.
    }

    public function compileColumnCreate(Column $column): string
    {
        // TODO: Implement compileColumnCreate() method.
    }

    public function compileColumnUpdate(Column $column): string
    {
        // TODO: Implement compileColumnUpdate() method.
    }

    public function compileColumnDelete(Column|string $column): string
    {
        // TODO: Implement compileColumnDelete() method.
    }
}