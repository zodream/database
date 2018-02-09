<?php
namespace Zodream\Database\Model\Concerns;

use Zodream\Domain\Filter\DataFilter;
use Zodream\Domain\Filter\ModelFilter;

trait ValidateData {
    /**
     * 过滤规则
     * @return array
     */
    protected function rules() {
        return [];
    }

    /**
     * 自定义验证错误信息
     * @return array
     */
    protected function messages() {
        return [];
    }

    /**
     * 判断是否有列名
     * @param $key
     *
     * @return bool
     */
    public function hasColumn($key) {
        return array_key_exists($key, $this->rules());
    }

    /**
     * 验证
     * @param array $rules
     * @return bool
     */
    public function validate($rules = array()) {
        if (empty($rules)) {
            $rules = $this->rules();
        }
        $result = true;
        foreach ($rules as $key => $rule) {
            if (is_integer($key) && is_array($rule)) {
                $key = array_shift($rule);
            }
            $result = !$result ? $result : $this->_validateOne($key, $rule);
        }
        return $result && !$this->hasError();
    }

    /**
     * 验证 int 时 bool 要转换可以通过
     * @param $key
     * @param $rule
     * @return bool
     */
    private function _validateOne($key, $rule) {
        $key = (array)$key;
        $method = is_array($rule) ? current($rule) : $rule;
        if (!is_callable($method) &&
            (!is_string($method) || !method_exists($this, $method))) {
            return $this->_validateByFilter($key, $rule);
        }
        $result = true;
        if (is_string($method)) {
            $method = [$this, $method];
        }
        foreach ($key as $k) {
            if (!$this->isNewRecord && !$this->hasAttribute($k)) {
                continue;
            }
            if (false !== call_user_func($method, $this->getAttributeValue($k))) {
                continue;
            }
            $result = false;;
            if (is_array($rule) && array_key_exists('message', $rule)) {
                $this->setError($k, str_replace('{key}', $rule['message']));
            }
        }
        return $result;
    }

    /**
     * @param $key
     * @param $rule
     * @return bool
     */
    private function _validateByFilter($key, $rule) {
        DataFilter::clearError();
        $rule = DataFilter::getFilters($rule);
        foreach ($key as $k) {
            if (!$this->isNewRecord && !$this->hasAttribute($k)) {
                continue;
            }
            if (!DataFilter::validateOneKey($this->getAttributeValue(),
                $rule, $k)) {
                $this->setError(DataFilter::getError());
                return false;
            }
        }
        return !$this->hasError();
    }
}