<?php
namespace Zodream\Database\Query;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/3/19
 * Time: 10:30
 */
use Zodream\Domain\Html\Page;
use Zodream\Helpers\Arr;

class Query extends BaseQuery {

    protected $select = array();

    protected $from = array();

    protected $join = array();

    protected $group = array();

    protected $having = array();

    protected $order = array();

    protected $union = array();

    protected $sequence =  array(
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
    );

    public function __construct($args = array()) {
        $this->load($args);
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

    /**
     * @param string|array $field
     * @return static
     */
    public function select($field = '*') {
        $this->select = [];
        if (!is_array($field)) {
            $field = func_get_args();
        }
        return $this->andSelect($field);
    }
    
    public function andSelect($field = '*') {
        if (!is_array($field)) {
            $field = func_get_args();
        }
        foreach ($field as $key => $value) {
            if (!is_int($key)) {
                $this->select[] = $value. ' AS '.$key;
                continue;
            }
            if (!is_null($value)) {
                $this->select[] = $value;
            }
        }
        return $this;
    }

    /**
     * 统计
     * @param string $column
     * @return integer
     */
    public function count($column = '*') {
        return (int)$this->_selectFunction(__FUNCTION__, $column)->scalar();
    }

    /**
     * 最大值
     * @param $column
     * @return bool|string
     */
    public function max($column)  {
        return $this->_selectFunction(__FUNCTION__, $column)->scalar();
    }

    /**
     * 最小值
     * @param $column
     * @return bool|int|string
     */
    public function min($column)  {
        return $this->_selectFunction(__FUNCTION__, $column)->scalar();
    }

    /**
     * 平均值
     * @param $column
     * @return bool|int|string
     */
    public function avg($column)  {
        return $this->_selectFunction(__FUNCTION__, $column)->scalar();
    }

    /**
     * 总和
     * @param $column
     * @return bool|int|string
     */
    public function sum($column)  {
        return $this->_selectFunction(__FUNCTION__, $column)->scalar();
    }

    /**
     * @param string $name
     * @param string $column
     * @return $this
     */
    private function _selectFunction($name, $column) {
        $this->select[] = "{$name}({$column}) AS {$name}";
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

    public function join($type, $table, $on = '', $params = array()) {
        $this->join[] = array($type, $table, $on);
        return $this->addParam($params);
    }

    public function inner($table, $on = '', $params = array()) {
        $this->addJoin($table, $on, 'INNER');
        return $this->addParam($params);
    }

    public function leftJoin() {

    }

    public function addJoin($args, $on = '', $tag = 'left') {
        if (is_array($on)) {
            if (count($on) == 2) {
                $on = $on[0].' = '.$on[1];
            } else {
                list($key, $value) = Arr::split($on);
                $on = $key.' = '.$value;
            }
        }
        $tag = strtoupper($tag);
        if (!is_array($args)) {
            $this->join[] = array( $tag.' JOIN', $this->addPrefix($args), $on);
            return;
        }
        if ($args[0] instanceof Query) {
            $this->join[] = array( $tag.' JOIN', '('.$args[0]->getSql().') '.$args[1], $on);
            return;
        }
        for ($i = 1, $length = count($args); $i < $length; $i += 2) {
            $this->join[] = array($tag.' JOIN ', $this->addPrefix($args[$i - 1]), $args[$i]);
        }
    }

    public function left($table, $on = '', $params = array()) {
        $this->addJoin($table, $on, 'LEFT');
        return $this->addParam($params);
    }

    public function right($table, $on = '', $params = array()) {
        $this->addJoin($table, $on, 'RIGHT');
        return $this->addParam($params);
    }

    public function groupBy($groups) {
        foreach (func_get_args() as $group) {
            $this->group = array_merge(
                (array) $this->group,
                array_wrap($group)
            );
        }
        return $this;
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

    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        $type = 'Basic';

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        list($value, $operator) = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() == 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');

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
    public function orHaving($column, $operator = null, $value = null)
    {
        return $this->having($column, $operator, $value, 'or');
    }

    /**
     * ORDER SQL
     * @param array|string $args
     * @return Query
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
                        $this->order[] = $value;
                        $this->order[] = $key;
                    }
                    continue;
                }
                // 'a' => 'b'
                $this->order[] = $key;
                $this->order[] = $item;
                continue;
            }
            if (is_array($item)) {
                // ['a', 'asc']
                $this->order[] = $item[0];
                $this->order[] = $item[1];
                continue;
            }
            $this->order[] = $item;
        }
        return $this;
    }

    public function union($sql, $all = false) {
        $this->union[] = ['query' => $sql, 'all' => $all];
        return $this;
    }

    /**
     * @return string
     */
    public function getSql() {
        return $this->getSelect().
        $this->getFrom().
        $this->getJoin().
        $this->getWhere().
        $this->getGroup().
        $this->getHaving().
        $this->getOrder().
        $this->getLimit().
        $this->getOffset();
    }

    /**
     * @param bool $isArray
     * @return array|object[]
     */
    public function all($isArray = true) {
        if ($isArray) {
            return $this->command()->getArray($this->getSql(), $this->get());
        }
        return $this->command()->getObject($this->getSql(), $this->get());
    }

    /**
     *
     * @param int $size
     * @param string $key
     * @return Page
     */
    public function page($size = 20, $key = 'page') {
        $select = $this->select;
        $this->select = [];
        $page = new Page($this, $size, $key);
        $this->select = $select;
        return $page->setPage($this->limit($page->getLimit())->all());
    }

    /**
     * @return array|bool
     */
    public function one() {
        $this->limit(1);
        $result = $this->all();
        if (empty($result)) {
            return false;
        }
        return current($result);
    }

    /**
     *
     * @return bool|string|int
     */
    public function scalar() {
        $result = $this->one();
        if (empty($result)) {
            return false;
        }
        return current($result);
    }

    public function pluck($column) {
        $data = $this->select($column)->all();
        if (empty($data)) {
            return [];
        }
        $args = [];
        foreach ($data as $item) {
            $args[] = current($item);
        }
        return $args;
    }

    /**
     * 获取值
     * @param string $column
     * @return bool|int|string
     */
    public function value($column) {
        return $this->select($column)->scalar();
    }

    protected function getSelect() {
        return 'SELECT '.$this->getField();
    }

    protected function getFrom() {
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
     * @return string
     */
    protected function getUnion() {
        if (empty($this->union)) {
            return null;
        }
        $sql = ' ';
        foreach ($this->union as $item) {
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

    protected function getHaving() {
        if (empty($this->having)) {
            return null;
        }
        return ' Having'.$this->getCondition($this->having);
    }



    /**
     * 支持多个相同的left [$table, $where, ...]
     * @return string
     */
    protected function getJoin() {
        if (empty($this->join)) {
            return null;
        }
        $sql = '';
        foreach ($this->join as $item) {
            $sql .= " {$item[0]} {$item[1]}";
            if (!empty($item[2])) {
                $sql .= " ON {$item[2] }";
            }
        }
        return $sql;
    }

    /**
     *
     * 关键字 DISTINCT 唯一 AVG() COUNT() FIRST() LAST() MAX()  MIN() SUM() UCASE() 大写  LCASE()
     * MID(column_name,start[,length]) 提取字符串 LEN() ROUND() 舍入 NOW() FORMAT() 格式化
     * @return string
     */
    protected function getField() {
        if (empty($this->select)) {
            return '*';
        }
        $result = array();
        foreach ((array)$this->select as $key => $item) {
            if (is_integer($key)) {
                $result[] = $item;
            } else {
                $result[] = "{$item} AS {$key}";
            }
        }
        return implode($result, ',');
    }

    protected function getGroup() {
        if (empty($this->group)) {
            return null;
        }
        return ' GROUP BY '.implode(',', (array)$this->group);
    }

    protected function getOrder() {
        if (empty($this->order)) {
            return null;
        }
        $result = array();
        for ($i = 0, $length = count($this->order); $i < $length; $i ++) {
            $sql = $this->order[$i];
            if ($i < $length - 1 && in_array(strtolower($this->order[$i + 1]), array('asc', 'desc')) ) {
                $sql .= ' '.strtoupper($this->order[$i + 1]);
                $i ++;
            }
            $result[] = $sql;
        }
        return ' ORDER BY '.implode($result, ',');
    }


}