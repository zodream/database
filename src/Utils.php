<?php
declare(strict_types=1);
namespace Zodream\Database;

use Zodream\Database\Contracts\Column;
use Zodream\Database\Contracts\Schema;
use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\Contracts\Table;

final class Utils {

    /**
     * 获取name
     * @param $value
     * @return string
     */
    public static function formatName($value): string {
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof SqlBuilder) {
            return $value->getTable();
        }
        if ($value instanceof Schema || $value instanceof Table || $value instanceof Column) {
            return $value->getName();
        }
        return (string)$value;
    }

    /**
     * 内容格式化成字符串，添加 ' 单引号
     * @param string $val
     * @return string
     */
    public static function wrapText(string $val): string {
        return var_export($val, true);
    }

    /**
     * 内容添加 ` 引号
     * @param string $name
     * @return string
     */
    public static function wrapName(string $name): string {
        if (str_contains($name, '`')) {
            return $name;
        }
        return '`'.$name.'`';
    }

    /**
     * 转化成数字
     * @param $value
     * @return int|float
     */
    public static function formatNumeric($value): int|float {
        if (empty($value)) {
            return 0;
        }
        if (is_int($value) || is_float($value) || is_double($value)) {
            return $value;
        }
        if (strpos($value, '.') > 0) {
            return floatval($value);
        }
        return intval($value);
    }
}