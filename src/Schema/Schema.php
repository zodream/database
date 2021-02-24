<?php
declare(strict_types=1);
namespace Zodream\Database\Schema;

use Zodream\Database\Contracts\Schema as SchemaInterface;
use Zodream\Database\Contracts\Table as TableInterface;
use Zodream\Database\DB;

class Schema implements SchemaInterface {

    protected string $name;
    protected string $charset = 'utf8mb4';
    protected string $collation = 'utf8mb4_general_ci';

    /**
     * @var TableInterface[]
     */
    protected array $items = [];

    public function __construct(string $schema = '')
    {
        $this->name($schema);
    }

    public function name(string $name): SchemaInterface
    {
        if (empty($name)) {
            $name = DB::engine()->config('database');
        }
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function charset(string $charset): SchemaInterface
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

    public function collation(string $collation): SchemaInterface
    {
        $this->collation = $collation;
        return $this;
    }

    public function tables(): array {
        return $this->items;
    }

    public function table(string|TableInterface $table): TableInterface
    {
        if ($table instanceof TableInterface) {
            $this->items[$table->getName()] = $table->setSchema($this);
            return $table;
        }
        if (isset($this->items[$table])) {
            return $this->items[$table];
        }
        return $this->items[$table] = (new Table($table))->setSchema($this);
    }
}