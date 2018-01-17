<?php
namespace Zodream\Database\Model\Concerns;

use Zodream\Database\Query\Record;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/5/7
 * Time: 14:12
 */
trait SaveModel {

    /**
     * 保存
     * @return bool|mixed
     */
    public function save() {
        $this->invoke(self::BEFORE_SAVE, [$this]);
        if ($this->isNewRecord) {
            $row = $this->insert();
        } else {
            $row = $this->update();
        }
        $this->invoke(self::BEFORE_SAVE, [$this]);
        return $row;
    }

    /**
     * 更新
     * @return bool|mixed
     */
    public function update() {
        $this->isNewRecord = false;
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }
        $this->invoke(self::BEFORE_UPDATE, [$this]);
        if (!$this->validate()) {
            return false;
        }
        $query = $this->getPrimaryKeyQuery();
        if (empty($query)) {
            $this->setError('pk', 'ERROR PK!');
            return false;
        }
        $data = $this->_getRealFields();
        $row = $query->set($data)
            ->update();
        if (!empty($row)) {
            $this->setOldAttribute();
        }
        $this->invoke(self::AFTER_UPDATE, [$this]);
        return $row;
    }

    /**
     * 插入
     * @return bool
     */
    public function insert() {
        $this->isNewRecord = true;
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }
        $this->invoke(self::BEFORE_INSERT, [$this]);
        if (!$this->validate()) {
            return false;
        }
        $data = $this->_getRealFields();
        $row = static::record()
            ->set($data)
            ->insert();
        if (!empty($row)) {
            $this->set(current($this->primaryKey), $row);
            $this->setOldAttribute();
        }
        $this->invoke(self::AFTER_INSERT, [$this]);
        return $row;
    }

    /**
     * 初始化并保存到数据库
     * @param array $data
     * @return static
     */
    public static function create(array $data) {
        $model = new static($data);
        $model->save();
        return $model;
    }

    /**
     * 删除数据
     * DELETE QUERY
     *
     * @return int 返回影响的行数,
     */
    public function delete() {
        $query = $this->getPrimaryKeyQuery();
        if (empty($query)) {
            return false;
        }
        $row = $query->delete();
        if (!empty($row)) {
            $this->initOldAttribute();
        }
        return $row;
    }

    /**
     * 获取表的列名
     * @return array
     */
    protected function getTableFields() {
        return array_merge(array_keys($this->rules()), (array)$this->primaryKey);
    }

    /**
     * @return array
     */
    private function _getRealFields() {
        $fields = $this->getTableFields();
        $data = [];
        foreach ($fields as $item) {
            if (!$this->hasAttribute($item)) {
                continue;
            }
            if (!$this->isNewRecord && !$this->isNewAttribute($item)) {
                continue;
            }
            $data[$item] = $this->getAttributeSource($item);
        }
        return $data;
    }

    /**
     * 自动获取条件
     * @return Record|bool
     */
    public function getPrimaryKeyQuery() {
        foreach ($this->primaryKey as $item) {
            if ($this->hasAttribute($item)) {
                return $this->record()->where($item, $this->getAttributeSource($item));
            }
        }
        return false;
    }

    /**
     * 是否有主键值存在
     * @return bool
     */
    public function hasPrimaryKey() {
        foreach ($this->primaryKey as $item) {
            if (!empty($this->getAttributeSource($item))) {
                return true;
            }
        }
        return false;
    }
}