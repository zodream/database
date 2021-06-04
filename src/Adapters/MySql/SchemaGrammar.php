<?php
declare(strict_types=1);
namespace Zodream\Database\Adapters\MySql;

use Zodream\Database\Contracts\Column;
use Zodream\Database\Contracts\Schema;
use Zodream\Database\Contracts\SchemaGrammar as GrammarInterface;
use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\Contracts\Table;
use Zodream\Database\Utils;

class SchemaGrammar implements GrammarInterface {

    public function compileSchemaAll(): string
    {
        return 'SHOW DATABASES';
    }

    public function compileTableAll(bool $full = false): string
    {
        return $full ? 'SHOW TABLE STATUS' : 'SHOW TABLES';
    }

    public function compileColumnAll(string|Table $table, bool $full = false): string
    {
        return sprintf($full ? 'SHOW FULL COLUMNS FROM %s' : 'SHOW COLUMNS FROM %s',
            Utils::formatName($table));
    }

    public function compileTableSql(string|Table $table): string
    {
        return sprintf('SHOW CREATE TABLE %s', Utils::formatName($table));
    }

    public function compileSchemaCreate(Schema $schema): string
    {
        return sprintf('CREATE SCHEMA IF NOT EXISTS `%s` DEFAULT CHARACTER SET %s COLLATE %s',
            $schema->getName(),
            $schema->getCharset(), $schema->getCollation());
    }

    public function compileSchemaUpdate(Schema $schema): string
    {
        return sprintf('ALTER SCHEMA `%s`  DEFAULT CHARACTER SET %s  DEFAULT COLLATE %s',
            $schema->getName(),
            $schema->getCharset(), $schema->getCollation());
    }

    public function compileSchemaDelete(string|Schema $schema): string
    {
        return sprintf('DROP DATABASE `%s`', Utils::formatName($schema));
    }

    public function compileSchemaUse(string|Schema $schema): string
    {
        return sprintf('use `%s`;', Utils::formatName($schema));
    }

    public function compileTableQuery(Table|string $table): string {
        return sprintf('SHOW TABLE STATUS WHERE `Name`=\'%s\'', Utils::formatName($table));
    }

    public function compileTableMerge(string|Table $table, SqlBuilder|string $builder): string
    {
        return sprintf(
            'CREATE TABLE %s AS %s',
            Utils::formatName($table),
            Utils::formatName($builder)
        );
    }

    public function compileTableCreate(Table $table): string
    {
        $items = ['CREATE TABLE IF NOT EXISTS', Utils::wrapName($table->getName()), '('];
        $lines = [];
        foreach ($table->columns() as $column) {
            $lines[] = $this->compileColumnSQL($column);
        }
        if (!empty($table->getPrimaryKey())) {
            $lines[] = "PRIMARY KEY (`{$table->getPrimaryKey()}`)";
        }
        foreach ($table->getChecks() as $key => $item) {
            $lines[] = (!is_integer($key) ? "CONSTRAINT `{$key}` " : null)." CHECK ({$item})";
        }
        foreach ($table->getIndexes() as $key => $item) {
            $lines[] = (count($item) > 2 ? 'UNIQUE ': null). "INDEX `{$key}` (`{$item[0]}` {$item['1']})";
        }
        foreach ($table->getForeignKeys() as $key => $item) {
            $lines[] = "CONSTRAINT `{$key}` FOREIGN KEY (`{$item[0]}`) REFERENCES `{$item[1]}` (`{$item[2]}`) ON DELETE {$item[2]} ON UPDATE {$item[3]}";
        }
        $items[] = implode(',', $lines);
        $items[] = ") ENGINE={$table->getEngine()}";
        if ($table->getAiBegin() > 1) {
            $items[] = 'AUTO_INCREMENT='.$table->getAiBegin();
        }
        $items[] = 'DEFAULT CHARSET='. $table->getCharset();
        if (!empty($table->getComment())) {
            $items[] = 'COMMENT='.Utils::wrapText($table->getComment());
        }
        return implode(' ', $items).';';
    }

    /**
     * @param Table $table
     * @param Column[] $newColumns
     * @param Column[]$updateColumns
     * @param Column[] $dropColumns
     * @return string
     */
    public function compileTableUpdate(Table $table,
                                       array $newColumns = [],
                                       array $updateColumns = [],
                                       array $dropColumns = []): string
    {
        $items = [];
        if (empty($newColumns) && empty($updateColumns) && empty($dropColumns)) {
            $updateColumns = $table->columns();
        }
        foreach ($dropColumns as $item) {
            $items[] = $this->compileColumnDelete($item);
        }
        foreach ($updateColumns as $item) {
            $items[] = $this->compileColumnUpdate($item);
        }
        foreach ($newColumns as $item) {
            $items[] = $this->compileColumnCreate($item);
        }
        return sprintf('ALTER TABLE %s %s;',
            $table->getName(),
            implode(',', $items));
    }

    public function compileTableDelete(string|Table $table): string
    {
        return sprintf('DROP TABLE IF EXISTS %s;', Utils::formatName($table));
    }

    public function compileTableTruncate(string|Table $table): string
    {
        return sprintf('TRUNCATE %s;', Utils::formatName($table));
    }

