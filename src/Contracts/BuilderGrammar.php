<?php
declare(strict_types=1);
namespace Zodream\Database\Contracts;

interface BuilderGrammar {

    public function compileQuery(SqlBuilder $builder): string;

    public function compileInsert(SqlBuilder $builder, array|string $columns = '', array|string $values = ''): string;

    public function compileUpdate(SqlBuilder $builder, array $data): string;

    public function compileDelete(SqlBuilder $builder): string;

    public function compileInsertOrUpdate(SqlBuilder $builder, array $insertData, array $updateData): string;

    public function compileInsertOrReplace(SqlBuilder $builder, array $data): string;

    public function cacheable(string $sql): bool;
}