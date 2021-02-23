<?php
declare(strict_types=1);
namespace Zodream\Database\Adapters\MySql;

use Zodream\Database\Contracts\Information as InformationInterface;
use Zodream\Database\Contracts\SchemaGrammar as SchemaInterface;
use Zodream\Database\Contracts\Schema;
use Zodream\Database\Contracts\Table as TableInterface;
use Zodream\Database\Contracts\Column as ColumnInterface;
use Zodream\Database\Schema\Column;
use Zodream\Database\Utils;
use Zodream\Infrastructure\Contracts\Database;
use Zodream\Database\Schema\Table;

class Information implements InformationInterface {

    public function version(): string
    {
        return $this->db()->executeScalar('SELECT VERSION();');
    }

    public function schemaList(): array
    {
        $items = $this->db()->fetch($this->grammar()->compileSchemaAll());
        return empty($items) ? [] : array_column($items, 'Database');
    }

    public function tableList(string|Schema $schema, bool $full = false): array
    {
        $db = $this->db();
        $db->changedSchema(Utils::formatName($schema));
        $items = $db->fetch($this->grammar()->compileTableAll($full));
        $db->changedSchema('');
        if (empty($items)) {
            return [];
        }
        if (!$full) {
            return array_map(function ($item) {
                return current($item);
            }, $items);
        }
        return $items;
    }

    public function columnList(string|TableInterface $table, bool $full = false): array
    {
        return $this->db()->fetch($this->grammar()->compileColumnAll($table, $full));
    }

    public function table(string|TableInterface $table, bool $full = false): ?TableInterface
    {
        $db = $this->db();
        $grammar = $this->grammar();
        $data = $db->first($grammar->compileTableQuery($table));
        if (empty($data)) {
            return null;
        }
        $table = $this->formatTable($table, $data);
        if (!$full) {
            return $table;
        }
        $items = $this->columnList($table, true);
        foreach ($items as $item) {
            $this->formatColumn($item, $table);
        }
        return $table;
    }

    public function column(string|TableInterface $table, ColumnInterface|string $column): ?ColumnInterface
    {
        $data = $this->db()->first($this->grammar()->compileColumnQuery($table, $column));
        if (empty($data)) {
            return null;
        }
        return $this->formatColumn($data);
    }

    public function tableCreateSql(TableInterface|string $table): string
    {
        $data = $this->db()->first($this->grammar()->compileTableSql($table));
        return empty($data) ? '' : ($data['Create Table'].';');
    }

    /**
     * 通过对比修改表格
     * @param TableInterface $table
     * @param ?TableInterface $oldTable
     * @param bool $autoLoad
     * @return Table
     */
    public function updateTable(TableInterface $table, ?TableInterface $oldTable = null, bool $autoLoad = false): TableInterface {
        if ($autoLoad) {
            $oldTable = $this->table($table->getName(), true);
        }
        $db = $this->db();
        $grammar = $this->grammar();
        if (empty($oldTable)) {
            $db->execute($grammar->compileTableCreate($table));
            return $table;
        }
        list($newColumns, $updateColumns, $dropColumns) = $this->formatDiff($table->columns(), $oldTable->columns());
        $db->execute($grammar->compileTableUpdate($table, $newColumns, $updateColumns, $dropColumns));
        return $table;
    }

    protected function formatDiff(array $items, array $oldItems): array {
        $add = [];
        $update = [];
        $del = [];
        foreach ($items as $name => $item) {
            if (!isset($oldItems[$name])) {
                $add[] = $item;
                continue;
            }
            if (!$this->columnEqual($item, $oldItems[$name])) {
                $update[] = $item;
                continue;
            }
        }
        foreach ($oldItems as $name => $item) {
            if (!isset($items[$name])) {
                $del[] = $item;
            }
        }
        return [$add, $update, $del];
    }

    protected function columnEqual(ColumnInterface $column, ColumnInterface $oldColumn): bool {
        if (!empty($column->getDefault()) && $column->getDefault() !== $oldColumn->getDefault()) {
            return false;
        }
        if (!empty($column->getComment()) && $column->getComment() !== $oldColumn->getComment()) {
            return false;
        }
        return $column->getName() === $oldColumn->getName() &&
            $column->getNullable() === $oldColumn->getNullable() &&
            $column->getTypeIsUnsigned() === $oldColumn->getTypeIsUnsigned() &&
            $column->getTypeLength() === $oldColumn->getTypeLength();
    }

    protected function formatColumn(array $data, ?TableInterface $table = null): ColumnInterface {
        $column = empty($table) ? new Column($data['Field']) : $table->column($data['Field']);
        $column->comment($data['Comment'])
            ->default($data['Default']);
        if (!empty($data['Collation'])) {
            $column->collation($data['Collation']);
        }
        if ($data['Extra'] === 'auto_increment') {
            $column->ai();
        }
        if ($data['Null'] === 'YES') {
            $column->nullable();
        }
        if ($data['Key'] === 'PRI') {
            $column->pk();
        } elseif ($data['Key'] === 'UNI') {
            $column->unique();
        }
        if (strpos($data['Type'], 'unsigned') > 0) {
            $column->unsigned();
        }
        $func = $data['type'];
        $params = [];
        if (preg_match('/^(\b+)\((.+)\)/', $data['Type'], $match)) {
            $func = $match[1];
            if ($func === 'enum') {
               $params = [$this->formatEnumOption($match[2])];
            } elseif ($func === 'decimal') {
                $params = array_map('intval', explode(',', $match[2]));
            } else {
                $params = [intval($match[2])];
            }
        }
        if (method_exists($column, $func)) {
            call_user_func_array([$column, $func], $params);
        }
        return $column;
    }

    protected function formatEnumOption(string $val): array {
        return array_map(function ($item) {
            return trim($item, '\'');
        }, explode(',', $val));
    }

    protected function formatTable(string|TableInterface $table, array $data): TableInterface {
        if (is_string($table)) {
            $table = new Table($table);
        }
        return $table->engine($data['Engine'])
            ->collation($data['Collation'])
            ->comment($data['Comment']);
    }

    protected function db(): Database {
        return app('db');
    }

    protected function grammar(): SchemaInterface {
        return $this->db()->engine()->schemaGrammar();
    }

}