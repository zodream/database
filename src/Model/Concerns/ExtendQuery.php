<?php
declare(strict_types=1);
namespace Zodream\Database\Model\Concerns;

use Exception;
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
     * @param array|string $param 条件
     * @param string $field
     * @param array $parameters
     * @return static|boolean
     * @throws Exception
     */
    public static function find($param, string $field = '*', array $parameters = []) {
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
        $query = static::query()
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
    public static function findWithAuth($id, string $key = 'id', string $userKey = 'user_id') {
        return static::query()->where($key, $id)->where($userKey, auth()->id())->first();
    }

    /**
     * 查找或新增
     * @param $param
     * @param string $field
     * @param array $parameters
     * @return bool|Model|static
     * @throws Exception
     */
    public static function findOrNew($param, string $field = '*', array $parameters = []) {
        if (empty($param)) {
            return new static();
        }
        $model = static::find($param, $field, $parameters);
        if (empty($model)) {
            return new static();
        }
        return $model;
    }

    /**
     * Set not found default data
     * @param $param
     * @param array $attributes
     * @return bool|Model
     * @throws Exception
     */
    public static function findOrDefault($param, array $attributes) {
        $model = self::findOrNew($param);
        if ($model->isNewRecord) {
            $model->set($attributes);
        }
        return $model;
    }

    /**
     * Set new attr
     * @param $param
     * @param array $attributes
     * @return bool|Model
     * @throws Exception
     */
    public static function findWithReplace($param, array $attributes) {
        $model = self::findOrNew($param);
        $model->set($attributes);
        return $model;
    }

    /**
     * 查找或报错
     * @param $param
     * @param string $message
     * @return bool|Model
     * @throws Exception
     */
    public static function findOrThrow($param, string $message = 'model find error') {
        $model = static::find($param);
        if (empty($model)) {
            throw new \InvalidArgumentException($message);
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
        return (new Query())
            ->setModelName(static::className())
            ->from(static::tableName());
    }
}