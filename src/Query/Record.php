<?php
namespace Zodream\Database\Query;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/7/12
 * Time: 19:24
 */
use Zodream\Helpers\Arr;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Traits\Attributes;

class Record extends BaseQuery  {

    use Attributes;

    protected $__attributes = [];

    /**
     * SET TABLE
     * @param $table
     * @return Record
     */
    public function setTable($table) {
        $this->command()->setTable($table);
        $this->from = $table;
        return $this;
    }


    /**
     * INSERT RECORD
     *
     * @access public
     *
     * @return int 返回最后插入的ID,
     */
    public function insert() {
        if (!$this->hasAttribute()) {
            return $this->command()->insert(null, 'NULL'); // 获取自增值
        }
        return $this->command()
            ->insert($this->compileInsert($this->get()),
                $this->getBindings());
    }

    /**
     * INSERT MANY RECORDS
     * @param array|string $columns
     * @param array $data
     * @return int
     */
    public function batchInsert($columns, array $data) {
        $args = [];
        foreach ($data as $item) {
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
                    $arg[] = "'".addslashes($value)."'";
                    continue;
                }
                if (is_array($value) || is_object($value)) {
                    $arg[] = "'".serialize($value)."'";
                    continue;
                }
                $arg[] = $value;
            }
            $args[] = '(' . implode(', ', $arg) . ')';
        }

        return $this->command()
            ->insert(implode(', ', (array)$columns), implode(', ', $args));
    }

    /**
     * UPDATE
     * @return mixed
     */
    public function update() {
        return $this->command()
            ->update($this->compileUpdate($this->get()), $this->getBindings());
    }

    /**
     * 设置bool值
     *
     * @param string $filed
     * @return int
     */
    public function updateBool($filed) {
        $this->__attributes[] = "{$filed} = CASE WHEN {$filed} = 1 THEN 0 ELSE 1 END";
        return $this->update();
    }

    /**
     * int加减
     *
     * @param string|string $filed
     * @param integer $num
     * @return int
     */
    public function updateOne($filed, $num = 1) {
        $sql = array();
        foreach ((array)$filed as $key => $item) {
            if (is_numeric($key)) {
                $sql[] = "`$item` = `$item` ".$this->_getNumber($num);
            } else {
                $sql[] = "`$key` = `$key` ".$item;
            }
        }
        return $this->set($sql)
            ->update();
    }

    /**
     * 获取加或减
     * @param string|int $num
     * @return string
     */
    private function _getNumber($num) {
        if ($num >= 0) {
            $num = '+'.$num;
        }
        return $num;
    }

    /**
     * INSERT OR REPLACE
     * @return mixed
     */
    public function replace() {
        $addFields = implode('`,`', array_keys($this->__attributes));
        return $this->command()
            ->insertOrReplace("`{$addFields}`", Str::repeat('?', $this->__attributes),
                array_values($this->__attributes));
    }

    /**
     * DELETE RECORD
     * @return mixed
     */
    public function delete() {
        return $this->command()
            ->delete($this->compileDelete(), $this->getBindings());
    }

    /**
     * @param array|Query $data
     * @param array $relations
     * @return bool
     */
    public static function moveTo($data, array $relations) {
        if ($data instanceof Query) {
            $data = $data->all();
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            foreach ($relations as $table => $relation) {
                if (is_integer($table) && is_callable($relation)) {
                    call_user_func($relation, $item);
                    continue;
                }
                $key = $table.'_id';
                /** @var Record $record */
                $record = (new static)->setTable($table);
                if (is_callable($relation)) {
                    call_user_func($relation, $record, $item);
                    // 防止有些表没有自增字段
                    $id = $record->insert();
                    if ($id > 0) {
                        $item[$key] = $id;
                    }
                    continue;
                }
                foreach ($relation as $column => $oldColumn) {
                    if (is_numeric($oldColumn) || empty($oldColumn)) {
                        $record->set($column, $oldColumn);
                        continue;
                    }
                    if (is_callable($oldColumn)) {
                        // 返回自定义字段
                        $record->set($column, $item[$table.'_'.$column] = call_user_func($oldColumn, $item));
                        continue;
                    }
                    if (strpos($oldColumn, '!') === 0) {
                        $record->set($column, substr($oldColumn, 1));
                        continue;
                    }
                    if (!array_key_exists($oldColumn, $item)) {
                        throw new \InvalidArgumentException($oldColumn);
                    }
                    $record->set($column, $item[$oldColumn]);
                }
                // 防止有些表没有自增字段
                $id = $record->insert();
                if ($id > 0) {
                    $item[$key] = $id;
                }
            }
        }
        return true;
    }
}