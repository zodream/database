<?php
declare(strict_types=1);
namespace Zodream\Database\Concerns;

interface Engine {

    public function grammar(): BuilderGrammar;
    public function schemaGrammar(): SchemaGrammar;
    public function information(): Information;
    public function open(): bool;
    public function version(): string;
    public function insert(string $sql, array $parameters = []);
    public function insertBatch(string $sql, array $parameters = []);
    public function update(string $sql, array $parameters = []): int;
    public function updateBatch(string $sql, array $parameters = []);
    public function delete(string $sql, array $parameters = []): int;
    public function execute(string $sql, array $parameters = []);
    public function executeScalar(string $sql, array $parameters = []);
    public function fetch(string $sql, array $parameters = []);
    public function fetchMultiple(string $sql, array $parameters = []);
    public function first(string $sql, array $parameters = []);

    /**
     * 第一次执行不返回任何数据, 最后返回null 以结束
     * @param string $sql
     * @param array $parameters
     * @return array|object|null
     */
    public function fetchRow(string $sql = '', array $parameters = []);

    public function lastInsertId(): int|string;
    public function rowCount(): int;

    public function transaction($cb): bool;
    public function transactionBegin(): bool;
    public function transactionCommit(array $args = []): bool;
    public function transactionRollBack(): bool;

    public function close(): bool;
}