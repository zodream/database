<?php
declare(strict_types=1);
namespace Zodream\Database\Model\Concerns;

use Exception;
use Zodream\Database\Model\DefaultEntityCreator;
use Zodream\Database\Model\Model;
use Zodream\Database\Model\Query;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/5/7
 * Time: 14:12
 */
trait ExtendQuery {
    /**
     * SELECT ONE BY QUERY
     * 查询一条数据
     *
     * @access public
     *
     * @param string|int|array $param 条件
     * @param string $field
     * @param array $parameters
     * @return boolean|Model|static
     * @throws Exception
     */
    public static function find(string|int|array $param, string $field = '*', array $parameters = []) {
        if (empty($param)) {
            return false;
        }
        $model = new static;
        if (!is_array($param)) {
            $param = [$model->getKeyName() => $param];
        }
        if (!is_array($param) || !array_key_exists('where', $param)) {
            $param = [
                'where' => $param
            ];
        }
        $query = DefaultEntityCreator::from($model)->builder()
            ->load($param);
        if ($field !== '*' || empty($query->selects)) {
            $query->select($field);
        }
        return $query->addBinding($parameters)
            ->first();
    }

    /**
     * @param integer|string $id
     * @param string $key
     * @param string $userKey
     * @return static|bool
     * @throws Exception
     */
    public static function findWithAuth(int|string $id, string $key = 'id', string $userKey = 'user_id') {
        return static::query()->where($key, $id)->where($userKey, auth()->id())->first();
    }

    /**
     * 查找或新增
     * @param integer|string $id
     * @return bool|Model|static
     * @throws Exception
     */
    public static function findOrNew(int|string $id, string $key = 'id') {
        if (empty($id)) {
            return new static();
        }
        $model = static::query()->where($key, $id)->first();
        if (empty($model)) {
            return new static();
        }
        return $model;
    }

    /**
     * Set not found default data
     * @param integer|string $id
     * @param array $attributes
     * @return bool|Model|static
     * @throws Exception
     */
    public static function findOrDefault(int|string $id, array $attributes) {
        $model = self::findOrNew($id);
        if ($model->isNewRecord) {
            $model->set($attributes);
        }
        return $model;
    }

    /**
     * Set new attr
     * @param integer|string $id
     * @param array $attributes
     * @return bool|Model|static
     * @throws Exception
     */
    public static function findWithReplace(int|string $id, array $attributes) {
        $model = self::findOrNew($id);
        $model->set($attributes);
        return $model;
    }

    /**
     * 查找或报错
     * @param integer|string $id
     * @param string $message
     * @return Model|static
     * @throws Exception
     */
    public static function findOrThrow(int|string $id, string $message = 'model find error') {
        $model = static::query()->where('id', $id)->first();
        if (empty($model)) {
            throw new \InvalidArgumentException(
                app()->isDebug() ? sprintf('[%s]%s', get_called_class(), $message) : $message
            );
        }
        return $model;
    }

    /**
     * 查询数据
     *
     * @access public
     *
     * @return Query 返回查询结果,
     */
    public static function query() {
        return DefaultEntityCreator::from(static::class)->builder();
    }
}