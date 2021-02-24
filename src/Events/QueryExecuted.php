<?php
declare(strict_types=1);
namespace Zodream\Database\Events;


class QueryExecuted {

    public function __construct(
        /**
         * The SQL query that was executed.
         *
         * @var string
         */
        public string $sql,

        /**
         * The array of query bindings.
         *
         * @var array
         */
        public array $bindings,

        /**
         * The number of milliseconds it took to execute the query.
         *
         * @var float
         */
        public float $time,

        /**
         * The database connection instance.
         *
         * @var string
         */
        public string $connection,

        /**
         * The database connection name.
         *
         * @var string
         */
        public string $connectionName = '',
    ) {
    }
}