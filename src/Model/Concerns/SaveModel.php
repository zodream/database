<?php
declare(strict_types=1);
namespace Zodream\Database\Model\Concerns;

use Exception;
use Zodream\Database\Model\Query;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/5/7
 * Time: 14:12
 */
trait SaveModel {

    /**
     * 保存
     * @param bool $force 是否忽略更新时的内容位变化错误
     * @return bool|mixed
     */
    public function save(bool $force = false): mixed {
        $this->invoke(self::BEFORE_SAVE, [$this]);
        if ($this->isNewRecord) {
            $row = $this->insert();
        } else {
            $row = $this->update();
        }
        $this->invoke(self::BEFORE_SAVE, [$this]);
        if (!$row && $force && $this->isNotChangedError()) {
            return true;
        }
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
        if (empty($data)) {
            return true;
        }
        $row = $query
            ->update($data);
        if (!empty($row)) {
            $this->setAttributeToOld();
        } else {
            $this->setError(static::ERROR_NOT_DATA_CHANGE, 'Data is not change!');
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
        $row = static::query()
            ->insert($data);
        if (!empty($row)) {
            $pk = $this->primaryKey;
            // 插入空主键自动设置
            if ($this->isEmpty($pk)) {
                $this->set($pk, is_numeric($row) ? intval($row) : $row);
            }
            $this->setAttributeToOld();
        }
        $this->invoke(self::AFTER_INSERT, [$this]);
        return $row;
    }

    /**
     * 初始化并保存到数据库
     * @param array $data
     * @return static|bool
     */
    public static function create(array $data) {
        $model = new static($data);
        if (!$model->save()) {
            return false;
        }
        return $model;
    }

    /**
     * 初始化并保存到数据库
     * @param array $data
     * @return static
     * @throws Exception
     */
    public static function createOrThrow(array $data) {
        $model = new static($data);
        if (!$model->save()) {
            throw new Exception(
                app()->isDebug() ?
                    sprintf('[%s]%s', get_called_class(), $model->getFirstError()) :
                $model->getFirstError()
            );
        }
        return $model;
    }

    /**
     * 删除数据
     * DELETE QUERY
     *
     * @return int 返回影响的行数,
     */
    public function delete(): mixed {
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
        $data = array_keys($this->rules());
        if (!empty($this->primaryKey)) {
            $data[] = $this->primaryKey;
        }
        return $data;
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
            $value = $this->getAttributeSource($item);
            if ($this->isPrimaryKey($item) && empty($value)) {
                continue;
            }
            $data[$item] = $value;
        }
        return $data;
    }

    /**
     * 自动获取条件
     * @return Query|bool
     */
    public function getPrimaryKeyQuery() {
        if (!empty($this->primaryKey)
            && $this->hasAttribute($this->primaryKey)) {
            return static::query()->where($this->primaryKey,
                $this->getAttributeSource($this->primaryKey));
        }
        return false;
    }

    /**
     * 是否有主键值存在
     * @return bool
     */
    public function hasPrimaryKey(): bool {
        return !empty($this->primaryKey)
            && !empty($this->getAttributeSource($this->primaryKey));
    }

    /**
     * 自动设置是否是新值
     * @return $this
     */
    public function autoIsNew() {
        if ($this->isNewRecord) {
            $this->isNewRecord = !$this->hasPrimaryKey();
        }
        return $this;
    }

    /**
     * 判断是否是更新无数据返回的错误
     * @return bool
     */
    public function isNotChangedError(): bool {
        return $this->hasError() && !empty($this->getError(static::ERROR_NOT_DATA_CHANGE));
    }
}