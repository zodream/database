<?php
namespace Zodream\Database\Query\Components;

use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\Query\Expression;
use Zodream\Database\Query\Builder;
use Zodream\Helpers\Str;
use Closure;
use Zodream\Infrastructure\Contracts\ArrayAble;

trait WhereBuilder {

    public $wheres = [];

    public function where($column, $operator = null, $value = null, string $boolean = 'and'): SqlBuilder {
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        if (func_num_args() < 3 || $this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator != '=');
        }

        if (Str::contains($column, '->') && is_bool($value)) {
            $value = new Expression($value ? 'true' : 'false');
        }
        $type = 'Basic';
        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );
        if (! $value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    protected function whereSub($column, $operator, Closure $callback, $boolean) {
        if ($this->isEmpty) {
            return $this;
        }
        $type = 'Sub';

        // Once we have the query instance we can simply execute it so it can add all
        // of the sub-select's conditions to itself, and then we can cache it off
        // in the array of where clauses for the "main" parent query instance.
        call_user_func($callback, $query = $this->newBuilder());

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'query', 'boolean'
        );

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    protected function addArrayOfWheres($column, $boolean, $method = 'where') {
        return $this->whereNested(function ($query) use ($column, $method) {
            foreach ($column as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $query->{$method}(...array_values($value));
                } else {
                    $query->$method($key, '=', $value);
                }
            }
        }, $boolean);
    }

    protected function invalidOperator($operator) {
        return !is_string($operator) || !in_array(strtolower($operator), Builder::OPERATORS, true);
    }

    public function orWhere($column, $operator = null, $value = null): SqlBuilder {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and') {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($first)) {
            return $this->addArrayOfWheres($first, $boolean, 'whereColumn');
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($second, $operator) = [$operator, '='];
        }

        // Finally, we will add this where clause into this array of clauses that we
        // are building for the query. All of them will be compiled via a grammar
        // once the query is about to be executed and run against the database.
        $type = 'Column';

        $this->wheres[] = compact(
            'type', 'first', 'operator', 'second', 'boolean'
        );

        return $this;
    }

    /**
     * Add an "or where" clause comparing two columns to the query.
     *
     * @param  string|array  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @return Builder|static
     */
    public function orWhereColumn($first, $operator = null, $second = null) {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /**
     * Add a raw where clause to the query.
     *
     * @param  string  $sql
     * @param  mixed   $bindings
     * @param  string  $boolean
     * @return $this
     */
    public function whereRaw(SqlBuilder|string $sql, array $bindings = [], string $boolean = 'and'): SqlBuilder {
        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];

        $this->addBinding((array) $bindings, 'where');

        return $this;
    }

    /**
     * Add a raw or where clause to the query.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @return Builder|static
     */
    public function orWhereRaw(SqlBuilder|string$sql, array $bindings = []): SqlBuilder {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): SqlBuilder {
        $type = $not ? 'NotIn' : 'In';

        // If the value is a query builder instance we will assume the developer wants to
        // look for any values that exists within this given query. So we will add the
        // query accordingly so that this query is properly executed when it is run.
        if ($values instanceof static) {
            return $this->whereInExistingQuery(
                $column, $values, $boolean, $not
            );
        }

        // If the value of the where in clause is actually a Closure, we will assume that
        // the developer is using a full sub-select for this "in" statement, and will
        // execute those Closures, then we can re-construct the entire sub-selects.
        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, $boolean, $not);
        }

        // Next, if the value is Arrayable we need to cast it to its raw array form so we
        // have the underlying array value instead of an Arrayable object which is not
        // able to be added as a binding, etc. We will then add to the wheres array.
        if ($values instanceof ArrayAble) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        // Finally we'll add a binding for each values unless that value is an expression
        // in which case we will just skip over it since it will be the query as a raw
        // string and not as a parameterized place-holder to be replaced by the PDO.
        foreach ($values as $value) {
            if (! $value instanceof Expression) {
                $this->addBinding($value, 'where');
            }
        }

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return Builder|static
     */
    public function orWhereIn(string $column, array $values): SqlBuilder {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @return Builder|static
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): SqlBuilder {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return Builder|static
     */
    public function orWhereNotIn($column, $values) {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Add a where in with a sub-select to the query.
     *
     * @param  string $column
     * @param Closure $callback
     * @param  string $boolean
     * @param  bool $not
     * @return $this
     */
    protected function whereInSub($column, Closure $callback, $boolean, $not) {
        $type = $not ? 'NotInSub' : 'InSub';

        // To create the exists sub-select, we will actually create a query and call the
        // provided callback with the query so the developer may set any of the query
        // conditions they want for the in clause, then we'll put it in this array.
        call_user_func($callback, $query = $this->newBuilder());

        $this->wheres[] = compact('type', 'column', 'query', 'boolean');

        $this->addBinding($this->getBindings(), 'where');

        return $this;
    }

    /**
     * Add an external sub-select to the query.
     *
     * @param  string   $column
     * @param  Builder|static  $query
     * @param  string   $boolean
     * @param  bool     $not
     * @return $this
     */
    protected function whereInExistingQuery($column, $query, $boolean, $not) {
        $type = $not ? 'NotInSub' : 'InSub';

        $this->wheres[] = compact('type', 'column', 'query', 'boolean');

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): SqlBuilder {
        $type = $not ? 'NotNull' : 'Null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * Add an "or where null" clause to the query.
     *
     * @param  string  $column
     * @return Builder|static
     */
    public function orWhereNull(string $column): SqlBuilder {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return SqlBuilder|static
     */
    public function whereNotNull(string $column, string $boolean = 'and'): SqlBuilder {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): SqlBuilder {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'not');

        $this->addBinding($values, 'where');

        return $this;
    }

    /**
     * Add an or where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @return Builder|static
     */
    public function orWhereBetween(string $column, array $values): SqlBuilder {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Add a where not between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return Builder|static
     */
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): SqlBuilder {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add an or where not between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @return Builder|static
     */
    public function orWhereNotBetween(string $column, array $values): SqlBuilder {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * Add an "or where not null" clause to the query.
     *
     * @param  string  $column
     * @return Builder|static
     */
    public function orWhereNotNull(string $column): SqlBuilder {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add a nested where statement to the query.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return Builder|static
     */
    public function whereNested(Closure $callback, $boolean = 'and') {
        if ($this->isEmpty) {
            return $this;
        }
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }


    /**
     * Create a new query instance for nested where condition.
     *
     * @return Builder
     */
    public function forNestedWhere() {
        return (new static())->from($this->from);
    }

    /**
     * Add another query builder as a nested where to the query builder.
     *
     * @param  Builder|static $query
     * @param  string  $boolean
     * @return $this
     */
    public function addNestedWhereQuery($query, $boolean = 'and') {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getBindings(), 'where');
        }

        return $this;
    }

    public function having($column, $operator = null, $value = null, string $boolean = 'and'): SqlBuilder {
        $type = 'Basic';

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        $this->having[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'having');
        }

        return $this;
    }

    /**
     * Add a "or having" clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     * @return static
     */
    public function orHaving($column, $operator = null, $value = null): SqlBuilder {
        return $this->having($column, $operator, $value, 'or');
    }
}