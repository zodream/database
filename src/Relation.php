<?php
namespace Zodream\Database;

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
        $data = static::getRelationQuery($models)->select($this->getRelationFields())->all();
        if (empty($data)) {
            return $data;
        }
        $results = [];
        foreach ($this->relations as $relation) {
            if (empty($relation->getKey())) {
                return $relation->getResults($data);
            }
            $results[$relation->getKey()] = $relation->getResults($data);
        }
        return $results;
    }

    /**
     * 获取查询结果
     * @param array $data
     * @return array|mixed|object[]
     * @throws \Exception
     */
    public function getResults(array $data) {
        $results = $this->getQueryResults($data);
        if (empty($results)) {
            return $results;
        }
        return $this->type == self::TYPE_ONE ? reset($results) : $results;
    }


    /**
     * @param array[] $models
     * @param array[] $relations
     * @return array
     * @throws \Exception
     */
    public static function create(array $models, array $relations) {
        foreach ($relations as $key => $relation) {
            $relation = static::parse($relation, $key);
            $models[$relation->getKey()] = $relation->getResults($models);
        }
        return $models;
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
        $relation->setLinks($relation['link']);
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