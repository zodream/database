<?php
declare(strict_types=1);
namespace Zodream\Database\Query\Components;

use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\Query\Builder;

trait JoinBuilder {

    public array $joins = [];

    /**
     * Add a join clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @param  string  $type
     * @return $this
     */
    public function join(string $table, $first, $operator = null, $second = null, string $type = 'inner'): SqlBuilder {
        $on = $first;
        if (!empty($operator)) {
            $on .= is_null($second) ? ' = '.$operator : sprintf(' %s %s', $operator, $second);
        }
        $this->joins[] = compact('table', 'on', 'type');
        return $this;
    }

    /**
     * Add a left join to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return Builder|static
     */
    public function leftJoin(string $table, $first, $operator = null, $second = null): SqlBuilder {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function left($table, $first, $operator = null, $second = null) {
        return $this->leftJoin($table, $first, $operator, $second);
    }

    /**
     * Add a right join to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return Builder|static
     */
    public function rightJoin(string $table, $first, $operator = null, $second = null): SqlBuilder {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function right($table, $first, $operator = null, $second = null) {
        return $this->rightJoin($table, $first, $operator, $second);
    }

    /**
     * Add a "cross join" clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return Builder|static
     */
    public function crossJoin(string $table, $first, $operator = null, $second = null): SqlBuilder {
        return $this->join($table, $first, $operator, $second, 'cross');
    }

    public function cross($table, $first = null, $operator = null, $second = null) {
        return $this->crossJoin($table, $first, $operator, $second);
    }

    public function inner($table, $first = null, $operator = null, $second = null) {
        return $this->innerJoin($table, $first, $operator, $second);
    }

    public function innerJoin(string $table, $first, $operator = null, $second = null): SqlBuilder {
        return $this->join($table, $first, $operator, $second);
    }


}