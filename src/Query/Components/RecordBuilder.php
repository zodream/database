<?php
declare(strict_types = 1);
namespace Zodream\Database\Query\Components;


use Zodream\Database\DB;

trait RecordBuilder {


    /**
     * INSERT RECORD
     *
     * @access public
     *
     * @param array|string $columns
     * @param array|string $values
     * @return int|string 返回最后插入的ID,
     * @throws \Exception
     */
    public function insert(array|string $columns = '', array|string $values = ''): int|string {
        $sql = DB::grammar()->compileInsert($this, $columns, $values);
        return DB::db()
            ->insert($sql, $this->getBindings());
    }

    /**
     * UPDATE
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function update(array $data): int {
        $sql = DB::grammar()->compileUpdate($this, $data);
        return DB::db()
            ->update($sql, $this->getBindings());
    }

    /**
     * 设置bool值
     *
     * @param string $filed
     * @return int
     * @throws \Exception
     */
    public function updateBool($filed): int {
        return $this->update([
            "{$filed} = CASE WHEN {$filed} = 1 THEN 0 ELSE 1 END"
        ]);
    }

    /**
     * int加减
     *
     * @param string|array $column
     * @param int|float $value
     * @return int
     * @throws \Exception
     */
    public function updateIncrement(string|array $column, int|float $value = 1): int {
        $sql = [];
        foreach ((array)$column as $key => $item) {
            if (is_numeric($key)) {
                $sql[] = "`$item` = `$item` ".$this->formatIncrement($value);
            } else {
                $sql[] = "`$key` = `$key` ".$this->formatIncrement($item);
            }
        }
        return $this->update($sql);
    }

    public function updateDecrement(string|array $column, int|float $value = 1): int {
        $sql = [];
        foreach ((array)$column as $key => $item) {
            if (is_numeric($key)) {
                $sql[] = "`$item` = `$item` ".$this->formatIncrement(- $value);
            } else {
                $sql[] = "`$key` = `$key` ".$this->formatIncrement(- $item);
            }
        }
        return $this->update($sql);
    }

    /**
     * 获取加或减
     * @param string|int $num
     * @return string
     */
    protected function formatIncrement($num): string {
        if ($num >= 0) {
            $num = '+'.$num;
        }
        return (string)$num;
    }

    /**
     * 如果行作为新记录被insert，则受影响行的值为1；如果原有的记录被更新，则受影响行的值为2。 如果有多条存在则只更新最后一条
     * @param array $insertData
     * @param array $updateData
     * @return int
     * @throws \Exception
     */
    public function insertOrUpdate(array $insertData, array $updateData): int {
        return DB::db()->update(
            DB::grammar()->compileInsertOrUpdate($this, $insertData, $updateData)
        );
    }

    /**
     * INSERT OR REPLACE 在执行REPLACE后，系统返回了所影响的行数，如果返回1，说明在表中并没有重复的记录，如果返回2，说明有一条重复记录，系统自动先调用了
     * @param array $data
     * @return mixed
     */
    public function replace(array $data): int {
        return DB::db()->update(
            DB::grammar()->compileInsertOrReplace($this, $data)
        );
    }

    /**
     * DELETE RECORD
     * @return mixed
     * @throws \Exception
     */
    public function delete(): int {
        $sql = DB::grammar()->compileDelete($this);
        return DB::db()
            ->delete($sql, $this->getBindings());
    }
}