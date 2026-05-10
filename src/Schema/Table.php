<?php
declare(strict_types=1);
namespace Zodream\Database\Schema;

use Zodream\Database\Contracts\Schema as SchemaInterface;
use Zodream\Database\Contracts\Table as TableInterface;
use Zodream\Database\Contracts\Column as ColumnInterface;
use Zodream\Database\Utils;

class Table implements TableInterface {

    const MyISAM = 'MyISAM';
    const HEAP = 'HEAP';
    const MEMORY = 'MEMORY';
    const MERGE = 'MERGE';
    const MRG_MYISAM = 'MRG_MYISAM';
    const InnoDB = 'InnoDB';
    const INNOBASE = 'INNOBASE';

    protected string $collation = 'utf8mb4_general_ci';

    protected array $foreignKey = [];

    protected array $checkItems = [];

    protected int $aiBegin = 1;

    protected array $indexItems = [];

    protected string $primaryKey = '';

    protected string $comment = '';

    /**
     * @var SchemaInterface
     */
    protected SchemaInterface $schema;

    public function __construct(
        protected string $name,
        /** @var ColumnInterface[] */
        protected array $items = [],
        protected string $engine = self::MyISAM,
        protected string $charset = 'utf8mb4'
    ) {
    }

    public function setSchema(SchemaInterface $schema): TableInterface {
        $this->schema = $schema;
        return $this;
    }

    public function schema(): SchemaInterface
    {
        return $this->schema;
    }

    public function name(string $name): TableInterface
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function charset(string $charset): TableInterface
    {
        $this->charset = $charset;
        return $this;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getCollation(): string
    {
        return $this->collation;
    }

    public function collation(string $collation): TableInterface
    {
        $this->collation = $collation;
        return $this;
    }

    public function comment(string $comment): TableInterface
    {
        $this->comment = $comment;
        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function engine(string $engine): TableInterface
    {
        $this->engine = $engine;
        return $this;
    }

    public function getEngine(): string
    {
        return $this->engine;
    }

    public function getPrimaryKey(): string {
        return $this->primaryKey;
    }
    public function getForeignKeys(): array {
        return $this->foreignKey;
    }
    public function getChecks(): array {
        return $this->checkItems;
    }
    public function getIndexes(): array {
        return $this->indexItems;
    }
    public function getAiBegin(): int {
        return $this->aiBegin;
    }

    public function ai(ColumnInterface|string $column, int $begin = 1): TableInterface
    {
        $this->aiBegin = max($this->aiBegin, $begin);
        $this->pk($column);
        return $this;
    }

    public function pk(ColumnInterface|string $column): TableInterface
    {
        $this->primaryKey = Utils::formatName($column);
        return $this;
    }

    public function fk(string $name, ColumnInterface|string $column, ColumnInterface $fkColumn, string $delete = 'NO ACTION', string $update = 'NO ACTION'): TableInterface
    {
        $this->foreignKey[$name] = [Utils::formatName($column), Utils::formatName($fkColumn->table()), Utils::formatName($fkColumn), $delete, $update];
        return $this;
    }

    public function index(string $name, ColumnInterface|string $column, string $order = ''): TableInterface
    {
        $this->indexItems[$name] = [Utils::formatName($column), $order];
        return $this;
    }

    public function unique(string $name, ColumnInterface|string $column, string $order = ''): TableInterface
    {
        $this->indexItems[$name] = [Utils::formatName($column), $order, __FUNCTION__];
        return $this;
    }

    public function check(string $name, string $constraint = ''): TableInterface
    {
        if (empty($constraint)) {
            $this->checkItems[] = $name;
        } else {
            $this->checkItems[$name] = $constraint;
        }
        return $this;
    }

    public function columns(): array
    {
        return $this->items;
    }

    public function column(ColumnInterface|string $column): ColumnInterface
    {
        if ($column instanceof ColumnInterface) {
            $this->items[$column->getName()] = $column->setTable($this);
            return $column;
        }
        if (isset($this->items[$column])) {
            return $this->items[$column];
        }
        return $this->items[$column] = (new Column($column))->setTable($this);
    }

    public function id(string $column = 'id'): ColumnInterface {
        return $this->increments($column);
    }

    public function increments(string $column): ColumnInterface {
        return $this->uint($column)->pk(true);
    }

    public function bool(string $name): ColumnInterface
    {
        return $this->column($name)->bool();
    }

    public function enum(string $name, array $items = []): ColumnInterface
    {
        return $this->column($name)->enum($items);
    }

    public function int(string $name, int $length = 11): ColumnInterface
    {
        $column = $this->column($name);
        if ($length < 3) {
            return $column->tinyint(1);
        }
        return $column->int($length);
    }

    public function uint(string $name, int $length = 11): ColumnInterface
    {
        $column = $this->column($name);
        if ($length < 3) {
            return $column->tinyint(1)->unsigned();
        }
        if ($length < 6) {
            return $column->short($length)->unsigned();
        }
        if ($length > 19) {
            return $column->long($length)->unsigned();
        }
        return $column->uint($length);
    }

    public function short(string $name, int $length = 4): ColumnInterface
    {
        return $this->column($name)->short($length);
    }

    public function long(string $name, int $length = 20): ColumnInterface
    {
        return $this->column($name)->long($length);
    }

    public function float(string $name, int $length = 8, int $d = 2): ColumnInterface
    {
        return $this->column($name)->float($length, $d);
    }

    public function double(string $name, int $length = 16, int $d = 10): ColumnInterface
    {
        return $this->column($name)->double($length, $d);
    }

    public function decimal(string $name, int $length = 16, int $d = 10): ColumnInterface
    {
        return $this->column($name)->decimal($length, $d);
    }

    public function string(string $name, int $length = 255): ColumnInterface
    {
        return $this->column($name)->string($length);
    }

    public function text(string $name): ColumnInterface
    {
        return $this->column($name)->text();
    }

    public function char(string $name, int $length = 10): ColumnInterface
    {
        return $this->column($name)->char($length);
    }

    public function blob(string $name): ColumnInterface
    {
        return $this->column($name)->blob();
    }

    public function date(string $name): ColumnInterface
    {
        return $this->column($name)->date();
    }

    public function datetime(string $name): ColumnInterface
    {
        return $this->column($name)->datetime();
    }

    public function time(string $name): ColumnInterface
    {
        return $this->column($name)->time();
    }

    public function timestamp(string $name): ColumnInterface
    {
        return $this->column($name)->timestamp();
    }

    public function timestamps() {
        $this->timestamp('updated_at');
        $this->timestamp('created_at');
    }

    /**
     * Add a "deleted at" timestamp for the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function softDeletes(string $column = 'deleted_at'): ColumnInterface {
        return $this->timestamp($column);
    }
}