<?php
declare(strict_types = 1);

namespace Zodream\Database\Query\Components;


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
    public function one(): ?array {
        $this->limit(1);
        $result = $this->all();
        if (empty($result)) {
            return null;
        }
        return current($result);
    }

    public function first($fields = null): ?array {
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

    public function pluck($column = null, $key = null): array {
        if (empty($this->selects)) {
            $this->select($column, $key);
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