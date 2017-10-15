<?php
namespace Zodream\Database\Query;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/6/25
 * Time: 9:38
 */
use Zodream\Database\Query\Components\WhereBuilder;
use Zodream\Database\Query\Converters\PdoConverter;
use Zodream\Database\Schema\BaseSchema;
use Closure;
use InvalidArgumentException;

abstract class BaseQuery extends BaseSchema  {

    use WhereBuilder, PdoConverter;

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

    public $wheres = array();

    public $from = array();

    public $limit;

    public $offset;

    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'in', 'not in', 'is', 'is not',
        'like', 'like binary', 'not like', 'between', 'not between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to'
    );

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
     * @param  array   $bindings
     * @param  string  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setBindings(array $bindings, $type = 'where') {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Add a binding to the query.
     *
     * @param  mixed   $value
     * @param  string  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function addBinding($value, $type = 'where') {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
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
     * @param  BaseQuery  $query
     * @return $this
     */
    public function mergeBindings(BaseQuery $query) {
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

    public function getSql() {
        return $this->compileQuery();
    }
}