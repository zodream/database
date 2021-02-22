<?php
declare(strict_types = 1);
namespace Zodream\Database\Adapters\MySql;

use Zodream\Database\Concerns\BuilderGrammar as GrammarInterface;
use Zodream\Database\Concerns\SqlBuilder;
use Zodream\Database\Query\Builder;
use Zodream\Database\Query\Expression;
use Zodream\Helpers\Arr;

class BuilderGrammar implements GrammarInterface {
    public function compileSelects(Builder $query): string {
        return 'SELECT '.$this->getField($query->selects);
    }

    /**
     *
     * 关键字 DISTINCT 唯一 AVG() COUNT() FIRST() LAST() MAX()  MIN() SUM() UCASE() 大写  LCASE()
     * MID(column_name,start[,length]) 提取字符串 LEN() ROUND() 舍入 NOW() FORMAT() 格式化
     * @param $selects
     * @return string
     */
    protected function getField($selects): string {
        if (empty($selects)) {
            return '*';
        }
        $result = array();
        foreach ((array)$selects as $key => $item) {
            if (is_integer($key)) {
                $result[] = $item;
            } else {
                $result[] = "{$item} AS {$key}";
            }
        }
        return implode(',', $result);
    }

    public function compileFrom(Builder $query): string {
        if (empty($query->from)) {
            return '';
        }
        $result = array();
        foreach ($query->from as $key => $item) {
            if (is_integer($key)) {
                $result[] = $query->addPrefix($item);
                continue;
            }
            if ($item instanceof Builder) {
                $result[] = '('.$item->getSql().') ' .$key;
                continue;
            }
            $result[] = $query->addPrefix($item).' ' .$key;
        }
        return ' FROM '.implode(',', $result);
    }

    /**
     * 支持多个相同的left [$table, $where, ...]
     * @param Builder $query
     * @return string
     */
    public function compileJoins(Builder $query): string {
        if (empty($query->joins)) {
            return '';
        }
        $sql = '';
        foreach ($query->joins as $item) {
            $sql .= sprintf(' %s JOIN %s ON %s',
                strtoupper($item['type']),
                $query->addPrefix($item['table']),
                $item['on']);
        }
        return $sql;
    }

    public function compileWheres(Builder $query): string {
        if (empty($query->wheres)) {
            return '';
        }
        $args = [];
        foreach ($query->wheres as $where) {
            $args[] = $where['boolean'].' '.$this->{"compileWhere{$where['type']}"}($where);
        }
        return ' WHERE '.$this->removeLeadingBoolean(implode(' ', $args));
    }

    protected function compileWhereBasic($where): string {
        return $where['column'].' '.$where['operator'].' ?';
    }

