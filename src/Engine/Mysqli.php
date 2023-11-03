<?php
declare(strict_types=1);
namespace Zodream\Database\Engine;

use Exception;
use Zodream\Database\Adapters\MySql\BuilderGrammar;
use Zodream\Database\Adapters\MySql\Information;
use Zodream\Database\Adapters\MySql\SchemaGrammar;
use Zodream\Database\Contracts\BuilderGrammar as BuilderInterface;
use Zodream\Database\Contracts\Engine;
use Zodream\Database\Contracts\Information as InformationInterface;
use Zodream\Database\Contracts\SchemaGrammar as SchemaInterface;

class Mysqli extends BaseEngine implements Engine {

	/**
	 * @var \mysqli
	 */
	protected $driver = null;

	/**
	 * @var \mysqli_stmt
	 */
	protected $result;

	protected $version;

	public function open(): bool
    {
        if (empty($this->configs)) {
            throw new Exception('Mysql host is not set');
        }
        $host = $this->configs['host'];
        if ($this->configs['persistent'] === true) {
            $host = 'p:'.$host;
        }
        $this->driver = new \mysqli(
            $host,
            $this->configs['user'],
            $this->configs['password'],
            $this->configs['database'],
            $this->configs['port']
        )
        or throw new Exception('There was a problem connecting to the database');
        /* check connection */
        /*if (mysqli_connect_errno()) {
         printf("Connect failed: %s\n", mysqli_connect_error());
         exit();
        }*/
        if (isset($this->configs['encoding'])) {
            $this->driver->set_charset($this->configs['encoding']);
        }
        return true;
    }

    public function insertBatch(string $sql, array $parameters = [])
    {
        $this->execute($sql, $parameters);
        return $this->rowCount();
    }

    public function updateBatch(string $sql, array $parameters = [])
    {
        return $this->update($sql, $parameters);
    }

    public function executeScalar(string $sql, array $parameters = [])
    {
        $res = $this->execute($sql, $parameters);
        $result = $res->result_metadata();
        $item = $result->fetch_assoc();
        mysqli_free_result($result);
        return empty($item) ? null : current($item);
    }

    public function fetch(string $sql, array $parameters = [])
    {
        $res = $this->execute($sql, $parameters);
        $result = $res->result_metadata();
        $items = $this->readRows($result);
        mysqli_free_result($result);
        return $items;
    }

    public function fetchMultiple(string $sql, array $parameters = [])
    {
        $items = [];
        if (mysqli_multi_query($this->driver, $sql)) {                                           //执行多个查询
            do {
                if ($res = mysqli_store_result($this->driver)) {
                    $items[] = $this->readRows($res);
                    mysqli_free_result($res);
                }
                /*if (mysqli_more_results($this_mysqli)) {
                 echo ("-----------------<br>");                   //连个查询之间的分割线
                 }*/
            } while (mysqli_next_result($this->driver));
        }
        return $items;
    }

    public function first(string $sql, array $parameters = [])
    {
        $res = $this->execute($sql, $parameters);
        $result = $res->result_metadata();
        $item = $this->readRow($result);
        mysqli_free_result($result);
        return $item;
    }

    public function fetchRow(string $sql = '', array $parameters = [])
    {
        if (!empty($sql)) {
            $this->execute($sql, $parameters);
            return true;
        }
        if ($this->result instanceof \mysqli_stmt) {
            $this->result = $this->result->result_metadata();
        }
        return $this->readRow($this->result);
    }

    protected function readRows(\mysqli_result $res): array {
        $items = [];
        while (!!$item = $this->readRow($res)) {
            $items[] = $item;
        }
        return $items;
    }

    protected function readRow(\mysqli_result $res) {
        if (!$this->isObject()) {
            return mysqli_fetch_assoc($res);
        }
        return mysqli_fetch_object($res);
    }

    public function transactionBegin(): bool
    {
        return $this->driver->autocommit(false);
    }

    public function transactionCommit(array $args = []): bool
    {
        foreach ($args as $item) {
            $this->driver->query($item);
        }
        if ($this->driver->errno > 0) {
            throw new \Exception('事务执行失败!');
        }
        return $this->driver->commit();
    }

    public function transactionRollBack(): bool
    {
        return $this->driver->rollback();
    }

    public function close(): bool
    {
        if (!empty($this->result) && !is_bool($this->result)) {
            if ($this->result instanceof \mysqli_result) {
                mysqli_free_result($this->result);
            } else {
                mysqli_stmt_free_result($this->result);
            }
        }
        mysqli_close($this->driver);
        $this->result = null;
        $this->driver = null;
        return true;
    }

    public function version(): string
    {
        if (empty($this->version)) {
            $this->version = $this->information()->version();
        }
        return $this->version;
    }

    public function insert(string $sql, array $parameters = [])
    {
        $this->execute($sql, $parameters);
        return $this->lastInsertId();
    }

    public function update(string $sql, array $parameters = []): int
    {
        $this->execute($sql, $parameters);
        return $this->rowCount();
    }

    public function delete(string $sql, array $parameters = []): int
    {
        $this->execute($sql, $parameters);
        return $this->rowCount();
    }

    public function execute(string $sql, array $parameters = [])
    {
        if (empty($sql)) {
            throw new Exception('sql is empty');
        }
        $this->prepare($sql);
        $this->bind($parameters);
        $error = mysqli_error($this->driver);
        if (!empty($error)) {
            throw new Exception($error);
        }
        logger()->info(sprintf('MYSQLI: %s => %s', $sql, $error));
        if (!$this->result->execute()) {
            throw new Exception(mysqli_error($this->driver));
        }
        return $this->result;
    }

    /**
     * 预处理
     * @param string $sql
     */
    public function prepare(string $sql) {
        $this->result = $this->driver->prepare($sql);
    }

    /**
     * 绑定值 只支持 ？ 绑定
     * @param array $params
     * @throws \ReflectionException
     */
    public function bind(array $params) {
        mysqli_stmt_bind_param($this->result, str_repeat('s', count($params)), ...$params);
    }

    public function lastInsertId(): int|string
    {
        return mysqli_insert_id($this->driver);
    }

    public function rowCount(): int
    {
        return mysqli_affected_rows($this->driver);
    }

    public function grammar(): BuilderInterface
    {
        return new BuilderGrammar();
    }

    public function schemaGrammar(): SchemaInterface
    {
        return new SchemaGrammar();
    }

    public function information(): InformationInterface
    {
        return new Information();
    }

    public function escapeString(mixed $value): string {
        return var_export(mysqli_real_escape_string($this->driver, (string)$value), true);
    }
}