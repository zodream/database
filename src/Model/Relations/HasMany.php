<?php
namespace Zodream\Database\Model\Relations;

use Zodream\Database\Model\Model;

class HasMany extends HasOneOrMany {
    /**
     * Get the results of the relationship.
     *
     * @return Model[]|boolean
     */
    public function getResults() {
        return $this->query->all();
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array $models
     * @param  string $relation
     * @return array
     */
    public function initRelation(array $models, $relation) {
        foreach ($models as $model) {
            $model->setRelation($relation, []);
        }
        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  array  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, $results, $relation) {
        return $this->matchMany($models, $results, $relation);
    }

    /**
     * @param Model[] $data
     * @return HasMany
     */
    public function saveMany(array $data) {
        foreach ($data as $model) {
            $model->{$this->foreignKey} = $this->parent->get($this->localKey);
            $model->save();
        }
        return $this;
    }
}
