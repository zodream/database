<?php
declare(strict_types=1);
namespace Zodream\Database\Model\Concerns;

use Zodream\Database\Model\Query;
use Zodream\Database\Relation;

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
    protected array $relations = [];

    /**
     * 1对1关联
     * @param string $table 目标表
     * @param string $foreignKey $table.$link 目标表字段
     * @param string $localKey $this.$key 当前表字段
     * @return Relation
     */
    public function hasOne(string $table, string $foreignKey, string $localKey = '') {
        return Relation::parse([
            'query' => $this->getRelationQuery($table),
            'type' => Relation::TYPE_ONE,
            'link' => [
                $localKey => $foreignKey
            ]
        ]);
    }

    /**
     * GET RELATION QUERY
     * @param string $table
     * @return Query
     */
    protected function getRelationQuery(string $table) {
        if (class_exists($table)) {
            return call_user_func($table.'::query');
        }
        return (new Query())->from($table);
    }

    /**
     * 关联多条数据
     * @param string $table 目标表
     * @param string $link $table.$link 目标表的字段
     * @param string $key $this.$key 当前表的字段
     * @return Relation
     */
    public function hasMany(string $table, string $link, string $key = 'id') {
        return Relation::parse([
            'query' => $this->getRelationQuery($table),
            'type' => Relation::TYPE_MANY,
            'link' => [
                $key => $link
            ]
        ]);
    }

    /**
     * 通过中间表获取目标表数据
     * @param string $dist 目标表
     * @param string $middle 中间表
     * @param string $middleKeyLinkCurrent 中间表字段能链接当前表
     * @param string $middleKeyLinkDist 中间表字段能链接目标表
     * @param string $currentKey 当前表的字段
     * @param string $distKey 目标表的字段
     * @return Relation
     */
    public function belongsToMany(
        string $dist, string $middle,
        string $middleKeyLinkCurrent,
        string $middleKeyLinkDist, string $currentKey = 'id', string $distKey = 'id') {
        return Relation::parse([
            'query' => $this->getRelationQuery($middle),
            'type' => Relation::TYPE_MANY,
            'link' => [
                $currentKey => $middleKeyLinkCurrent
            ],
            'relation' => [
                [
                    'query' => $this->getRelationQuery($dist),
                    'type' => Relation::TYPE_ONE,
                    'link' => [
                        $middleKeyLinkDist => $distKey
                    ],
                ]
            ]
        ]);
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
    public function relationLoaded(string $key) {
        return array_key_exists($key, $this->relations);
    }


    /**
     * Set the specific relationship in the model.
     *
     * @param  string  $relation
     * @param  mixed  $value
     * @return $this
     */
    public function setRelation(string $relation, $value) {
        $this->relations[$relation] = $value;
        return $this;
    }
}