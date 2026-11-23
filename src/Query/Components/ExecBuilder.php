<?php
declare(strict_types = 1);
namespace Zodream\Database\Query\Components;

use Zodream\Database\DB;
use Zodream\Database\Model\Model;
use Zodream\Html\Page;

trait ExecBuilder {

    public function get(mixed ...$columns): mixed {
        if ($this->isEmpty) {
            return [];
        }
        if (func_num_args() > 0) {
            $this->select(...func_get_args());
        }
        $res = DB::db()->fetch($this->getSQL(), $this->getBindings());
        return empty($res) ? [] : $this->wrapResult($res);
    }

    public function each(callable $cb, mixed ...$fields): array {
        if (func_num_args() > 1) {
            $this->select(...$fields);
        }
        $engine = DB::engine();
        $result = $engine->fetchRow($this->getSQL(), $this->getBindings());
        $items = [];
        while (!!$res = $engine->fetchRow()) {
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
    public function page(int $perPage = 20, string $pageKey = 'page', int $page = -1): Page {
        $countQuery = clone $this;
        $countQuery->selects = [];
        $countQuery->orders = [];
        $countQuery->limit = null;
        $page = new Page($countQuery, $perPage, $pageKey, $page);
        return $page->setItems($this->skip($page->getItemsOffset())->take($page->getPerPage())->get());
    }

    public function first(mixed ...$columns): mixed {
        $result = $this->take(1)->get(...$columns);
        if (empty($result)) {
            return null;
        }
        return current($result);
    }

    /**
     *
     * @return bool|string|int
     * @throws \Exception
     */
    public function scalar(): mixed {
        if ($this->isEmpty) {
            return [];
        }
        return DB::db()->executeScalar($this->take(1)->getSQL(), $this->getBindings());
    }

    protected function wrapField(mixed $field) {
        return empty($field) ? $field : sprintf('`%s`', trim($field, '`'));
    }

    public function pluck(string|null $column = null, string|null $key = null): array {
        if (empty($this->selects) && !empty($column)) {
            $this->select($this->wrapField($column), $this->wrapField($key));
        }
        $data = $this->get();
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
    public function value(string $column): mixed {
        return $this->select($column)->scalar();
    }

    /**
     * 转换查询结果
     */
    protected function wrapResult(array $items): array {
        return $items;
    }
}