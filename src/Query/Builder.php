<?php
declare(strict_types = 1);
namespace Zodream\Database\Query;

use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\DB;
use Zodream\Database\Query\Components\ExecBuilder;
use Zodream\Database\Query\Components\JoinBuilder;
use Zodream\Database\Query\Components\RecordBuilder;
use Zodream\Database\Query\Components\SelectBuilder;
use Zodream\Database\Query\Components\WhereBuilder;
use Zodream\Database\Schema\BaseSchema;
use Closure;
use InvalidArgumentException;

class Builder extends BaseSchema implements SqlBuilder {

    use SelectBuilder, JoinBuilder, WhereBuilder, ExecBuilder, RecordBuilder;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    protected array $bindings = [
        'select' => [],
        'join'   => [],
        'where'  => [],
        'having' => [],
        'order'  => [],
        'union'  => [],
    ];

    public array|string $from = [];

    public $limit;

    public $offset;

    public array $groups = [];

    public array $having = [];

    public array $orders = [];

    public array $unions = [];

    protected array $sequence = [
        'select',
        'from',
        'join',
        'left',
        'inner',
        'right',
        'where',
        'group',
        'having',
        'order',
        'limit',
        'offset'
    ];

    const OPERATORS = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'in', 'not in', 'is', 'is not',
        'like', 'like binary', 'not like', 'between', 'not between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to'
    ];

    /**
     * 提前知道查询结果为空，但必须保留链式
     * @var bool
     */
    protected bool $isEmpty = false;

    public function __construct(array $args = array()) {
        $this->load($args);
    }

    /**
     * SET TABLE
     * @param $table
     * @return Builder
     * @throws \Exception
     */
    public function table(string $table): SqlBuilder {
        $this->from = $table;
        return $this;
    }

    public function getTable(): string {
        return $this->addPrefix(is_array($this->from) ? reset($this->from) : $this->from);
    }

    public function isEmpty(): SqlBuilder {
        $this->isEmpty = true;
        return $this;
    }

    /**
     * MAKE LIKE 'SELECT' TO EMPTY ARRAY!
     * @param $tag
     * @return static
     */
    public function flush($tag) {
        $args = func_get_args();
        foreach ($args as $item) {
            if (in_array($item, $this->sequence)) {
                $this->$item = [];
            }
        }
        return $this;
    }

    public function load(array $args = []) {
        if (empty($args)) {
            return $this;
        }
        foreach ($args as $key => $item) {
            $tag = strtolower(is_integer($key) ? array_shift($item) : $key);
            if (!in_array($tag, $this->sequence) || empty($item)) {
                continue;
            }
            $this->$tag($item);
        }
        return $this;
    }

    public function groupBy(string $column, ...$args): SqlBuilder {
        foreach (func_get_args() as $group) {
            $this->groups = array_merge(
                (array) $this->groups,
                (array)$group
            );
        }
        return $this;
    }

    public function group($args) {
        return call_user_func_array([$this, 'groupBy'], func_get_args());
    }


    /**
     * 起别名
     * @param string $key
     * @return static
     */
    public function alias(string $key): SqlBuilder {
        if (count($this->from) == 1) {
            $this->from = array($key => current($this->from));
        }
        return $this;
    }

    /**
     * ORDER SQL
     * @param array|string $column
     * @param string $order
     * @param mixed ...$args
     * @return Builder
     */
    public function orderBy(array|string $column, string $order = '', ...$args): SqlBuilder {
        $args = !is_array($column) ? func_get_args() : $column;
        // 把关联数组变成 1，asc
        foreach ($args as $key => $item) {
            if (!is_integer($key)) {
                if (is_array($item)) {
                    //'asc' => ['a', 'b']
                    foreach ($item as $value) {
                        $this->orders[] = $value;
                        $this->orders[] = $key;
                    }
                    continue;
                }
                // 'a' => 'b'
                $this->orders[] = $key;
                $this->orders[] = $item;
                continue;
            }
            if (is_array($item)) {
                // ['a', 'asc']
                $this->orders[] = $item[0];
                $this->orders[] = $item[1];
                continue;
            }
            $this->orders[] = $item;
        }
        return $this;
    }

    public function union(SqlBuilder|string $sql, bool $all = false): SqlBuilder {
        $this->unions[] = ['query' => $sql, 'all' => $all];
        return $this;
    }

    /**
     * @param string|array $table
     * @param string $alias
     * @return static
     */
    public function from(string|array $table, string $alias = ''): SqlBuilder {
        if (!is_array($table)) {
            $table = func_get_args();
        }
        $this->from = array_merge($this->from, $table);
        return $this;
    }

    /**
     * Pass the query to a given callback.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function tap(Closure $callback): SqlBuilder {
        return $this->when(true, $callback);
    }

    /**
     * 条件语句
     * @param bool $condition
     * @param Closure $trueFunc
     * @param Closure|null $falseFunc
     * @return $this
     */
    public function when($condition, Closure $trueFunc, Closure|null $falseFunc = null): SqlBuilder {
        if ($this->isEmpty) {
            return $this;
        }
        if ($condition) {
            $trueFunc($this);
            return $this;
        }
        if (!empty($falseFunc)) {
            $falseFunc($this);
        }
        return $this;
    }

    public function take(int $value): SqlBuilder {
        return $this->limit($value);
    }

    public function limit(string|int $offset, int $length = 0): SqlBuilder {
        if (is_string($offset) && str_contains($offset, ',')) {
            list($offset, $length) = explode(',', $offset);
        }
        if (empty($length)) {
            $this->limit = $offset;
            return $this;
        }
        $this->limit = $length;
        return $this->offset((int)$offset);
    }

    public function offset(int $offset): SqlBuilder {
        $this->offset = $offset;
        return $this;
    }


    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings(): array {
        $keys = func_num_args() < 1 ? array_keys($this->bindings) : func_get_args();
        $args = [];
        foreach ($keys as $item) {
            if (!array_key_exists($item, $this->bindings)) {
                continue;
            }
            $args = array_merge($args, $this->bindings[$item]);
        }
        return $args;
    }

    /**
     * 获取原始数组
     * @return array
     */
    public function getRawBindings() {
        return $this->bindings;
    }

    /**
     * Set the bindings on the query builder.
     *
     * @param array $bindings
     * @param string $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function setBindings(array $bindings, $type = 'where') {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException(
                __('Invalid binding type: ').$type
            );
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Add a binding to the query.
     *
     * @param mixed $value
     * @param string $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function addBinding($value, string $type = 'where'): SqlBuilder {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException(
                __('Invalid binding type: ').$type
            );
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    /**
     * Merge an array of bindings into our bindings.
     *
     * @param  Builder  $query
     * @return $this
     */
    public function mergeBindings(Builder $query) {
        $this->bindings = array_merge_recursive($this->bindings, $query->bindings);

        return $this;
    }

    /**
     * Remove all of the expressions from a list of bindings.
     *
     * @param  array  $bindings
     * @return array
     */
    protected function cleanBindings(array $bindings) {
        return array_values(array_filter($bindings, function ($binding) {
            return ! $binding instanceof Expression;
        }));
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getSQL(): string {
        return DB::grammar()->compileQuery($this);
    }

    public function newBuilder(): SqlBuilder {
        return new static();
    }
}