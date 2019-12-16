<?php
declare(strict_types = 1);

namespace Zodream\Database\Query;


use Zodream\Database\Grammars\Grammar;
use Zodream\Database\Query\Components\ExecBuilder;
use Zodream\Database\Query\Components\JoinBuilder;
use Zodream\Database\Query\Components\RecordBuilder;
use Zodream\Database\Query\Components\SelectBuilder;
use Zodream\Database\Query\Components\WhereBuilder;
use Zodream\Database\Schema\BaseSchema;
use Closure;
use InvalidArgumentException;

class Builder extends BaseSchema {

    use SelectBuilder, JoinBuilder, WhereBuilder, ExecBuilder, RecordBuilder;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    protected $bindings = [
        'select' => [],
        'join'   => [],
        'where'  => [],
        'having' => [],
        'order'  => [],
        'union'  => [],
    ];

    public $from = [];

    public $limit;

    public $offset;

    public $groups = [];

    public $having = [];

    public $orders = [];

    public $unions = [];

    protected $sequence = [
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

    protected $operators = [
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
    protected $isEmpty = false;

    public function __construct($args = array()) {
        $this->load($args);
    }

    public function isEmpty() {
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

    public function load($args = []) {
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

    public function groupBy($groups) {
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
    public function alias($key) {
        if (count($this->from) == 1) {
            $this->from = array($key => current($this->from));
        }
        return $this;
    }

    /**
     * ORDER SQL
     * @param array|string $args
     * @return Builder
     */
    public function orderBy($args) {
        if (!is_array($args)) {
            $args = func_get_args();
        }
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

    public function union($sql, $all = false) {
        $this->unions[] = ['query' => $sql, 'all' => $all];
        return $this;
    }

    /**
     * @param string|array $tables
     * @return static
     */
    public function from($tables) {
        if (!is_array($tables)) {
            $tables = func_get_args();
        }
        $this->from = array_merge($this->from, $tables);
        return $this;
    }

    /**
     * Pass the query to a given callback.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function tap($callback) {
        return $this->when(true, $callback);
    }

    /**
     * 条件语句
     * @param bool $condition
     * @param Closure $trueFunc
     * @param Closure|null $falseFunc
     * @return $this
     */
    public function when($condition, Closure $trueFunc, Closure $falseFunc = null) {
        if ($condition) {
            $trueFunc($this);
            return $this;
        }
        if (!empty($falseFunc)) {
            $falseFunc($this);
        }
        return $this;
    }

    public function take($value) {
        return $this->limit($value);
    }

    public function limit($limit, $length = null) {
        if (is_string($limit) && strpos($limit, ',') !== false) {
            list($limit, $length) = explode(',', $limit);
        }
        if (empty($length)) {
            $this->limit = $limit;
            return $this;
        }
        $this->limit = $length;
        return $this->offset($limit);
    }

    public function offset($offset) {
        $this->offset = intval($offset);
        return $this;
    }


    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings() {
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
    public function addBinding($value, $type = 'where') {
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
    public function getSql(): string {
        return $this->grammar()->compileSelect($this);
    }

    /**
     * @return Grammar
     * @throws \Exception
     */
    protected function grammar() {
        return $this->command()->grammar();
    }
}