<?php
namespace Zodream\Database\Model\Relations;

use Zodream\Database\Model\Model;
use Zodream\Database\Model\Query;

abstract class Relation {

    /**
     * @var Model
     */
    protected $parent;

    /**
     * @var Query
     */
    protected $query;

    public function __construct(Query $query, Model $parent) {
        $this->query = $query;
        $this->parent = $parent;
    }

    /**
     * 获取结果
     * @return Model|boolean
     */
    abstract public function getResults();
}