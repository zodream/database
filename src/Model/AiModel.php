<?php
namespace Zodream\Database\Model;

use Zodream\Database\Schema\Table;
use Zodream\Service\Factory;

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

    public function __construct($table = null) {
        if (empty($table)) {
            return;
        }
       static::$tableName = $table;
    }

    public function getTableFields() {
        $key = 'table_'.static::tableName();
        $data = Factory::cache()->get($key);
        if (!empty($data)) {
            return unserialize($data);
        }
        $data = (new Table(static::tableName()))->getAllColumn();
        $data = array_column($data, 'Field');
        Factory::cache()->set($key, serialize($data));
        return $data;
    }

}