<?php
namespace Zodream\Database\Model\Relations;

use Zodream\Database\Model\Model;
use Zodream\Database\Model\Query;

abstract class HasOneOrMany extends Relation {
    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * The count of self joins.
     *
     * @var int
     */
    protected static $selfJoinCount = 0;

    /**
     * Create a new has one or many relationship instance.
     *
     * @param  Query  $query
     * @param  Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Query $query, Model $parent, $foreignKey, $localKey) {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Create and return an un-saved instance of the related model.
     *
     * @param  array  $attributes
     * @return Model
     */
    public function make(array $attributes = []) {
//        return tap($this->related->newInstance($attributes), function ($instance) {
//            $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
//        });
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints() {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());
            $this->query->whereNotNull($this->foreignKey);
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models) {
        $this->query->whereIn(
            $this->foreignKey, $this->getKeys($models, $this->localKey)
        );
    }


    /**
     * Get a relationship join table hash.
     *
     * @return string
     */
    public function getRelationCountHash() {
        return 'laravel_reserved_'.static::$selfJoinCount++;
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getExistenceCompareKey() {
        return $this->getQualifiedForeignKeyName();
    }

    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName() {
        return $this->parent->tableName().'.'.$this->localKey;
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName() {
        $segments = explode('.', $this->getQualifiedForeignKeyName());

        return $segments[count($segments) - 1];
    }

    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKeyName() {
        return $this->foreignKey;
    }

    public function matchOne(array $models, array $results, $relation) {
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array   $models
     * @param  array  $results
     * @param  string  $relation
     * @return array
     */
    public function matchMany(array $models, array $results, $relation) {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array   $models
     * @param  array  $results
     * @param  string  $relation
     * @param  string  $type
     * @return array
     */
    protected function matchOneOrMany(array $models, array $results, $relation, $type) {
        $dictionary = $this->buildDictionary($results);



        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->getAttribute($this->localKey)])) {
                $model->setRelation(
                    $relation, $this->getRelationValue($dictionary, $key, $type)
                );
            }
        }

        return $models;
    }

    /**
     * @param Model[] $results
     * @return mixed
     */
    protected function buildDictionary(array $results) {
        $foreign = $this->getForeignKeyName();
        $data = [];
        foreach ($results as $result) {
            $data[$result->{$foreign}][] = $result;
        }
        return $data;
    }

    /**
     * Get the value of a relationship by one or many type.
     *
     * @param  array   $dictionary
     * @param  string  $key
     * @param  string  $type
     * @return mixed
     */
    protected function getRelationValue(array $dictionary, $key, $type) {
        $value = $dictionary[$key];
        return $type == 'one' ? reset($value) : $value;
    }

    /**
     * 保存一个
     * @param Model $model
     * @return $this
     */
    public function save(Model $model) {
        if ($model->isPrimaryKey($this->foreignKey)) {
            $model->save();
            $this->parent->{$this->localKey} = $model->{$this->foreignKey};
            $this->parent->save();
            return $this;
        }
        $model->{$this->foreignKey} = $this->parent->{$this->localKey};
        $model->save();
        return $this;
    }

    /**
     * 新建
     * @param array $data
     * @return mixed
     */
    public function create(array $data) {
        $data[$this->foreignKey] = $this->parent->{$this->localKey};
        return call_user_func(sprintf('%s::create', $this->query->getModelName()), $data);
    }
}
