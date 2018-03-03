<?php
namespace Zodream\Database\Model\Relations;


use Zodream\Database\Model\Model;

class HasOne extends HasOneOrMany {

    /**
     * Get the results of the relationship.
     *
     * @return Model|boolean
     */
    public function getResults() {
        return $this->query->one();
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  Model[] $models
     * @param  string $relation
     * @return array
     */
    public function initRelation(array $models, $relation) {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }
        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array  $models
     * @param  array  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, $results, $relation) {
        return $this->matchOne($models, $results, $relation);
    }
}
