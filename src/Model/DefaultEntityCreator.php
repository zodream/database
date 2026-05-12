<?php
declare(strict_types=1);
namespace Zodream\Database\Model;

use Zodream\Database\Contracts\EntityCreator;
use Zodream\Database\Contracts\SqlBuilder;
use Zodream\Database\DB;
use Zodream\Database\Relation;
use Zodream\Helpers\Str;
use Zodream\Helpers\Time;
use Zodream\Helpers\Json;

final class DefaultEntityCreator implements EntityCreator {


    public function __construct(
        private readonly string $entityClassName,
        private readonly string $entityTableName,
        private Model|null $instance = null,
    ) {
    }


    public function tableName(): string {
        return $this->entityTableName;
    }

    public function fieldItems(): array {
        $key = 'table_'.$this->tableName();
        $driver = cache()->store('tables');
        $data = $driver->get($key);
        if (!empty($data)) {
            return unserialize($data);
        }
        $data = DB::information()->columnList($this->tableName(), true);
        $data = array_column($data, 'Field');
        $driver->set($key, serialize($data));
        return $data;
    }

    public function binding(string $name): Relation {
        if (!$this->instance) {
            $this->instance = new $this->entityClassName;
        }
        return call_user_func([$this->instance, $name]);
    }

    public function callScope(string $name, array $arguments): void {
        if (!$this->instance) {
            $this->instance = new $this->entityClassName;
        }
        $method = 'scope'.Str::studly($name);
        call_user_func_array([$this->instance, $method], $arguments);
    }

    public function qualifyColumn(string $column): string {
        if (Str::contains($column, '.')) {
            return $column;
        }

        return $this->tableName().'.'.$column;
    }

    public function create(array $data): Model {
        $model = new $this->entityClassName;
        if ($model instanceof Model) {
            $model->setAttribute($data);
        }
        return $model;
    }

    public function parse(array $data): Model {
        $model = new $this->entityClassName;
        if ($model instanceof Model) {
            $data = $this->getRealValues($data);
            $model->setAttributeToOld($data)
                ->setAttributeToSource($data);
        }
        return $model;
    }

    public function save(Model|array $data, bool $isUpdated = false): Model {
        $model = is_array($data) ? $this->create($data) : $data;
        if ($isUpdated) {
            $model->update($this);
        } else {
            $model->insert($this);
        }
        return $model;
    }

    public function lock(string $lockType = ''): void {
        DB::lock($this->tableName(), $lockType);
    }

    public function builder(): SqlBuilder {
        return (new Query($this))->from($this->tableName());
    }

    /**
     * 转换成真实的数据
     * @param array $data
     * @return array
     * @throws \Exception
     */
    private function getRealValues(array $data): array {
        $maps = static::getRealType($this->entityClassName);
        return empty($maps) ? $data : static::toRealArr($data, $maps);
    }


    public static function from(Model|string $entity): EntityCreator {
        if (is_object($entity)) {
            $entity = get_class($entity);
        }
        return new DefaultEntityCreator($entity, call_user_func($entity. '::tableName'), is_object($entity) ? $entity : null);
    }

    /**
     * 转换成真实的类型
     * @param array $data
     * @param array $maps
     * @return array
     */
    public static function toRealArr(array $data, array $maps): array {
	    foreach ($data as $key => $item) {
	        if (!isset($maps[$key])) {
	            continue;
            }
            $data[$key] = static::changeType($item, $maps[$key]);
        }
        return $data;
    }

    /**
     * 转换类型
     */
    public static function changeType(mixed $value, string $type): mixed {
        return match($type) {
            'int', 'integer' => intval($value),
            'float' => floatval($value),
            'double' => doubleval($value),
            'bool', 'boolean' => Str::toBool($value),
            'datetime' => Time::format($value),
            'ago' => Time::ago($value),
            'array' => Json::decode($value),
            default => $value
        };
    }

    /**
     * 获取真实的类型
     * @param string $class
     * @return bool|array
     * @throws \Exception
     */
    public static function getRealType($class): array {
        $callback = function () use ($class) {
            $instance = new \ReflectionClass($class);
            $doc = $instance->getDocComment();
            unset($instance);
            if (!is_string($doc)) {
                return [];
            }
            preg_match_all('/\@property\s+([a-z]+)\s+\$([a-z\d_]+)/i', $doc, $matches, PREG_SET_ORDER);
            return array_column($matches, 1, 2);
        };
        if (app()->isDebug()) {
            return $callback();
        }
        return cache()->getOrSet('class_doc_type:'.$class, $callback, 86400);
    }
}