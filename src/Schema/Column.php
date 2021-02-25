<?php
declare(strict_types=1);
namespace Zodream\Database\Schema;

use Zodream\Database\Contracts\Table as TableInterface;
use Zodream\Database\Contracts\Column as ColumnInterface;

class Column implements ColumnInterface {

    protected string $type;
    protected int|array $typeLength = 0;
    protected bool $typeIsUnsigned = false;
    protected bool $isNullable = false;
    protected string|int|float|null $default = null;
    protected $typeOption;

    protected string $charset = 'utf8mb4';
    protected string $collation = 'utf8mb4_general_ci';
    protected string $oldName = '';
    protected TableInterface $table;
    protected string $previousName  = '';

    public function __construct(
        protected string $name,
        protected string $comment = '',
    )
    {
    }

    public function setTable(TableInterface $table): ColumnInterface {
        $this->table = $table;
        return $this;
    }

    public function name(string $name): ColumnInterface
    {
        if (!empty($this->name) && $this->name !== $name) {
            $this->oldName = $this->name;
        }
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function charset(string $charset): ColumnInterface
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

    public function collation(string $collation): ColumnInterface
    {
        $this->collation = $collation;
        return $this;
    }

    public function comment(string $comment): ColumnInterface
    {
        $this->comment = $comment;
        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getOldName(): string
    {
        return $this->oldName;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getTypeOption() {
        return $this->typeOption;
    }

    public function getTypeLength(): int|array {
        return $this->typeLength;
    }
    public function getTypeIsUnsigned(): bool {
        return $this->typeIsUnsigned;
    }
    public function getNullable(): bool {
        return $this->isNullable;
    }
    public function getDefault() {
        return $this->default;
    }

    public function table(): TableInterface
    {
        return $this->table;
    }

    public function after(ColumnInterface $column): ColumnInterface {
        $this->previousName = $column->getName();
        return $this;
    }

    public function getPreviousName(): string
    {
        return $this->previousName;
    }

    public function bool(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function enum(array $items): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeOption = $items;
        return $this;
    }

    public function set(array $allowed): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeOption = $allowed;
        return $this;
    }

    public function jsonb(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function int(int $length = 11): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = $length;
        return $this;
    }

    public function bit(): ColumnInterface {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function tinyint(int $length = 1): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = $length;
        return $this;
    }

    public function uint(int $length = 11): ColumnInterface
    {
        $this->int($length)->unsigned();
        return $this;
    }

    public function short(int $length = 4): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = $length;
        return $this;
    }

    public function long(int $length = 20): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = $length;
        return $this;
    }

    public function float(int $length = 8, int $d = 2): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = [$length, $d];
        return $this;
    }

    public function double(int $length = 16, int $d = 10): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = [$length, $d];
        return $this;
    }

    public function decimal(int $length = 16, int $d = 10): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = [$length, $d];
        return $this;
    }

    public function string(int $length = 255): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = $length;
        return $this;
    }

    public function char(int $length = 10): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = $length;
        return $this;
    }

    public function varchar(int $length = 255): ColumnInterface
    {
        $this->type = __FUNCTION__;
        $this->typeLength = $length;
        return $this;
    }

    public function text(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function json(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function mediumText(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function longText(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function blob(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function mediumBlob(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function longBlob(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function date(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function year(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function datetime(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function time(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function timestamp(): ColumnInterface
    {
        $this->type = __FUNCTION__;
        return $this;
    }

    public function unsigned(): ColumnInterface {
        $this->typeIsUnsigned = true;
        return $this;
    }

    public function nullable(bool $value = true): ColumnInterface
    {
        $this->isNullable = $value;
        return $this;
    }

    public function default(float|int|string $value): ColumnInterface
    {
        $this->default = $value;
        return $this;
    }

    public function ai(int $begin = 1): ColumnInterface
    {
        if (empty($this->type)) {
            $this->uint();
        }
        if ($this->table) {
            $this->table->ai($this, $begin);
        }
        $this->typeOption = 'AUTO_INCREMENT';
        return $this;
    }

    public function pk(bool $isAi = false): ColumnInterface
    {
        if (empty($this->type)) {
            $this->int();
        }
        if ($this->table) {
            $this->table->pk($this);
        }
        if ($isAi) {
            $this->ai();
        }
        return $this;
    }

    public function unique(string $name = '', string $order = ''): ColumnInterface
    {
        if ($this->table) {
            $this->table->unique($name, $this, $order);
        }
        return $this;
    }

    public function index(string $name = '', string $order = ''): ColumnInterface
    {
        if ($this->table) {
            $this->table->index($name, $this, $order);
        }
        return $this;
    }

    public function fk(ColumnInterface $column, string $name = ''): ColumnInterface
    {
        if ($this->table) {
            $this->table->fk($name, $this, $column);
        }
        return $this;
    }
}