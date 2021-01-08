<?php
declare(strict_types=1);
namespace Zodream\Database\Model;

use Zodream\Database\Schema\Table;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2017/3/31
 * Time: 19:41
 */
class AiModel extends Model {
    protected static $tableName;

    public static function tableName() {
        return static::$tableName;
    }

    public static function table($table) {
        return new static($table);
    }

    public function __construct($table = '') {
        if (empty($table)) {
            return;
        }
       static::$tableName = $table;
    }

    public function getTableFields() {
        $key = 'table_'.static::tableName();
        $driver = cache()->store('tables');
        $data = $driver->get($key);
        if (!empty($data)) {
            return unserialize($data);
        }
        $data = (new Table(static::tableName()))->getAllColumn();
        $data = array_column($data, 'Field');
        $driver->set($key, serialize($data));
        return $data;
    }

}