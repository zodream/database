<?php
namespace Zodream\Database\Query\Components;

use Zodream\Database\Query\Query;

trait JoinBuilder {

    public $joins = [];

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
    public function join($table, $first, $operator = null, $second = null, $type = 'inner') {
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
     * @return Query|static
     */
    public function leftJoin($table, $first, $operator = null, $second = null) {
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
     * @return Query|static
     */
    public function rightJoin($table, $first, $operator = null, $second = null) {
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
     * @return Query|static
     */
    public function crossJoin($table, $first = null, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'cross');
    }

    public function cross($table, $first = null, $operator = null, $second = null) {
        return $this->crossJoin($table, $first, $operator, $second, 'cross');
    }

    public function inner($table, $first = null, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second);
    }


}