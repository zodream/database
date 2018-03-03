<?php
namespace Zodream\Database\Model\Relations;

use Zodream\Database\Model\Model;
use Zodream\Database\Model\Query;
use Closure;

abstract class Relation {

    /**
     * Indicates if the relation is adding constraints.
     *
     * @var bool
     */
    protected static $constraints = true;

    /**
     * An array to map class names to their morph names in database.
     *
     * @var array
     */
    protected static $morphMap = [];

    /**
     * @var Model
     */
    protected $parent;

    /**
     * @var Query
     */
    protected $query;

    /**
     * The related model instance.
     *
     * @var Model
     */
    protected $related;

    public function __construct(Query $query, Model $parent) {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();
        $this->addConstraints();
    }

    /**
     * 获取结果
     * @return Model|boolean
     */
    abstract public function getResults();

    /**
     * Run a callback with constraints disabled on the relation.
     *
     * @param  \Closure  $callback
     * @return Relation|null
     */
    public static function noConstraints(Closure $callback) {
        $previous = static::$constraints;

        static::$constraints = false;

        // When resetting the relation where clause, we want to shift the first element
        // off of the bindings, leaving only the constraints that the developers put
        // as "extra" on the relationships, and not original relation constraints.
        try {
            return call_user_func($callback);
        } finally {
            static::$constraints = $previous;
        }
    }

    public function getRelated() {
        return $this->related;
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    abstract public function addConstraints();

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    abstract public function addEagerConstraints(array $models);

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    abstract public function initRelation(array $models, $relation);

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  mixed  $results
     * @param  string  $relation
     * @return array
     */
    abstract public function match(array $models, $results, $relation);

    /**
     * @param Model[] $models
     * @param null $key
     * @return mixed
     */
    protected function getKeys(array $models, $key = null) {
        $data = [];
        foreach ($models as $model) {
            $data[] = $model->getAttribute($key ?: $model->getKeyName());
        }
        $data = array_unique($data);
        sort($data);
        return $data;
    }

    public function getEager() {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return array
     */
    public function get($columns = ['*']) {
        return $this->query->select($columns)->all();
    }

    /**
     * Run a raw update against the base query.
     *
     * @param  array $attributes
     * @return int
     * @throws \Exception
     */
    public function rawUpdate(array $attributes = []) {
        return $this->query->update($attributes);
    }

    public function getRelationExistenceCountQuery(Query $query, Query $parentQuery) {
        return $this->getRelationExistenceQuery(
            $query, $parentQuery, 'count(*)'
        );
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param  Query  $query
     * @param  Query  $parentQuery
     * @param  array|mixed $columns
     * @return Query
     */
    public function getRelationExistenceQuery(Query $query, Query $parentQuery, $columns = ['*']) {
        return $query->select($columns)->whereColumn(
            $this->parent->qualifyColumn($this->parent->getKeyName()), '=', $this->getExistenceCompareKey()
        );
    }

    public function getExistenceCompareKey() {
        return '';
    }


    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters) {

        $result = call_user_func_array([$this->query, $method], $parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone() {
        $this->query = clone $this->query;
    }
}