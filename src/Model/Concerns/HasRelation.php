<?php
namespace Zodream\Database\Model\Concerns;

use Zodream\Database\Model\Model;
use Zodream\Database\Model\Query;
use Zodream\Database\Model\Relations\BelongsToMany;
use Zodream\Database\Model\Relations\HasMany;
use Zodream\Database\Model\Relations\HasOne;
use Zodream\Database\Model\Relations\Relation;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/5/7
 * Time: 14:21
 */
trait HasRelation {
    /**
     * GET RELATION
     * @var array
     */
    protected $relations = [];

    /**
     * @param string $table
     * @param string $foreignKey $table.$link
     * @param string $localKey $this.$key
     * @return Relation
     */
    public function hasOne($table, $foreignKey, $localKey = null) {
        if ($table instanceof Model) {
            $table = $table->className();
        }
        return new HasOne($this->getRelationQuery($table), $this,  $foreignKey, $localKey);
    }

    /**
     * GET RELATION WHERE SQL
     * @param string|array $links
     * @param string $key
     * @return array
     */
    protected function getRelationWhere($links, $key = null) {
        if (is_null($key) && !is_array($links)) {
            $key = in_array('id', $this->primaryKey) ? 'id' : reset($this->primaryKey);
        }
        if (!is_array($links)) {
            $links = [$links => $key];
        }
        foreach ($links as &$item) {
            $item = $this->get($item);
        }
        return $links;
    }

    /**
     * GET RELATION QUERY
     * @param static $table
     * @return Query
     */
    protected function getRelationQuery($table) {
        $query = new Query();
        if (class_exists($table)) {
            return $query->setModelName($table)
                ->from(call_user_func($table.'::tableName'));
        }
        return $query->from($table);
    }

    /**
     * @param string $table
     * @param string $link $table.$link
     * @param string $key $this.$key
     * @return Relation
     */
    public function hasMany($table, $link, $key = 'id') {
        if ($table instanceof Model) {
            $table = $table->className();
        }
        return new HasMany($this->getRelationQuery($table), $this, $link, $key);
    }

    public function belongsToMany($dist, $middle, $currentForeignKey, $distForeignKey) {

    }

    /**
     * Get a specified relationship.
     *
     * @param  string  $relation
     * @return Relation
     */
    public function getRelation($relation) {
        return $this->relations[$relation];
    }

    /**
     * Determine if the given relation is loaded.
     *
     * @param  string  $key
     * @return bool
     */
    public function relationLoaded($key) {
        return array_key_exists($key, $this->relations);
    }


    /**
     * Set the specific relationship in the model.
     *
     * @param  string  $relation
     * @param  mixed  $value
     * @return $this
     */
    public function setRelation($relation, $value) {
        $this->relations[$relation] = $value;
        return $this;
    }
}