<?php
declare(strict_types=1);
namespace Zodream\Database\Query\Components;

use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\Query\Builder;
use Zodream\Database\Utils;

trait SelectBuilder {

    public array $selects = [];


    /**
     * @param string|array $field
     * @return static
     */
    public function select(array|string $field = '*', ...$args): SqlBuilder {
        if (func_num_args() < 1) {
            return $this->andSelect($field);
        }
        if (!is_array($field)) {
            $field = func_get_args();
        }
        return $this->andSelect($field);
    }

    public function appendSelect($field) {
        if (empty($this->selects)) {
            $this->selects[] = '*';
        }
        return $this->andSelect(...func_get_args());
    }

    public function andSelect($field = '*') {
        if (!is_array($field)) {
            $field = func_get_args();
        }
        foreach ($field as $key => $value) {
            if (!is_int($key)) {
                $this->selects[] = $value. ' AS '.$key;
                continue;
            }
            if (is_null($value)) {
                continue;
            }
            $this->selects[] = $value;
        }
        return $this;
    }

    public function selectRaw($expression, array $bindings = []): SqlBuilder {
        $this->selects[] = $expression;
        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    public function selectSub($query, $as) {
        // If the given query is a Closure, we will execute it while passing in a new
        // query instance to the Closure. This will give the developer a chance to
        // format and work with the query before we cast it to a raw SQL string.
        if ($query instanceof \Closure) {
            $callback = $query;

            $callback($query = new static());
        }

        // Here, we will parse this query into an SQL string and an array of bindings
        // so we can add it to the query builder using the selectRaw method so the
        // query is included in the real SQL generated by this builder instance.
        list($query, $bindings) = $this->parseSubSelect($query);

        return $this->selectRaw(
            '('.$query.') as '.$as, $bindings
        );
    }

    /**
     * Parse the sub-select query into SQL and bindings.
     *
     * @param mixed $query
     * @return array
     * @throws \Exception
     */
    protected function parseSubSelect($query) {
        if ($query instanceof Builder) {
            $query->selects = [$query->selects[0]];

            return [$query->getSQL(), $query->getBindings()];
        } elseif (is_string($query)) {
            return [$query, []];
        } else {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * 统计
     * @param string $column
     * @return integer
     * @throws \Exception
     */
    public function count(string $column = '*'): int {
        return (int)$this->selectFuncName(__FUNCTION__, $column)->scalar();
    }

    /**
     * 最大值
     * @param $column
     * @return bool|string
     * @throws \Exception
     */
    public function max(string $column)  {
        return $this->selectFuncName(__FUNCTION__, $column)->scalar();
    }

    /**
     * 最小值
     * @param $column
     * @return bool|int|string
     * @throws \Exception
     */
    public function min(string $column)  {
        return $this->selectFuncName(__FUNCTION__, $column)->scalar();
    }

    /**
     * 平均值
     * @param $column
     * @return bool|int|string
     * @throws \Exception
     */
    public function avg(string $column): int|float  {
        return Utils::formatNumeric($this->selectFuncName(__FUNCTION__, $column)->scalar());
    }

    /**
     * 总和
     * @param $column
     * @return int|float|double
     * @throws \Exception
     */
    public function sum(string $column): int|float  {
        return Utils::formatNumeric($this->selectFuncName(__FUNCTION__, $column)->scalar());
    }

    /**
     * @param string $name
     * @param string $column
     * @return $this
     */
    protected function selectFuncName($name, $column) {
        $this->selects[] = "{$name}({$column}) AS {$name}";
        return $this;
    }
}