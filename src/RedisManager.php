<?php
declare(strict_types=1);
namespace Zodream\Database;

use Zodream\Database\Engine\Redis;
use Zodream\Infrastructure\Concerns\SingletonPattern;

class RedisManager extends Manager {

    use SingletonPattern;

    /**
     * @var Redis[]
     */
    protected array $engines = [];

    protected string $defaultDriver = Redis::class;

    protected $configKey = 'redis';

    /**
     * @param string $name
     * @return Redis
     */
    public static function connection($name = null) {
        return static::getInstance()->getEngine($name);
    }
}