<?php
namespace Zodream\Database;

use Zodream\Database\Model\Model;
use Zodream\Database\Query\Builder;

class Relation {

    const TYPE_ONE = 0;

    const TYPE_MANY = 1;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var Builder
     */
    protected $query;

    /**
     * @var array  $foreignKey => $localKey
     */
    protected $links = [];

    /**
     * @var Relation[]
     */
    protected $relations;

    /**
     * @var int
     */
    protected $type = self::TYPE_MANY;


    /**
     * @param string $key
     */
    public function setKey($key) {
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * @param int $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    /**
     * @param array $maps
     */
    public function setLinks(array $maps) {
        foreach ($maps as $key => $val) {
            if (!is_numeric($key)) {
                $this->appendLink($key, $val);
                continue;
            }
            if ($key % 2 == 0) {
                continue;
            }
            if (!isset($maps[$key -1])) {
                continue;
            }
            $this->appendLink($maps[$key -1], $val);
        }
    }

    public function appendLink($foreignKey, $localKey) {
        $this->links[$foreignKey] = $localKey;
    }

    /**
     * @return array
     */
    public function getLinks() {
        return $this->links;
    }

    public function getLinkKeys() {
        return array_keys($this->links);
    }

    /**
     * @param Builder $query
     */
    public function setQuery($query) {
        $this->query = $query;
    }

    /**
     * @param Relation[] $relations
     */
    public function setRelations(array $relations) {
        foreach ($relations as $key => $relation) {
            $this->appendRelation($relation, is_int($key) ? null : $key);
        }

    }

    public function appendRelation($relation, $key = null) {
        $this->relations[] = static::parse($relation, $key);
    }

    /**
     * @param array $models
     * @param null $key
     * @return mixed
     */
    protected function getKeys(array $models, $key) {
        $data = [];
        foreach ($models as $model) {
            $data[] = $model[$key];
        }
        $data = array_unique($data);
        sort($data);
        return $data;
    }

    /**
     * 转化成关联参训语句
     * @param array $data
     * @return Builder
     */
    public function getRelationQuery(array $data) {
        foreach ($this->links as $key => $val) {
            $this->query->whereIn(
                $val, $this->getKeys($data, $key)
            );
        }
        return $this->query;
    }

    /**
     * 获取下一个关联需要的字段
     * @return array
     */
    protected function getRelationFields() {
        $fields = [];
        foreach ($this->relations as $relation) {
            $fields = array_merge($fields, $relation->getLinkKeys());
        }
        return array_unique($fields);
    }

    /**
     * 获取所有的查询结果
     * @param array $models
     * @return array|mixed|object[]
     * @throws \Exception
     */
    protected function getQueryResults(array $models) {
        if (empty($this->relations)) {
            return static::getRelationQuery($models)->all();
        }
        $data = static::getRelationQuery($models)->appendSelect($this->getRelationFields())->all();
        if (empty($data)) {
            return $data;
        }
        foreach ($this->relations as $relation) {
            if (empty($relation->getKey())) {
                return $relation->getResults($data);
            }
            $data = $relation->getResults($data);
        }
        return $data;
    }

    /**
     * 获取查询结果
     * @param array $models
     * @return array|mixed|object[]
     * @throws \Exception
     */
    public function getResults($models) {
        $is_one = !is_array($models) || !is_array(reset($models));
        if ($is_one) {
            $models = [$models];
        }
        $results = $this->getQueryResults($models);
        if (empty($results) || !$is_one) {
            return $results;
        }
        return $this->type == self::TYPE_ONE ? reset($results) : $results;
    }

    /**
     * 获取并绑定属性值(自动判断数组类型)
     * @param array|Model $data
     * @return array|Model
     * @throws \Exception
     */
    public function get($data) {
        $is_one = !static::isSomeArr($data);;
        if ($is_one) {
            $data = [$data];
        }
        $data = $this->getWithMulti($data);
        return $is_one ? reset($data) : $data;
    }

    /**
     * 获取并绑定属性值（只接受二维数组）
     * @param array $data [[],]
     * @return array [[],]
     * @throws \Exception
     */
    public function getWithMulti(array $data) {
        $results = $this->getQueryResults($data);
        return $this->buildRelation($data, $results);
    }

    /**
     * 绑定属性
     * @param array $models
     * @param array $results
     * @return array
     */
    public function buildRelation(array $models, array $results) {
        foreach ($models as &$model) {
            if ($model instanceof Model) {
                $model->setRelation($this->getKey(), $this->matchRelation($model, $results));
                continue;
            }
            $model[$this->getKey()] = $this->matchRelation($model, $results);
        }
        unset($model);
        return $models;
    }

    /**
     * 匹配值
     * @param $model
     * @param $results
     * @return array
     */
    public function matchRelation($model, $results) {
        $data = [];
        foreach ($results as $item) {
            if (!$this->isMatchRelation($model, $item)) {
                continue;
            }
            if ($this->type == self::TYPE_ONE) {
                return $item;
            }
            $data[] = $item;
        }
        return $data;
    }

    /**
     * 是否匹配
     * @param $model
     * @param $result
     * @return bool
     */
    public function isMatchRelation($model, $result) {
        foreach ($this->links as $fk => $lk) {
            if ($model[$fk] != $result[$lk]) {
                return false;
            }
        }
        return true;
    }


    /**
     * 通过关联获取值（支持Model, 单个数组, 多个）
     * @param array[] $models
     * @param array[] $relations
     * @return array
     * @throws \Exception
     */
    public static function create($models, array $relations) {
        if (empty($models)) {
            return $models;
        }
        $is_one = !static::isSomeArr($models);
        if ($is_one) {
            $models = [$models];
        }
        foreach ($relations as $key => $relation) {
            $relation = static::parse($relation, $key);
            $models =  $relation->getWithMulti($models);
        }
        return $is_one ? reset($models) : $models;
    }

    /**
     * 是否是多维数组
     * @param $models
     * @return bool
     */
    protected static function isSomeArr($models) {
        if (!is_array($models)) {
            return false;
        }
        $model = reset($models);
        return is_array($model) || $model instanceof Model;
    }

    /**
     * @param $data
     * @param null $key
     * @return static
     */
    public static function parse($data, $key = null) {
        if ($data instanceof Relation) {
            return $data;
        }
        $relation = new static();
        $relation->setKey($key);
        $relation->setQuery($data['query']);
        $relation->setLinks($data['link']);
        if (isset($data['type'])) {
            $relation->setType($data['type']);
        }
        if (!isset($data['relation'])) {
            return $relation;
        }
        if (!is_array($data['relation']) ||
            isset($data['relation']['query'])) {
            $relation->setRelations([$data['relation']]);
            return $relation;
        }
        $relation->setRelations($data['relation']);
        return $relation;
    }
}