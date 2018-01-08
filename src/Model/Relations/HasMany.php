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
    public function initRelation(array $models, $relation)
    {
        // TODO: Implement initRelation() method.
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array $models
     * @param  mixed $results
     * @param  string $relation
     * @return array
     */
    public function match(array $models, $results, $relation)
    {
        // TODO: Implement match() method.
    }
}
