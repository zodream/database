<?php
namespace Zodream\Database\Model\Concerns;

use Zodream\Validate\Validator;

trait ValidateAttributes {
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
     * @throws \Exception
     */
    public function validate($rules = array()) {
        if (empty($rules)) {
            $rules = $this->rules();
        }
        $validator = new Validator();
        foreach ($rules as $key => $item) {
            if (!$this->isNewRecord && !$this->hasAttribute($key)) {
                continue;
            }
            $value = $this->getAttributeValue($key);
            $item = $validator->converterRule($item);
            foreach ($item['rules'] as $rule => $args) {
                if (is_callable($args)) {
                    if (false !== call_user_func($args, $value)) {
                        continue;
                    }
                    $validator->messages()->add($key, $validator->getMessage($key, $rule, $rule['message']));
                    continue;
                }
                if (method_exists($this, $rule)) {
                    if (false !== call_user_func([$this, $rule], $value)) {
                        continue;
                    }
                    $validator->messages()->add($key, $validator->getMessage($key, $rule, $rule['message']));
                    continue;
                }
                if (Validator::buildRule($rule, (array)$args)
                    ->validate($value)) {
                    continue;
                }
                $validator->messages()->add($key, $validator->getMessage($key, $rule, $rule['message']));
            }
        }
        $this->setError($validator->messages()->all());
        return !$this->hasError();
    }
}