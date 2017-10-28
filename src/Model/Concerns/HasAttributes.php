<?php
namespace Zodream\Database\Model\Concerns;

use Zodream\Database\Model\Relations\Relation;
use Zodream\Helpers\Str;
/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/5/7
 * Time: 14:20
 */
trait HasAttributes {

    protected $hidden = []; //隐藏

    protected $visible = [];  //显示

    protected $append = []; //追加

    public function getAttribute($key = null, $default = null){
        if (is_null($key)) {
            return $this->getAllAttributes();
        }
        $method = sprintf('get%sAttribute', Str::studly($key));
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }
        if ($this->has($key)) {
            return $this->__attributes[$key];
        }
        if (!method_exists($this, $key)) {
            return $default;
        }
        return $this->getRelationValue($key);
    }

    public function getAllAttributes() {
        $data = [];
        foreach ($this->__attributes as $key => $value) {
            $data[$key] = $this->getAttribute($key);
        }
        return $data;
    }

    public function getAttributeValue($key = null) {
        return $this->getAttributeSource($key);
    }

    public function getAttributeSource($key = null) {
        return parent::getAttribute($key);
    }

    public function getRelationValue($key) {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
        $result = call_user_func([$this, $key]);
        if ($result instanceof Relation) {
            $result = $result->getResults();
        }
        return $this->relations[$key] = $result;
    }

    /**
     * 判断是否为空
     * @param null $key
     * @return bool
     */
    public function isEmpty($key = null) {
        if (is_null($key)) {
            return count($this->__attributes) == 0;
        }
        return !$this->has($key) || empty($this->__attributes[$key]);
    }

    /**
     * @param string|array $key
     * @return bool
     */
    public function has($key = null) {
        if (!is_array($key)) {
            return parent::has($key);
        }
        foreach ($key as $item) {
            if (array_key_exists($item, $this->__attributes)) {
                return true;
            }
        }
        return false;
    }

    public function setAttribute($key, $value = null) {
        if (is_array($key)) {
            return parent::setAttribute($key, $value);
        }
        $method = 'set'.Str::studly($key).'Attribute';
        if (method_exists($this, $method)) {
            return $this->{$method}($value);
        }
        return parent::setAttribute($key, $value);
    }

    /**
     * Get the hidden attributes for the model.
     *
     * @return array
     */
    public function getHidden() {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param  array  $hidden
     * @return $this
     */
    public function setHidden(array $hidden){
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Add hidden attributes for the model.
     *
     * @param  array|string|null  $attributes
     * @return void
     */
    public function addHidden($attributes = null) {
        $this->hidden = array_merge(
            $this->hidden, is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return array
     */
    public function getVisible() {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     *
     * @param  array  $visible
     * @return $this
     */
    public function setVisible(array $visible){
        $this->visible = $visible;
        return $this;
    }

    /**
     * Add visible attributes for the model.
     *
     * @param  array|string|null  $attributes
     * @return void
     */
    public function addVisible($attributes = null) {
        $this->visible = array_merge(
            $this->visible, is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Make the given, typically hidden, attributes visible.
     *
     * @param  array|string  $attributes
     * @return $this
     */
    public function makeVisible($attributes) {
        $this->hidden = array_diff($this->hidden, (array) $attributes);
        if (! empty($this->visible)) {
            $this->addVisible($attributes);
        }
        return $this;
    }

    /**
     * Make the given, typically visible, attributes hidden.
     *
     * @param  array|string  $attributes
     * @return $this
     */
    public function makeHidden($attributes) {
        $attributes = (array) $attributes;
        $this->visible = array_diff($this->visible, $attributes);
        $this->hidden = array_unique(array_merge($this->hidden, $attributes));
        return $this;
    }

    protected function getArrayAbleItems(array $values) {
        if (count($this->getVisible()) > 0) {
            $values = array_intersect_key($values, array_flip($this->getVisible()));
        }
        if (count($this->getHidden()) > 0) {
            $values = array_diff_key($values, array_flip($this->getHidden()));
        }
        return $values;
    }

    public function toArray() {
        return $this->getArrayAbleItems($this->getAllAttributes());
    }
}