<?php
declare(strict_types=1);
namespace Zodream\Database\Contracts;

use Closure;
use Zodream\Html\Page;

interface SqlBuilder {

    public function table(string $table): SqlBuilder;
    public function getTable(): string;

    public function alias(string $key): SqlBuilder;
    public function select(array|string $column = '*', ...$args): SqlBuilder;
    public function selectRaw($expression, array $bindings = []): SqlBuilder;

    public function from(string|array $table, string $alias = ''): SqlBuilder;

    public function join(string $table, $first, $operator = null, $second = null, string $type = 'inner'): SqlBuilder;
    public function leftJoin(string $table, $first, $operator = null, $second = null): SqlBuilder;
    public function rightJoin(string $table, $first, $operator = null, $second = null): SqlBuilder;
    public function innerJoin(string $table, $first, $operator = null, $second = null): SqlBuilder;
    public function crossJoin(string $table, $first, $operator = null, $second = null): SqlBuilder;

    public function where($column, $operator = null, $value = null, string $boolean = 'and'): SqlBuilder;
    public function orWhere($column, $operator = null, $value = null): SqlBuilder;
    public function whereRaw(SqlBuilder|string $sql, array $bindings = [], string $boolean = 'and'): SqlBuilder;
    public function orWhereRaw(SqlBuilder|string$sql, array $bindings = []): SqlBuilder;
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): SqlBuilder;
    public function orWhereIn(string $column, array $values): SqlBuilder;
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): SqlBuilder;
    public function orWhereNull(string $column): SqlBuilder;
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): SqlBuilder;
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): SqlBuilder;
    public function orWhereBetween(string $column, array $values): SqlBuilder;
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): SqlBuilder;
    public function orWhereNotBetween(string $column, array $values): SqlBuilder;
    public function whereNotNull(string $column, string $boolean = 'and'): SqlBuilder;
    public function orWhereNotNull(string $column): SqlBuilder;

    public function having($column, $operator = null, $value = null, string $boolean = 'and'): SqlBuilder;
    public function orHaving($column, $operator = null, $value = null): SqlBuilder;


    public function orderBy(array|string $column, string $order = '', ...$args): SqlBuilder;
    public function groupBy(string $column, ...$args): SqlBuilder;
    public function union(SqlBuilder|string $sql, bool $all = false): SqlBuilder;
    public function limit(string|int $offset, int $length = 0): SqlBuilder;
    public function offset(int $offset): SqlBuilder;
    public function take(int $length): SqlBuilder;

    public function tap(Closure $cb): SqlBuilder;
    public function when(bool $condition, Closure $trueFunc, Closure $falseFunc = null): SqlBuilder;
    public function isEmpty(): SqlBuilder;

    /**
     * 声明结果为数组
     * @return SqlBuilder
     */
    public function asArray(): SqlBuilder;

    public function count(string $column = '*'): int;
    public function max(string $column);
    public function min(string $column);
    public function avg(string $column): int|float;
    public function sum(string $column): int|float;

    public function scalar();
    public function pluck(?string $column = null, ?string $key = null): array;
    public function value(string $column);
    public function first(...$columns);
    public function get(...$columns);
    public function page(int $perPage = 20, string $pageKey = 'page', int $page = -1): Page;
    public function each(callable $cb, ...$columns): array;

    public function insert(array|string $columns = '', array|string $values = ''): int|string;
    public function update(array $data): int;
    public function updateBool(string $column): int;
    public function updateIncrement(string|array $column, int|float $value = 1): int;
    public function updateDecrement(string|array $column, int|float $value = 1): int;
    public function replace(array $data): int;
    public function delete(): int;

    public function addBinding($value, string $type = 'where'): SqlBuilder;
    public function getSQL(): string;

}