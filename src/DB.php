<?php
namespace Zodream\Database;

use Zodream\Database\Query\Query;

class DB {
    /**
     *
     * @param $table
     * @return static
     */
    public static function table($table) {
        return (new Query())->from($table);
    }
}