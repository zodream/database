<?php
namespace Zodream\Database\Query\Converters;

use Zodream\Database\Query\Expression;
use Zodream\Database\Query\Query;

trait PdoConverter {

    protected function compileSelects() {
        return 'SELECT '.$this->getField();
    }

    /**
     *
     * 关键字 DISTINCT 唯一 AVG() COUNT() FIRST() LAST() MAX()  MIN() SUM() UCASE() 大写  LCASE()
     * MID(column_name,start[,length]) 提取字符串 LEN() ROUND() 舍入 NOW() FORMAT() 格式化
     * @return string
     */
    protected function getField() {
        if (empty($this->selects)) {
            return '*';
        }
        $result = array();
        foreach ((array)$this->selects as $key => $item) {
            if (is_integer($key)) {
                $result[] = $item;
            } else {
                $result[] = "{$item} AS {$key}";
            }
        }
        return implode($result, ',');
    }

    protected function compileFrom() {
        if (empty($this->from)) {
            return null;
        }
        $result = array();
        foreach ($this->from as $key => $item) {
            if (is_integer($key)) {
                $result[] = $this->addPrefix($item);
                continue;
            }
            if ($item instanceof Query) {
                $result[] = '('.$item->getSql().') ' .$key;
                continue;
            }
            $result[] = $this->addPrefix($item).' ' .$key;
        }
        return ' FROM '.implode($result, ',');
    }

    /**
     * 支持多个相同的left [$table, $where, ...]
     * @return string
     */
    protected function compileJoins() {
        if (empty($this->joins)) {
            return null;
        }
        $sql = '';
        foreach ($this->joins as $item) {
            $sql .= sprintf(' %s JOIN %s ON %s',
                strtoupper($item['type']),
                $this->addPrefix($item['table']),
                $item['on']);
        }
        return $sql;
    }

    public function compileWheres() {
        if (empty($this->wheres)) {
            return null;
        }
        $args = [];
        foreach ($this->wheres as $where) {
            $args[] = $where['boolean'].' '.$this->{"compileWhere{$where['type']}"}($where);
        }
        return ' WHERE '.$this->removeLeadingBoolean(implode(' ', $args));
    }

