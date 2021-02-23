<?php
declare(strict_types=1);
namespace Zodream\Database;

use Zodream\Database\Contracts\Column;
use Zodream\Database\Contracts\Schema;
use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\Contracts\Table;

final class Utils {

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

    public static function wrapText(string $val) {
        return '\''.$val.'\'';
    }

    public static function wrapName(string $name) {
        return '`'.$name.'`';
    }

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