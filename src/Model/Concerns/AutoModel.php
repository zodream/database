<?php
namespace Zodream\Database\Model\Concerns;

use Zodream\Infrastructure\Http\Request;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Interfaces\ArrayAble;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/5/7
 * Time: 14:12
 */
trait AutoModel {

    protected $__oldAttributes = [];

    /**
     * 转载数据
     * @param string|array $data
     * @param null $key
     * @return bool
     */
    public function load($data = null, $key = null) {
        if (is_string($data)) {
            list($key, $data) = [$data, null];
        }
        if (!is_array($data) && Request::isPost()) {
            $data = Request::post($key);
        }
        if (empty($data)) {
            return false;
        }
        $this->set($data);
        return true;
    }

    public function set($key, $value = null){
        if (empty($key)) {
            return $this;
        }
        if ($key instanceof ArrayAble) {
            $key = $key->toArray();
        } elseif (is_object($key)) {
            $key = (array)$key;
        } elseif (!is_array($key)) {
            $key = [$key => $value];
        }
        foreach ($key as $k => $item) {
            $method = sprintf('set%sAttribute', Str::studly($k));
            if (method_exists($this, $method)) {
                $this->{$method}($item);
                continue;
            }
            if (property_exists($this, $k)) {
                $this->$k = $item;
                continue;
            }
            $this->__attributes[$k] = $item;
        }
        return $this;
    }

    public function isNewAttribute($key) {
        if (!$this->hasOldAttribute($key)) {
            return true;
        }
        return $this->getOldAttribute($key)
            === $this->getAttributeSource($key);
    }

    /**
     * 设置旧值
     * @param null $data
     * @return $this
     */
    public function setOldAttribute($data = null) {
        if (is_null($data)) {
            $data = $this->getAttribute();
        }
        $this->isNewRecord = false;
        $this->__oldAttributes = array_merge($this->__oldAttributes, $data);
        return $this;
    }

    public function getOldAttribute($key) {
        if (!$this->hasOldAttribute($key)) {
            return null;
        }
        return $this->__oldAttributes[$key];
    }

    public function hasOldAttribute($key) {
        return isset($this->__oldAttributes[$key])
            || array_key_exists($key, $this->__oldAttributes);
    }

    /**
     * 初始化旧值
     * @return $this
     */
    public function initOldAttribute() {
        $this->isNewRecord = true;
        $this->__oldAttributes = [];
        return $this;
    }
}