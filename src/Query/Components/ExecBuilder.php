<?php
declare(strict_types = 1);
namespace Zodream\Database\Query\Components;


use Zodream\Database\Command;
use Zodream\Database\Model\Model;
use Zodream\Html\Page;

trait ExecBuilder {


    /**
     * @return array
     * @throws \Exception
     */
    public function all() {
        if ($this->isEmpty) {
            return null;
        }
        return $this->command()->fetch($this->getSQL(), $this->getBindings());
    }

    public function get(...$columns) {
        if (func_num_args() > 0) {
            $this->select(...func_get_args());
        }
        return $this->all();
    }

    public function each(callable $cb, ...$fields): array {
        if (func_num_args() > 1) {
            $this->select(...$fields);
        }
        /** @var Command $command */
        $command = $this->command();
        $result = $command->execute($this->getSQL(), $this->getBindings());
        $items = [];
        while (!!$res = $command->getEngine()->row(true, $result)) {
            $item = call_user_func($cb, $res);
            if (empty($item)) {
                continue;
            }
            if (!is_array($item)) {
                $items[] = $item;
                continue;
            }
            $val = current($item);
            if (is_array($val) || $val instanceof Model) {
                $items = array_merge($items, $item);
                continue;
            }
            $items[] = $item;
        }
        return $items;
    }

    /**
     *
     * @param int $perPage
     * @param string $pageKey
     * @return Page
     * @throws \Exception
     */
    public function page(int $perPage, string $pageKey = 'page'): Page {
        $countQuery = clone $this;
        $countQuery->selects = [];
        $countQuery->orders = [];
        $countQuery->limit = null;
        $page = new Page($countQuery, $perPage, $pageKey);
        return $page->setPage($this->limit($page->getLimit())->all());
    }

    /**
     * @return array|bool
     * @throws \Exception
     */
    public function one() {
        $this->limit(1);
        $result = $this->all();
        if (empty($result)) {
            return null;
        }
        return current($result);
    }

    public function first(...$columns) {
        if (func_num_args() > 0) {
            $this->select(...func_get_args());
        }
        return $this->one();
    }

    /**
     *
     * @return bool|string|int
     * @throws \Exception
     */
    public function scalar() {
        $result = $this->one();
        if (empty($result)) {
            return null;
        }
        return current($result);
    }

    protected function wrapField($field) {
        return empty($field) ? $field : sprintf('`%s`', trim($field, '`'));
    }

    public function pluck(?string $column = null, ?string $key = null): array {
        if (empty($this->selects) && !empty($column)) {
            $this->select($this->wrapField($column), $this->wrapField($key));
        }
        $data = $this->all();
        if (empty($data)) {
            return [];
        }
        if (!is_null($column) || !is_null($key)) {
            return array_column($data, $column, $key);
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
     * @throws \Exception
     */
    public function value(string $column) {
        return $this->select($column)->scalar();
    }
}