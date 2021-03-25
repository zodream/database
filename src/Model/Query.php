<?php
declare(strict_types=1);
namespace Zodream\Database\Model;

use Zodream\Database\Relation;
use Zodream\Database\Query\Builder;
use Zodream\Helpers\Arr;
use Zodream\Helpers\Str;
use Closure;

class Query extends Builder {

    protected array $relations = [];

    /**
     * @var array [name => function($query) => void]
     */
    protected array $eagerLoad = [];

    protected string $modelName = '';

    /**
     * @var Model|null
     */
    protected ?Model $model = null;

    protected bool $isArray = false;

    public function getModel() {
        if (!$this->model instanceof Model) {
            $this->model = new $this->modelName;
        }
        return $this->model;
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function with(array|string $relations) {
        $eagerLoad = $this->parseWithRelations(is_string($relations) ? func_get_args() : $relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        return $this;
    }

    /**
     * Prevent the specified relations from being eager loaded.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function without(array|string $relations) {
        $this->eagerLoad = array_diff_key($this->eagerLoad, array_flip(
            is_string($relations) ? func_get_args() : $relations
        ));

        return $this;
    }

    /**
     * Add subselect queries to count the relations.
     *
     * @param mixed $relations
     * @return $this
     * @throws \Exception
     */
    public function withCount(array|string $relations) {
        if (empty($relations)) {
            return $this;
        }

        if (empty($this->selects)) {
            $this->select();
        }

        $relations = is_array($relations) ? $relations : func_get_args();

        foreach ($this->parseWithRelations($relations) as $name => $constraints) {
            // First we will determine if the name has been aliased using an "as" clause on the name
            // and if it has we will extract the actual relationship name and the desired name of
            // the resulting column. This allows multiple counts on the same relationship name.
            $segments = explode(' ', $name);

            $alias = null;

            if (count($segments) == 3 && strtolower($segments[1]) == 'as') {
                list($name, $alias) = [$segments[0], $segments[2]];
            }

            $relation = $this->getRelationWithoutConstraints($name);

            // Here we will get the relationship count query and prepare to add it to the main query
            // as a sub-select. First, we'll get the "has" query and use that to get the relation
            // count query. We will normalize the relation name then append _count as the name.
            $query = $relation->getRelationExistenceCountQuery(
                $relation->getQuery(), $this
            );

            // Finally we will add the proper result column alias to the query and run the subselect
            // statement against the query builder. Then we will return the builder instance back
            // to the developer for further constraint chaining that needs to take place on it.
            $column = $alias ?: strtolower($name.'_count');

            $this->selectSub($query, $column);
        }

        return $this;
    }

    /**
     * @param $relation
     * @return Relation
     */
    protected function getRelationWithoutConstraints($relation) {
        return $this->getModel()->{$relation}();
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param  array  $relations
     * @return array
     */
    protected function parseWithRelations(array $relations) {
        $results = [];

        foreach ($relations as $name => $constraints) {
            // If the "relation" value is actually a numeric key, we can assume that no
            // constraints have been specified for the eager load and we'll just put
            // an empty Closure with the loader so that we can treat all the same.
            if (is_numeric($name)) {
                $name = $constraints;

                list($name, $constraints) = Str::contains($name, ':')
                    ? $this->createSelectWithConstraint($name)
                    : [$name, function () {
                        //
                    }];
            }

            // We need to separate out any nested includes. Which allows the developers
            // to load deep relationships using "dots" without stating each level of
            // the relationship with its own key in the array of eager load names.
            $results = $this->addNestedWiths($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Create a constraint to select the given columns for the relation.
     *
     * @param  string  $name
     * @return array
     */
    protected function createSelectWithConstraint($name) {
        return [explode(':', $name)[0], function (Query $query) use ($name) {
            $query->select(explode(',', explode(':', $name)[1]));
        }];
    }

    /**
     * Parse the nested relationships in a relation.
     *
     * @param  string  $name
     * @param  array  $results
     * @return array
     */
    protected function addNestedWiths($name, $results) {
        $progress = [];

        // If the relation has already been set on the result array, we will not set it
        // again, since that would override any constraints that were already placed
        // on the relationships. We will only set the ones that are not specified.
        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;
            if (! isset($results[$last = implode('.', $progress)])) {
                $results[$last] = function () {
                    //
                };
            }
        }

        return $results;
    }

    /**
     * Get the relationships being eagerly loaded.
     *
     * @return array
     */
    public function getEagerLoads() {
        return $this->eagerLoad;
    }

    /**
     * Set the relationships being eagerly loaded.
     *
     * @param  array  $eagerLoad
     * @return $this
     */
    public function setEagerLoads(array $eagerLoad) {
        $this->eagerLoad = $eagerLoad;

        return $this;
    }

    public function setModelName($model) {
        if ($model instanceof Model) {
            $this->model = $model;
            $model = $model->className();
        }
        $this->modelName = $model;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getModelName(): string {
        return $this->modelName;
    }

    public function asArray() {
        $this->isArray = true;
        return $this;
    }

    /**
     * @return array|object[]|Model[]
     * @throws \Exception
     */
    public function all() {
        $data = parent::all();
        if (empty($data)
            || $this->isArray
            || !class_exists($this->modelName)) {
            return $data;
        }
        $args = [];
        foreach ($data as $item) {
            $item = $this->getRealValues($item);
            /** @var $model Model */
            $model = new $this->modelName;
            $model->setOldAttribute($item)
                ->setSourceAttribute($item);
            $args[] = $model;
        }
        return $this->eagerLoadRelations($args);
    }

    public function each(callable $cb, ...$fields): array
    {
        return parent::each(function (array $item) use ($cb) {
            if ($this->isArray || !class_exists($this->modelName)) {
                return call_user_func($cb, $item);
            }
            $item = $this->getRealValues($item);
            /** @var $model Model */
            $model = new $this->modelName;
            $model->setOldAttribute($item)
                ->setSourceAttribute($item);
            return call_user_func($cb, $model);
        }, ...$fields);
    }

    /**
     * 转换成真实的数据
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function getRealValues(array $data) {
        $maps = Arr::getRealType($this->modelName);
        return empty($maps) ? $data : Arr::toRealArr($data, $maps);
    }


    /**
     * 取一个值
     * @return bool|int|string
     * @throws \Exception
     */
    public function scalar() {
        $this->asArray();
        return parent::scalar();
    }

    /**
     * @param array $attributes
     * @return Model
     */
    public function firstOrNew(array $attributes = []): Model {
        /** @var $model Model */
        $model = self::first();
        if (!empty($model)) {
            return $model;
        }
        $model = new $this->modelName;
        $model->set($attributes);
        return $model;
    }

    /**
     * @param array $attributes
     * @return Model|bool
     */
    public function firstWithReplace(array $attributes = []): ?Model {
        $model = self::first();
        if (empty($model)) {
            return null;
        }
        /** @var $model Model */
        $model->set($attributes);
        return $model;
    }

    public function pluck($column = null, $key = null) : array {
        $this->asArray();
        return parent::pluck($column, $key);
    }



    /**
     * Eager load the relationships for the models.
     *
     * @param  array  $models
     * @return array
     */
    public function eagerLoadRelations(array $models) {
        foreach ($this->eagerLoad as $name => $constraints) {
            // For nested eager loads we'll skip loading them here and they will be set as an
            // eager load on the query to retrieve the relation so that they will be eager
            // loaded on that query, because that is where they get hydrated as models.
            if (!str_contains($name, '.')) {
                $models = $this->eagerLoadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     *
     * @param  array  $models
     * @param  string  $name
     * @param  Closure  $constraints
     * @return array
     */
    protected function eagerLoadRelation(array $models, $name, Closure $constraints) {
        $relation = $this->getRelation($name);
        $constraints($relation);
        $relation->setKey($name);
        return $relation->get($models);
    }

    /**
     * Get the relation instance for the given relation name.
     *
     * @param string $name
     * @return Relation
     */
    public function getRelation(string $name) {
        // We want to run a relationship query without any constrains so that we will
        // not have to remove these where clauses manually which gets really hacky
        // and error prone. We don't want constraints because we add eager ones.
        try {
            /** @var Relation $relation */
            $relation = $this->getModel()->{$name}();
        } catch (\Exception $e) {
            throw $e;
        };

        $nested = $this->relationsNestedUnder($name);

        // If there are nested relationships set on the query, we will put those onto
        // the query instances so that they can be handled after this relationship
        // is loaded. In this way they will all trickle down as they are loaded.
        if (count($nested) > 0) {
            $relation->getQuery()->with($nested);
        }

        return $relation;
    }

    /**
     * Get the deeply nested relations for a given top-level relation.
     *
     * @param string $relation
     * @return array
     */
    protected function relationsNestedUnder(string $relation) {
        $nested = [];

        // We are basically looking for any relationships that are nested deeper than
        // the given top-level relationship. We will just check for any relations
        // that start with the given top relations and adds them to our arrays.
        foreach ($this->eagerLoad as $name => $constraints) {
            if ($this->isNestedUnder($relation, $name)) {
                $nested[substr($name, strlen($relation.'.'))] = $constraints;
            }
        }

        return $nested;
    }

    /**
     * Determine if the relationship is nested.
     *
     * @param string $relation
     * @param string $name
     * @return bool
     */
    protected function isNestedUnder(string $relation, string $name) {
        return Str::contains($name, '.') && Str::startsWith($name, $relation.'.');
    }


    /***
     * 使用 model 中的方法
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments) {
        $method = 'scope'.Str::studly($name);
        array_unshift($arguments, $this);
        call_user_func_array([$this->getModel(), $method], $arguments);
        return $this;
    }
}