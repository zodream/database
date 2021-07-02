<?php
declare(strict_types=1);
namespace Zodream\Database;

use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\Model\Model;
use Zodream\Database\Model\Query;
use Zodream\Database\Query\Builder;
use Zodream\Helpers\Arr;
use Zodream\Html\Page;

/**
 * Class Relation
 * @package Zodream\Database
 * @method Relation where($column, $operator = null, $value = null, $boolean = 'and')
 * @method Relation with(...$args)
 * @method Relation orWhere($column, $operator = null, $value = null)
 * @method Relation whereIn($column, $values, $boolean = 'and', $not = false)
 * @method Relation orWhereIn($column, $values)
 * @method Relation whereNotIn($column, $values, $boolean = 'and')
 * @method Relation orWhereNotIn($column, $values)
 * @method Relation having($column, $operator = null, $value = null, $boolean = 'and')
 * @method Relation orHaving($column, $operator = null, $value = null)
 * @method Relation join($table, $first, $operator = null, $second = null, $type = 'inner')
 * @method Relation leftJoin($table, $first, $operator = null, $second = null)
 */
class Relation {

    const TYPE_ONE = 0;

    const TYPE_MANY = 1;

    /**
     * 表示替换整个
     */
    const EMPTY_RELATION_KEY = '__relation_empty__';
    /**
     * 表示合并，同时会转化成数组
     */
    const MERGE_RELATION_KEY = '__relation_merge__';

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
     * @return Relation
     */
    public function setKey($key) {
        $this->key = $key;
        return $this;
    }

    /**
     * @return string
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * @param int $type
     * @return Relation
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getType(): int {
        return $this->type;
    }

    /**
     * @param array $maps
     * @return Relation
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
        return $this;
    }

    public function appendLink($foreignKey, $localKey) {
        $this->links[$foreignKey] = $localKey;
        return $this;
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
     * @return Relation
     */
    public function setQuery($query) {
        $this->query = $query;
        return $this;
    }

    /**
     * @param Relation[] $relations
     * @return Relation
     */
    public function setRelations(array $relations) {
        foreach ($relations as $key => $relation) {
            $this->appendRelation($relation, is_int($key) ? null : $key);
        }
        return $this;
    }

    public function appendRelation($relation, $key = null) {
        $this->relations[] = static::parse($relation, $key);
        return $this;
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
            return static::getRelationQuery($models)->get();
        }
        $data = static::getRelationQuery($models)->appendSelect($this->getRelationFields())->get();
        if (empty($data)) {
            return $data;
        }
        foreach ($this->relations as $relation) {
            if (empty($relation->getKey())) {
                // 如果为key空 表示把结果作为替换
                $relation->setKey(self::EMPTY_RELATION_KEY);
                return $relation->get($data);
            }
            $data = $relation->get($data);
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
        $is_one = !static::isSomeArr($models);
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
        return $this->buildRelation($data, is_array($results) ? $results : []);
    }

    /**
     * 绑定属性
     * @param array $models
     * @param array $results
     * @return array
     */
    public function buildRelation(array $models, array $results) {
        foreach ($models as &$model) {
            $value = $this->matchRelation($model, $results);
            if ($this->key === self::EMPTY_RELATION_KEY) {
                $model = $value;
                continue;
            }
            if ($this->key === self::MERGE_RELATION_KEY) {
                $model = array_merge(Arr::toArray($model), Arr::toArray($value));
                continue;
            }
            if ($model instanceof Model) {
                $model->setRelation($this->getKey(), $value);
                continue;
            }
            $model[$this->getKey()] = $value;
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
                if ($this->hasEmptyRelationKey($item)) {
                    return $item[self::EMPTY_RELATION_KEY];
                }
                if ($this->hasMergeRelationKey($item)) {
                    return $item[self::MERGE_RELATION_KEY];
                }
                return $item;
            }
            if (!$this->hasReplaceRelationKey($item)) {
                $data[] = $item;
                continue;
            }
            $args = $this->hasEmptyRelationKey($item) ?
                $item[self::EMPTY_RELATION_KEY] : $item[self::MERGE_RELATION_KEY];
            if (empty($args)) {
                continue;
            }
            if (!self::isSomeArr($args)) {
                $data[] = $args;
                continue;
            }
            $data = array_merge($data, $args);
        }
        return $data;
    }

    protected function hasReplaceRelationKey($item) {
        return $this->hasEmptyRelationKey($item) || $this->hasMergeRelationKey($item);
    }

    protected function hasEmptyRelationKey($item) {
        if (is_array($item) || !$item instanceof Model) {
            return isset($item[self::EMPTY_RELATION_KEY])
                || array_key_exists(self::EMPTY_RELATION_KEY, $item);
        }
        return $item->relationLoaded(self::EMPTY_RELATION_KEY);
    }

    protected function hasMergeRelationKey($item) {
        if (is_array($item) || !$item instanceof Model) {
            return isset($item[self::MERGE_RELATION_KEY])
                || array_key_exists(self::MERGE_RELATION_KEY, $item);
        }
        return $item->relationLoaded(self::MERGE_RELATION_KEY);
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
     * @return Builder| Query
     */
    public function getQuery() {
        return $this->query;
    }

    /**
     * @param Query $query
     * @param Query $parentQuery
     * @return Query
     */
    public function getRelationExistenceCountQuery(Query $query, Query $parentQuery) {
        return $this->getRelationExistenceQuery(
            $query, $parentQuery, 'count(*)'
        );
    }

    public function getRelationExistenceQuery(Query $query, Query $parentQuery, $columns = ['*']) {
        foreach ($this->links as $fk => $lk) {
            $query->whereColumn(
                $parentQuery->getModel()->qualifyColumn($fk), '=',
                $lk
            );
        }
        return $query->select($columns);
    }

    public function __call($name, $arguments) {
        $this->query->$name(...$arguments);
        return $this;
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
     * 绑定已有数据
     * @param mixed $models 主数据
     * @param array $items 附加数据
     * @param string $key 附加key
     * @param array $links 关联
     * @param int $type 附加形式
     * @return array|Page
     * @throws \Exception
     */
    public static function bindRelation($models, array $items, string $key, array $links, $type = self::TYPE_ONE) {
        if (empty($models)) {
            return $models;
        }
        if ($models instanceof Page) {
            return $models->setPage(static::bindRelationArr($models->getPage(), $items, $key, $links, $type));
        }
        $is_one = !static::isSomeArr($models);
        if ($is_one) {
            $models = [$models];
        }
        $models = static::bindRelationArr($models, $items, $key, $links, $type);
        return $is_one ? reset($models) : $models;
    }

    public static function bindRelationArr($models, array $items, string $key, array $links, $type = self::TYPE_ONE) {
        if (empty($models)) {
            return $models;
        }
        $relation = new static();
        $relation->setKey($key);
        $relation->setLinks($links);
        $relation->setType($type);
        return $relation->buildRelation($models, $items);
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
            if (!empty($key)) {
                $data->setKey($key);
            }
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

    /**
     * 生成一个关系
     * @param SqlBuilder $builder
     * @param string $dataKey
     * @param string $foreignKey
     * @param int $type
     * @return array
     */
    public static function make(SqlBuilder $builder, string $dataKey, string $foreignKey, int $type = self::TYPE_ONE) {
        return [
            'query' => $builder,
            'link' => [
                $dataKey => $foreignKey
            ],
            'type' => $type,
        ];
    }
}