<?php
declare(strict_types = 1);
namespace Zodream\Database\Query\Components;


use Zodream\Database\Query\Builder;
use Zodream\Helpers\Str;

trait RecordBuilder {

    /**
     * SET TABLE
     * @param $table
     * @return Builder
     * @throws \Exception
     */
    public function setTable(string $table): Builder {
        $this->from = $table;
        return $this;
    }

    public function getTable(): string {
        return $this->addPrefix(is_array($this->from) ? reset($this->from) : $this->from);
    }


    /**
     * INSERT RECORD
     *
     * @access public
     *
     * @param null $columns
     * @param array $data
     * @return int 返回最后插入的ID,
     * @throws \Exception
     */
    public function insert($columns = null, $data = null) {
        $sql = $this->grammar()->compileInsert($this, $columns, $data);
        return $this->command()
            ->insert($sql, $this->getBindings());
    }

    /**
     * UPDATE
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function update(array $data) {
        $sql = $this->grammar()->compileUpdate($this, $data);
        return $this->command()
            ->update($sql, $this->getBindings());
    }

    /**
     * 设置bool值
     *
     * @param string $filed
     * @return int
     * @throws \Exception
     */
    public function updateBool($filed) {
        return $this->update([
            "{$filed} = CASE WHEN {$filed} = 1 THEN 0 ELSE 1 END"
        ]);
    }

    /**
     * int加减
     *
     * @param string|string $filed
     * @param integer $num
     * @return int
     * @throws \Exception
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
        return $this->update($sql);
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
     * @param array $data
     * @return mixed
     */
    public function replace(array $data) {
        $addFields = implode('`,`', array_keys($data));
        return $this->command()
            ->insertOrReplace("`{$addFields}`", Str::repeat('?', $data),
                array_values($data));
    }

    /**
     * DELETE RECORD
     * @return mixed
     * @throws \Exception
     */
    public function delete() {
        $sql = $this->grammar()->compileDelete($this);
        return $this->command()
            ->delete($sql, $this->getBindings());
    }
}