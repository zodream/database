<?php
declare(strict_types=1);
namespace Zodream\Database\Adapters\MySql;

use Zodream\Database\Concerns\Information as InformationInterface;

class Information implements InformationInterface {

    public function version(): string
    {
        return db()->executeScalar('SELECT VERSION();');
    }

    public function schemaList(): array
    {
        // TODO: Implement schemaList() method.
    }

    public function tableList(string|Schema $schema, bool $full = false): array
    {
        // TODO: Implement tableList() method.
    }

    public function columnList(string|Table $table): array
    {
        // TODO: Implement columnList() method.
    }

    public function table(string|Table $table, bool $full = false): Table
    {
        // TODO: Implement table() method.
    }

    public function column(string|Table $table, Column|string $column): Column
    {
        // TODO: Implement column() method.
    }
}