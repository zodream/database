<?php
namespace Zodream\Database\Model;

use Zodream\Database\Model\Relations\Relation;
use Zodream\Database\Query\Query as BaseQuery;
use Zodream\Helpers\Str;
use Closure;

class Query extends BaseQuery {

    protected $relations = [];

    protected $eagerLoad = [];

    protected $modelName;

    /**
     * @var Model
     */
    protected $model;

    protected $isArray = false;

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
    public function with($relations) {
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
    public function without($relations) {
        $this->eagerLoad = array_diff_key($this->eagerLoad, array_flip(
            is_string($relations) ? func_get_args() : $relations
        ));

        return $this;
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

    public function asArray() {
        $this->isArray = true;
        return $this;
    }

    /**
     * @param bool $isArray
     * @return array|object[]|Model[]
     */
    public function all($isArray = true) {
        $data = parent::all($isArray);
        if (empty($data)
            || $this->isArray
            || !$isArray
            || !class_exists($this->modelName)) {
            return $data;
        }
        $args = [];
        foreach ($data as $item) {
            /** @var $model Model */
            $model = new $this->modelName;
            $model->setOldData($item)->set($item);
            $args[] = $model;
        }
        return $args;
    }

    /**
     * 取一个值
     * @return bool|int|string
     */
    public function scalar() {
        $this->asArray();
        return parent::scalar();
    }

    public function pluck($column = null, $key = null) {
        $this->asArray();
        return parent::pluck($column, $key);
    }

    /**
     * 更新
     * @param array $args
     * @return int
     */
    public function update(array $args) {
        return $this->command()
            ->update($this->compileUpdate($args), $this->getBindings());
    }

    /**
     * 删除
     * @return int
     */
    public function delete() {
        return $this->command()
            ->delete($this->compileDelete(), $this->getBindings());
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
            if (strpos($name, '.') === false) {
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
        // First we will "back up" the existing where conditions on the query so we can
        // add our eager constraints. Then we will merge the wheres that were on the
        // query back to it in order that any where conditions might be specified.
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        $constraints($relation);

        // Once we have the results, we just match those back up to their parent models
        // using the relationship instance. Then we just return the finished arrays
        // of models which have been eagerly hydrated and are readied for return.
        return $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(), $name
        );
    }

    /**
     * Get the relation instance for the given relation name.
     *
     * @param  string  $name
     * @return Relation
     */
    public function getRelation($name) {
        // We want to run a relationship query without any constrains so that we will
        // not have to remove these where clauses manually which gets really hacky
        // and error prone. We don't want constraints because we add eager ones.
        $relation = Relation::noConstraints(function () use ($name) {
            try {
                return $this->getModel()->{$name}();
            } catch (\Exception $e) {
                throw $e;//RelationNotFoundException::make($this->getModel(), $name);
            }
        });

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
     * @param  string  $relation
     * @return array
     */
    protected function relationsNestedUnder($relation) {
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
     * @param  string  $relation
     * @param  string  $name
     * @return bool
     */
    protected function isNestedUnder($relation, $name) {
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