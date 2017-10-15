<?php
namespace Zodream\Database\Query;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/3/19
 * Time: 10:30
 */
use Zodream\Database\Query\Components\JoinBuilder;
use Zodream\Database\Query\Components\WhereBuilder;
use Zodream\Domain\Html\Page;
use Zodream\Helpers\Arr;

class Query extends BaseQuery {

    use JoinBuilder;

    public $selects = array();

    public $joins = array();

    public $groups = array();

    public $having = array();

    public $orders = array();

    public $unions = array();

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
                $this->selects[] = $value. ' AS '.$key;
                continue;
            }
            if (!is_null($value)) {
                $this->selects[] = $value;
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
        $this->selects[] = "{$name}({$column}) AS {$name}";
        return $this;
    }

    public function groupBy($groups) {
        foreach (func_get_args() as $group) {
            $this->groups = array_merge(
                (array) $this->groups,
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

    public function having($column, $operator = null, $value = null, $boolean = 'and') {
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
    public function orHaving($column, $operator = null, $value = null) {
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

    public function order($args) {
        return call_user_func_array([$this, 'orderBy'], func_get_args());
    }

    public function union($sql, $all = false) {
        $this->unions[] = ['query' => $sql, 'all' => $all];
        return $this;
    }



    /**
     * @param bool $isArray
     * @return array|object[]
     */
    public function all($isArray = true) {
        if ($isArray) {
            return $this->command()->getArray($this->getSql(), $this->getBindings());
        }
        return $this->command()->getObject($this->getSql(), $this->getBindings());
    }

    /**
     *
     * @param int $size
     * @param string $key
     * @return Page
     */
    public function page($size = 20, $key = 'page') {
        $select = $this->selects;
        $this->selects = [];
        $page = new Page($this, $size, $key);
        $this->selects = $select;
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



}