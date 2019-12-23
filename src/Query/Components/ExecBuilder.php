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
    public function all(): ?array {
        if ($this->isEmpty) {
            return null;
        }
        return $this->command()->select($this->getSql(), $this->getBindings());
    }

    public function get($fields = null): ?array {
        if (func_num_args() > 0) {
            $this->select(...func_get_args());
        }
        return $this->all();
    }

    public function each(callable $cb, ...$fields) {
        if (func_num_args() > 1) {
            $this->select(...$fields);
        }
        /** @var Command $command */
        $command = $this->command();
        $result = $command->execute($this->getSql(), $this->getBindings());
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
     * @param int $size
     * @param string $key
     * @return Page
     * @throws \Exception
     */
    public function page($size = 20, $key = 'page'): Page {
        $countQuery = clone $this;
        $countQuery->selects = [];
        $countQuery->orders = [];
        $countQuery->limit = null;
        $page = new Page($countQuery, $size, $key);
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

    public function first($fields = null) {
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
    public function scalar(): ?string {
        $result = $this->one();
        if (empty($result)) {
            return null;
        }
        return current($result);
    }

    protected function wrapField($field) {
        return empty($field) ? $field : sprintf('`%s`', trim($field, '`'));
    }

    public function pluck($column = null, $key = null): array {
        if (empty($this->selects)) {
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
    public function value($column): ?string {
        return $this->select($column)->scalar();
    }
}