    public function compileTableLock(string|Table|array $table, string $lockType = ''): string
    {
        $lines = [];
        foreach ((array)$table as $key => $item) {
            if (is_numeric($key)) {
                list($key, $item) = [$item, $lockType];
            }
            $lines[] = sprintf('%s %s', Utils::formatName($key), strtoupper(empty($item) ? 'WRITE' : $item));
        }
        return sprintf('LOCK TABLES %s;', implode(',', $lines));
    }

    public function compileTableUnlock(string|Table|array $table): string
    {
        return 'UNLOCK TABLES;';
    }

    public function compileTableAnalyze(string|Table $table): string
    {
        return sprintf('ANALYZE TABLE %s;', Utils::formatName($table));
    }

    public function compileTableCheck(string|Table $table): string
    {
        return sprintf('CHECK TABLE %s;', Utils::formatName($table));
    }

    public function compileTableOptimize(string|Table $table): string
    {
        return sprintf('OPTIMIZE TABLE %s;', Utils::formatName($table));
    }

    public function compileTableRepair(string|Table $table): string
    {
        return sprintf('REPAIR TABLE %s;', Utils::formatName($table));
    }

    public function compileTableRename(string|Table $table, string $newName): string
    {
        return sprintf('ALTER TABLE  %s RENAME TO %s', Utils::formatName($table), $newName);
    }

    public function compileColumnQuery(Table|string $table, Column|string $column): string {
        return sprintf('SHOW FULL COLUMNS FROM `%s` WHERE `Field`=\'%s\'', Utils::formatName($table), Utils::formatName($column));
    }

    protected function compileColumnSQL(Column $column): string
    {
        $type = $this->formatColumnType($column);
        $items = [];
        if ($column->getName()) {
            $items[] = Utils::wrapName($column->getName());
        }
        $items[] = $type;
        $items[] = $column->getNullable() ? 'NULL' : 'NOT NULL';
        if ($column->getTypeOption() === 'AUTO_INCREMENT') {
            $items[] = 'AUTO_INCREMENT';
        }
        if (!is_null($column->getDefault())) {
            $items[] = 'DEFAULT';
            $items[] = is_string($column->getDefault()) ?
                Utils::wrapText($column->getDefault()) :
                $column->getDefault();
        }
        if (!empty($column->getComment())) {
            $items[] = 'COMMENT';
            $items[] = Utils::wrapText($column->getComment());
        }
        if (($column->table() && $column->table()->getCharset() === $column->getCharset()) ||
            (!str_contains($type, 'CHAR')
                && !str_contains($type, 'TEXT'))) {
            return implode(' ', $items);
        }
        $items[] = sprintf('CHARACTER SET \'%s\' COLLATE \'%s\'',
            $column->getCharset(),
            $column->getCollation());
        return implode(' ', $items);
    }

    public function compileColumnCreate(Column $column): string {
        $items = ['ADD COLUMN', $this->compileColumnSQL($column)];
        if (!empty($column->getPreviousName())) {
            $items[] = "after `{$column->getPreviousName()}`";
        }
        return implode(' ', $items);
    }

    public function compileColumnUpdate(Column $column): string
    {
        $items = [
            'CHANGE COLUMN',
            Utils::wrapName(empty($column->getOldName()) ? $column->getName() : $column->getOldName()),
            $this->compileColumnSQL($column)
        ];
        if (!empty($column->getPreviousName())) {
            $items[] = "after `{$column->getPreviousName()}`";
        }
        return implode(' ', $items);
    }

    public function compileColumnDelete(Column|string $column): string
    {
        return "DROP COLUMN `{$column->getName()}`";
    }

    public function compileCopy(SqlBuilder|string $builder, string|Table $distTable): string
    {
        // TODO: Implement compileCopy() method.
    }

    protected function formatColumnType(Column $column): string {
        $type = $column->getType();
        if (in_array($type, ['set', 'enum'])) {
            return sprintf('%s(%s)', strtoupper($type), implode(',',
                array_map(function ($item) {
                return Utils::wrapText($item);
            }, $column->getTypeOption())));
        }
        if ($type === 'timestamp') {
            $column->default(0);
            return 'INT(10) UNSIGNED';
        }
        if ($type === 'bool') {
            return 'TINYINT(1) UNSIGNED';
        }
        $typeMaps = [
            'bool' => 'tinyint',
            'short' => 'smallint',
            'long' => 'bigint',
            'string' => 'varchar',
            'jsonb' => 'json'
        ];
        if (isset($typeMaps[$type])) {
            $type = $typeMaps[$type];
        }
        $length = $column->getTypeLength();
        $unsigned = $column->getTypeIsUnsigned() &&
            in_array($type,
                ['tinyint', 'int', 'smallint', 'bigint', 'float', 'double', 'decimal']) ? ' UNSIGNED' : '';
        if ($type === 'decimal') {
            return sprintf('DECIMAL(%s)%s',
                implode(',', (array)$length), $unsigned);
        }
        if ($type === 'tinyint') {
            // 所有的tinyint 都设为1
            $length = 1;
        }
        $lengthTypes = ['tinyint', 'int', 'smallint', 'bigint', 'float', 'double', 'char', 'varchar'];
        if (is_array($length)) {
            $length = $length[0];
        }
        if (in_array($type, $lengthTypes)) {
            return sprintf('%s(%d)%s', strtoupper($type), $length, $unsigned);
        }
        return strtoupper($type);
    }
}