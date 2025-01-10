<?php
declare(strict_types=1);
namespace Zodream\Database\Model\Concerns;

use Zodream\Helpers\Str;
use Zodream\Infrastructure\Contracts\ArrayAble;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2017/5/7
 * Time: 14:12
 */
trait AutoModel {

    protected array $__oldAttributes = [];

    /**
     * 转载数据
     * @param string|array $data
     * @param string|array $key
     * @param array $excludes 排除的数组
     * @return bool
     * @throws \Exception
     */
    public function load(array|string|null $data = null, array|string $key = '', array $excludes = []): bool {
        if (is_string($data)) {
            list($key, $excludes, $data) = [$data, $key, null];
        }
        if (is_array($key)) {
            list($key, $excludes) = ['', $key];
        }
        if (!is_array($data) && request()->isPost()) {
            $data = request()->get($key);
        }
        if (empty($data)) {
            return false;
        }
        if (!empty($excludes)) {
            $data = array_diff_key($data, array_flip((array)$excludes));
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
            $method = sprintf('set%sAttribute', Str::studly((string)$k));
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
        return $this->getAttributeFromOld($key)
            !== $this->getAttributeSource($key);
    }

    /**
     * 设置原数据
     * @param $key
     * @param null $value
     * @return $this
     */
    public function setAttributeToSource(array|string|null $key, mixed $value = null) {
        if (empty($key)) {
            return $this;
        }
        if (!is_array($key)) {
            $this->__attributes[$key] = $value;
            return $this;
        }
        $this->__attributes = $key;
        return $this;
    }

    /**
     * 设置旧值
     * @param array|null $data
     * @return $this
     * @throws \Exception
     */
    public function setAttributeToOld(array|null $data = null) {
        if (is_null($data)) {
            $data = $this->getAttribute();
        }
        $this->isNewRecord = false;
        $this->__oldAttributes = array_merge($this->__oldAttributes, $data);
        return $this;
    }

    public function getAttributeFromOld(string $key) {
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