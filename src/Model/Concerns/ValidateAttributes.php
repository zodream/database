<?php
declare(strict_types=1);
namespace Zodream\Database\Model\Concerns;

use Zodream\Validate\Validator;
use Exception;

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
     * @throws Exception
     */
    public function validate(array $rules = []) {
        if (empty($rules)) {
            $rules = $this->rules();
        }
        if ($this->validateAttribute($rules)) {
            return true;
        }
        logger()->error(sprintf('%s model validate error', static::class), $this->getError());
        return false;
    }

    /**
     * 验证属性值
     * @param array $rules
     * @return bool
     * @throws Exception
     */
    protected function validateAttribute(array $rules) {
        $validator = new Validator();
        foreach ($rules as $key => $item) {
            if (!$this->isNewRecord && !$this->hasAttribute($key)) {
                continue;
            }
            $item = $validator->converterRule($item);
            // 增加必须判断
            if (!$this->hasAttribute($key)
                    && !property_exists($this, $key)
                && !isset($item['rules']['required'])) {
                continue;
            }
            $value = $this->hasAttribute($key) ?
                $this->getAttributeValue($key) : $this->$key;
            foreach ($item['rules'] as $rule => $args) {
                if (is_callable($args)) {
                    if (false !== call_user_func($args, $value)) {
                        continue;
                    }
                    $validator->messages()->add($key, $validator->getMessage($key, $rule, $item['message']));
                    continue;
                }
                if (method_exists($this, $rule)) {
                    if (false !== call_user_func([$this, $rule], $value)) {
                        continue;
                    }
                    $validator->messages()->add($key, $validator->getMessage($key, $rule, $item['message']));
                    continue;
                }
                if (Validator::buildRule($rule, (array)$args)
                    ->validate($value)) {
                    continue;
                }
                $validator->messages()->add($key, $validator->getMessage($key, $rule, $item['message']));
            }
        }
        $this->setError($validator->messages()->getMessages());
        return !$this->hasError();
    }
}