    protected function compileWhereBasic($where) {
        return $where['column'].' '.$where['operator'].' ?';
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereNull($where) {
        return $where['column'].' is null';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereIn($where) {
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
    protected function compileWhereNotIn($where) {
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
    protected function compileWhereNotNull($where) {
        return $where['column'].' is not null';
    }

    /**
     * Compile a "between" where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereBetween($where) {
        $between = $where['not'] ? 'not between' : 'between';

        return $where['column'].' '.$between.' ? and ?';
    }

    /**
     * Compile a raw where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereRaw($where) {
        return $where['sql'];
    }

    /**
     * Compile a nested where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhereNested($where) {
        // Here we will calculate what portion of the string we need to remove. If this
        // is a join clause query, we need to remove the "on" portion of the SQL and
        // if it is a normal query we need to take the leading "where" of queries.
        $offset = 6;

        return '('.substr($where['query']->compileWheres(), $offset).')';
    }

    /**
     * @return string
     */
    protected function compileUnions() {
        if (empty($this->unions)) {
            return null;
        }
        $sql = ' ';
        foreach ($this->unions as $item) {
            $sql .= 'UNION ';
            if ($item['all']) {
                $sql .= 'ALL ';
            }
            if ($item['query'] instanceof Query) {
                $sql .= $item['query']->getSql();
                continue;
            }
            if (is_array($item['query'])) {
                $sql .= (new Query())->load($item['query'])->getSql();
            }
            $sql .= $item['query'];
        }
        return $sql;
    }

    /**
     * Compile the "having" portions of the query.
     *
     * @return string
     */
    protected function compileHavings() {
        if (empty($this->having)) {
            return null;
        }
        $sql = implode(' ', array_map([$this, 'compileHaving'], $this->having));
        return ' HAVING '.$this->removeLeadingBoolean($sql);
    }

    /**
     * Compile a single having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileHaving(array $having) {
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
    protected function compileBasicHaving($having) {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'].' '.$column.' '.$having['operator'].' '.$parameter;
    }



    protected function compileGroups() {
        if (empty($this->groups)) {
            return null;
        }
        return ' GROUP BY '.implode(',', (array)$this->groups);
    }

    protected function compileOrders() {
        if (empty($this->orders)) {
            return null;
        }
        $result = array();
        for ($i = 0, $length = count($this->orders); $i < $length; $i ++) {
            $sql = $this->orders[$i];
            if ($i < $length - 1 && in_array(strtolower($this->orders[$i + 1]), array('asc', 'desc')) ) {
                $sql .= ' '.strtoupper($this->orders[$i + 1]);
                $i ++;
            }
            $result[] = $sql;
        }
        return ' ORDER BY '.implode($result, ',');
    }

    protected function compileLimit() {
        if (empty($this->limit)) {
            return null;
        }
        $param = (array)$this->limit;
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

    protected function compileOffset() {
        if (empty($this->offset)) {
            return null;
        }
        return ' OFFSET '.intval($this->offset);
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
    protected function compileLock($value) {
        if (! is_string($value)) {
            return $value ? ' for update' : ' lock in share mode';
        }

        return $value;
    }

    /**
     * @return string
     */
    public function compileQuery() {
        $sql = $this->compileSelects().
            $this->compileFrom().
            $this->compileJoins().
            $this->compileWheres().
            $this->compileGroups().
            $this->compileHavings().
            $this->compileOrders().
            $this->compileLimit().
            $this->compileOffset();
        return $sql;
    }


    /**
     * Compile an insert statement into SQL.
     *
     * @param  array  $values
     * @return string
     */
    public function compileInsert(array $values) {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->addPrefix($this->from);

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = implode('`,`', array_keys(reset($values)));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same amount of parameter
        // bindings so we will loop through the record and parameterize them all.
        $args = [];
        foreach ($values as $item) {
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
                    $this->addBinding($value);
                    continue;
                }
                if (is_array($value) || is_object($value)) {
                    $arg[] = '?';
                    $this->addBinding(serialize($value));
                    continue;
                }
                $arg[] = $value;
            }
            $args[] = '(' . implode(', ', $arg) . ')';
        }
        $parameters = implode(', ', $args);
        return "insert into $table (`$columns`) values $parameters";
    }


    /**
     * Compile an update statement into SQL.
     *
     * @param  array  $values
     * @return string
     */
    public function compileUpdate($values) {
        $table = $this->addPrefix($this->from);
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
        $this->addBinding($parameters, 'join');

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        $joins = '';

        if (isset($this->joins)) {
            $joins = $this->compileJoins();
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $where = $this->compileWheres();

        $sql = rtrim("update {$table}{$joins} set $columns $where");

        // If the query has an order by clause we will compile it since MySQL supports
        // order bys on update statements. We'll compile them using the typical way
        // of compiling order bys. Then they will be appended to the SQL queries.
        if (! empty($this->orders)) {
            $sql .= $this->compileOrders();
        }

        // Updates on MySQL also supports "limits", which allow you to easily update a
        // single record very easily. This is not supported by all database engines
        // so we have customized this update compiler here in order to add it in.
        if (isset($this->limit)) {
            $sql .= $this->compileLimit();
        }

        return rtrim($sql);
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @return string
     */
    public function compileDelete() {
        $table = $this->addPrefix(reset($this->from));

        $where = is_array($this->wheres) ? $this->compileWheres() : '';

        return !empty($this->joins)
            ? $this->compileDeleteWithJoins($table, $where)
            : $this->compileDeleteWithoutJoins($table, $where);
    }

    /**
     * Compile a delete query that does not use joins.
     *
     * @param  string  $table
     * @param  array  $where
     * @return string
     */
    protected function compileDeleteWithoutJoins($table, $where) {
        $sql = trim("delete from {$table} {$where}");

        // When using MySQL, delete statements may contain order by statements and limits
        // so we will compile both of those here. Once we have finished compiling this
        // we will return the completed SQL statement so it will be executed for us.
        if (! empty($this->orders)) {
            $sql .= $this->compileOrders();
        }

        if (isset($this->limit)) {
            $sql .= $this->compileLimit();
        }
        return $sql;
    }

    /**
     * Compile a delete query that uses joins.
     *
     * @param  string  $table
     * @param  array  $where
     * @return string
     */
    protected function compileDeleteWithJoins($table, $where) {
        $joins = $this->compileJoins();

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

}