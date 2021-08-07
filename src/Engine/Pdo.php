<?php 
namespace Zodream\Database\Engine;

use Exception;
use Zodream\Database\Adapters\MySql\BuilderGrammar;
use Zodream\Database\Adapters\MySql\Information;
use Zodream\Database\Adapters\MySql\SchemaGrammar;
use Zodream\Database\Contracts\BuilderGrammar as BuilderInterface;
use Zodream\Database\Contracts\Engine;
use Zodream\Database\Contracts\Information as InformationInterface;
use Zodream\Database\Contracts\SchemaGrammar as SchemaInterface;

class Pdo extends BaseEngine implements Engine {

    const MYSQL = 'mysql';
    const MSSQL = 'dblib';
    const ORACLE = 'oci';
    const SQLSRV = 'sqlsrv';

	/**
	 * @var \PDO
	 */
	protected $driver = null;

	/**
	 * @var \PDOStatement
	 */
	protected $result;

    public function open(): bool
    {
        try {
            //$this->driver = new \PDO('mysql:host='.$host.';port='.$port.';dbname='.$database, $user, $pwd ,
            //                     array(\PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES {$coding}"));
            $this->driver = new \PDO (
                $this->getDsn(),
                $this->configs['user'],
                $this->configs['password'],
                array(
                    \PDO::ATTR_PERSISTENT => $this->configs['persistent'] === true,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, //默认是PDO::ERRMODE_SILENT, 0, (忽略错误模式)
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // 默认是PDO::FETCH_BOTH, 4
                )
            );
            if ($this->getType() == self::MYSQL) {
                $this->driver->exec ('SET NAMES '.$this->configs['encoding']);
                $this->driver->query ( "SET character_set_client={$this->configs['encoding']}" );
                $this->driver->query ( "SET character_set_connection={$this->configs['encoding']}" );
                $this->driver->query ( "SET character_set_results={$this->configs['encoding']}" );
            }
        } catch (\PDOException $ex) {
            throw $ex;
        }
        return true;
    }

    public function getDsn() {
        if ($this->getType() == self::SQLSRV) {
            return sprintf('sqlsrv:server=%s;Database=%s',
                $this->configs['host'],
                $this->configs['database']
            );
        }
        return sprintf('%s:host=%s;port=%s;dbname=%s',
            $this->getType(),
            $this->configs['host'],
            $this->configs['port'],
            $this->configs['database']
        );
    }

    /**
     * 获取连接数据库的类型
     * @return string
     */
    public function getType() {
        if (!array_key_exists('type', $this->configs)
            || empty($this->configs['type'])) {
            return self::MYSQL;
        }
        return strtolower($this->configs['type']);
    }

    public function transactionBegin(): bool
    {
        return $this->driver->beginTransaction();
    }

    public function transactionCommit(array $args = []): bool
    {
        foreach ($args as $item) {
            $this->driver->exec($item);
        }
        return $this->driver->commit();
    }

    public function transactionRollBack(): bool
    {
        return $this->driver->rollBack();
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

    public function insertBatch(string $sql, array $parameters = [])
    {
        $this->execute($sql, $parameters);
        return $this->rowCount();
    }

    public function update(string $sql, array $parameters = []): int
    {
        $this->execute($sql, $parameters);
        return $this->rowCount();
    }

    public function updateBatch(string $sql, array $parameters = [])
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
        try {
            $this->prepare($sql);
            $this->bind($parameters);
            $this->result->execute();
        } catch (\PDOException  $ex) {
            logger()->info(sprintf('PDO: %s => %s', $sql, $ex->getMessage()), $parameters);
            throw $ex;
        }
        return $this->result;
    }

    public function executeScalar(string $sql, array $parameters = [])
    {
        $this->execute($sql, $parameters);
        return $this->result->fetchColumn(0);
    }

    public function fetch(string $sql, array $parameters = [])
    {
        $this->execute($sql, $parameters);
        return $this->readRows($this->result);
    }

    public function fetchMultiple(string $sql, array $parameters = [])
    {
        // TODO: Implement fetchMultiple() method.
    }

    public function first(string $sql, array $parameters = [])
    {
        $this->execute($sql, $parameters);
        return $this->readRow($this->result);
    }

    public function fetchRow(string $sql = '', array $parameters = [])
    {
        if (!empty($sql)) {
            $this->execute($sql, $parameters);
            return true;
        }
        return $this->readRow($this->result);
    }

    protected function readRows(\PDOStatement $res): array {
        return $res->fetchAll($this->isObject() ? \PDO::FETCH_CLASS : \PDO::FETCH_ASSOC);
    }

    protected function readRow(\PDOStatement $res) {
        return $res->fetch($this->isObject() ? \PDO::FETCH_CLASS : \PDO::FETCH_ASSOC);
    }

    public function lastInsertId(): int|string
    {
        return $this->driver->lastInsertId();
    }

    public function rowCount(): int
    {
        return $this->result->rowCount();
    }

    /**
     * 预处理
     * @param string $sql
     */
    public function prepare(string $sql) {
        $this->result = $this->driver->prepare($sql);
    }

    /**
     * 绑定值
     * @param array $param
     */
    public function bind(array $param) {
        foreach ($param as $key => $value) {
            if (is_null($value)) {
                $type = \PDO::PARAM_NULL;
            } else if (is_bool($value)) {
                $type = \PDO::PARAM_BOOL;
            } else if (is_int($value)) {
                $type = \PDO::PARAM_INT;
            } else {
                $type = \PDO::PARAM_STR;
            }
            $this->result->bindValue(is_int($key) ? ++$key : $key, $value, $type);
        }
    }

    public function close(): bool
    {
        unset($this->result);
        $this->driver = null;
        return true;
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

    public function escapeString(string $value): string
    {
        return $this->driver->quote($value);
    }
}