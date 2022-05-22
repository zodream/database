<?php
declare(strict_types=1);
namespace Zodream\Database\Model;

use Zodream\Database\DB;

class AiModel extends Model {
    protected static string $tableName;

    public static function tableName() {
        return static::$tableName;
    }

    public static function table(string $table) {
        return new static($table);
    }

    public function __construct(string $table = '') {
        parent::__construct();
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
        $data = DB::information()->columnList(static::tableName(), true);
        $data = array_column($data, 'Field');
        $driver->set($key, serialize($data));
        return $data;
    }

}