    protected function compileWhereColumn($where): string {
        return $where['first'].' '.$where['operator'].' '.$where['second'];
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereNull($where): string {
        return $where['column'].' is null';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereIn($where): string {
        if (! empty($where['values'])) {
            return $where['column'].' in ('.$this->parameterize($where['values']).')';
        }

        return '0 = 1';
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereNotIn($where): string {
        if (! empty($where['values'])) {
            return $where['column'].' not in ('.$this->parameterize($where['values']).')';
        }

        return '1 = 1';
    }


    /**
     * Compile a "where not null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereNotNull($where): string {
        return $where['column'].' is not null';
    }

    /**
     * Compile a "between" where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereBetween($where): string {
        $between = $where['not'] ? 'not between' : 'between';

        return $where['column'].' '.$between.' ? and ?';
    }

    /**
     * Compile a raw where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereRaw($where): string {
        return $where['sql'];
    }

    /**
     * Compile a nested where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereNested($where): string {
        // Here we will calculate what portion of the string we need to remove. If this
        // is a join clause query, we need to remove the "on" portion of the SQL and
        // if it is a normal query we need to take the leading "where" of queries.
        $offset = 6;

        return '('.substr($this->compileWheres($where['query']), $offset).')';
    }

    /**
     * @param Builder $query
     * @return string
     */
    protected function compileUnions(Builder $query): string {
        if (empty($query->unions)) {
            return '';
        }
        $sql = ' ';
        foreach ($query->unions as $item) {
            $sql .= 'UNION ';
            if ($item['all']) {
                $sql .= 'ALL ';
            }
            if ($item['query'] instanceof Builder) {
                $sql .= $item['query']->getSql();
                continue;
            }
            if (is_array($item['query'])) {
                $sql .= (new Builder())->load($item['query'])->getSql();
            }
            $sql .= $item['query'];
        }
        return $sql;
    }

    /**
     * Compile the "having" portions of the query.
     *
     * @param Builder $query
     * @return string
     */
    protected function compileHavings(Builder $query): string {
        if (empty($query->having)) {
            return '';
        }
        $sql = implode(' ', array_map([$this, 'compileHaving'], $query->having));
        return ' HAVING '.$this->removeLeadingBoolean($sql);
    }

    /**
     * Compile a single having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileHaving(array $having): string {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        if ($having['type'] === 'Raw') {
            return $having['boolean'].' '.$having['sql'];
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileBasicHaving($having): string {
        $column = $having['column'];

        $parameter = $this->parameter($having['value']);

        return $having['boolean'].' '.$column.' '.$having['operator'].' '.$parameter;
    }



    protected function compileGroups(Builder $query): string {
        if (empty($query->groups)) {
            return '';
        }
        return ' GROUP BY '.implode(',', (array)$query->groups);
    }

    protected function compileOrders(Builder $query): string {
        if (empty($query->orders)) {
            return '';
        }
        $result = array();
        for ($i = 0, $length = count($query->orders); $i < $length; $i ++) {
            $sql = $query->orders[$i];
            if ($i < $length - 1 && in_array(strtolower($query->orders[$i + 1]),
                    array('asc', 'desc')) ) {
                $sql .= ' '.strtoupper($query->orders[$i + 1]);
                $i ++;
            }
            $result[] = $sql;
        }
        return ' ORDER BY '.implode(',', $result);
    }

    protected function compileLimit(Builder $query): string {
        if (empty($query->limit)) {
            return '';
        }
        $param = (array)$query->limit;
        if (count($param) == 1) {
            return " LIMIT {$param[0]}";
        }
        $param[0] = intval($param[0]);
        $param[1] = intval($param[1]);
        if ($param[0] < 0) {
            $param[0] = 0;
        }
        return " LIMIT {$param[0]},{$param[1]}";
    }

    protected function compileOffset(Builder $query): string {
        if (empty($query->offset)) {
            return '';
        }
        return ' OFFSET '.intval($query->offset);
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string  $seed
     * @return string
     */
    public function compileRandom($seed) {
        return ' RAND('.$seed.')';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     * @return string
     */
    public function compileLock($value) {
        if (! is_string($value)) {
            return $value ? ' for update' : ' lock in share mode';
        }

        return $value;
    }

    /**
     * @param Builder $query
     * @return string
     */
    public function compileSelect(Builder $query) {
        $sql = $this->compileSelects($query).
            $this->compileFrom($query).
            $this->compileJoins($query).
            $this->compileWheres($query).
            $this->compileGroups($query).
            $this->compileHavings($query).
            $this->compileOrders($query).
            $this->compileLimit($query).
            $this->compileOffset($query);
        return $sql;
    }

    public function compileInsert(Builder $query, $columns = null, $values = null): string {
        $table = $query->getTable();
        if (empty($columns)) {
            return sprintf('INSERT INTO %s (%s)', $table, is_null($values) ? 'NULL' : $values);
        }
        if (is_array($columns)) {
            if (is_array(reset($columns))) {
                list($values, $columns) = [$columns, array_keys(reset($columns))];
            } elseif (is_null($values)) {
                list($values, $columns) = [$columns, array_keys($columns)];
            }
            if (is_array($values) && Arr::isAssoc(reset($values))){
                // 如果值是关联数组则接着下来会进行排序
                sort($columns);
            }
        }
        if (is_array($columns)) {
            $columns = implode('`,`', $columns);
        }
        return $this->compileInsertColumns($query, $table, $columns, $values);
    }

    protected function compileInsertColumns(Builder $query, $table, $columns, $values) {
        if (!is_array($values)) {
            return sprintf('INSERT INTO %s (%s) VALUES %s', $table, $columns,
                $values);
        }
        if (!is_array(reset($values))) {
            return sprintf('INSERT INTO %s (`%s`) VALUES %s', $table, $columns,
                $this->compileInsertColumn($query, $values));
        }
        $args = [];
        $others = []; // 其他不规则的单独处理
        $column_count = count(explode(',', $columns));
        foreach ($values as $item) {
            if (count($item) != $column_count) {
                $others[] = $item;
                continue;
            }
            ksort($item);
            $args[] = $this->compileInsertColumn($query, $item);
        }
        $parameters = implode(', ', $args);
        $insert_columns = [
            "insert into $table (`$columns`) values $parameters"
        ];
        if (!empty($others)) {
            $insert_columns[] = $this->compileInsert($query, $others);
        }
        return implode(';', $insert_columns);
    }

    /**
     * @param Builder $query
     * @param array $item
     * @return string
     */
    public function compileInsertColumn(Builder $query, $item) {
        $arg = [];
        foreach ($item as $value) {
            if (is_null($value)) {
                $arg[] = 'NULL';
                continue;
            }
            if (is_bool($value)) {
                $arg[] = intval($value);
                continue;
            }
            if (is_string($value)) {
                $arg[] = '?';
                $query->addBinding($value);
                continue;
            }
            if (is_array($value) || is_object($value)) {
                $arg[] = '?';
                $query->addBinding(serialize($value));
                continue;
            }
            $arg[] = $value;
        }
        return '(' . implode(', ', $arg) . ')';
    }

    public function compileUpdate(Builder $query, array $values): string {
        $table = $query->getTable();
        $data = [];
        $parameters = array();
        foreach ($values as $key => $value) {
            if (is_integer($key)) {
                $data[] = $value;
                continue;
            }
            $data[] = "`{$key}` = ?";
            $parameters[] = $value;
        }
        $columns = implode(',', $data);
        $query->addBinding($parameters, 'join');

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        $joins = '';

        if (isset($query->joins)) {
            $joins = $this->compileJoins($query);
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $where = $this->compileWheres($query);

        $sql = rtrim("update {$table}{$joins} set $columns $where");

        // If the query has an order by clause we will compile it since MySQL supports
        // order bys on update statements. We'll compile them using the typical way
        // of compiling order bys. Then they will be appended to the SQL queries.
        if (! empty($query->orders)) {
            $sql .= $this->compileOrders($query);
        }

        // Updates on MySQL also supports "limits", which allow you to easily update a
        // single record very easily. This is not supported by all database engines
        // so we have customized this update compiler here in order to add it in.
        if (isset($query->limit)) {
            $sql .= $this->compileLimit($query);
        }

        return rtrim($sql);
    }

    public function compileDelete(Builder $query): string {
        $table = $query->getTable();

        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return !empty($query->joins)
            ? $this->compileDeleteWithJoins($query, $table, $where)
            : $this->compileDeleteWithoutJoins($query, $table, $where);
    }

    /**
     * Compile a delete query that does not use joins.
     *
     * @param Builder $query
     * @param  string $table
     * @param  string $where
     * @return string
     */
    protected function compileDeleteWithoutJoins(Builder $query, $table, $where) {
        $sql = trim("delete from {$table} {$where}");

        // When using MySQL, delete statements may contain order by statements and limits
        // so we will compile both of those here. Once we have finished compiling this
        // we will return the completed SQL statement so it will be executed for us.
        if (! empty($query->orders)) {
            $sql .= $this->compileOrders($query);
        }

        if (isset($query->limit)) {
            $sql .= $this->compileLimit($query);
        }
        return $sql;
    }

    /**
     * Compile a delete query that uses joins.
     *
     * @param Builder $query
     * @param  string $table
     * @param  string $where
     * @return string
     */
    protected function compileDeleteWithJoins(Builder $query, $table, $where) {
        $joins = $this->compileJoins($query);

        $alias = strpos(strtolower($table), ' as ') !== false
            ? explode(' as ', $table)[1] : $table;

        return trim("delete {$alias} from {$table}{$joins} {$where}");
    }

    /**
     * Remove the leading boolean from a statement.
     *
     * @param  string  $value
     * @return string
     */
    protected function removeLeadingBoolean($value) {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    public function parameterize(array $values) {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param  mixed   $value
     * @return string
     */
    public function parameter($value) {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    /**
     * Determine if the given value is a raw expression.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function isExpression($value) {
        return $value instanceof Expression;
    }

    /**
     * Get the value of a raw expression.
     *
     * @param  Expression  $expression
     * @return string
     */
    public function getValue($expression) {
        return $expression->getValue();
    }

    public function compileQuery(SqlBuilder $builder): string
    {
        // TODO: Implement compileQuery() method.
    }

    public function compileInsertOrUpdate(SqlBuilder|string $builder, array $insertData, array $updateData): string
    {
        // TODO: Implement compileInsertOrUpdate() method.
    }

    public function compileInsertOrReplace(SqlBuilder|string $builder, array $data): string
    {
        // TODO: Implement compileInsertOrReplace() method.
    }

    public function cacheable(string $sql): bool
    {
        // TODO: Implement cacheable() method.
    }
}