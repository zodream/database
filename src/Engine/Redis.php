<?php
declare(strict_types=1);
namespace Zodream\Database\Engine;

/**
 * redis
 *
 * @author Jason
 */
use Zodream\Infrastructure\Base\ConfigObject;
use Zodream\Infrastructure\Error\Exception;
use Redis as RedisClient;

class Redis extends ConfigObject {
	private ?RedisClient $driver = null;

    protected array $configs = array(
        'host'     => '127.0.0.1',                //服务器
        'port'     => '6379',						//端口
    );

    public function __construct(array|RedisClient $config) {
        if (is_array($config)) {
            $this->setConfigs($config)->connect();
            return;
        }
        $this->setDriver($config);
	}

    protected function connect() {
        $this->driver = new RedisClient();
        $this->driver->connect($this->configs['host'], $this->configs['port']);
        if (isset($this->configs['password']) && !$this->driver->auth($this->configs['password'])) {
            throw new \Exception('redis password error!');
        }
        $this->driver->select(isset($this->configs['db']) ? intval($this->configs['db']) : 0);
    }

    /**
     * @param mixed $driver
     */
    public function setDriver(RedisClient $driver) {
        $this->driver = $driver;
    }

	/**
	 * 设置值  构建一个字符串
	 * @param string $key KEY名称
	 * @param string $value 设置值
	 * @param int $timeOut 时间  0表示无过期时间
	 * @return bool
	 */
	public function set(string $key, mixed $value, int $timeOut = 0): bool {
		$retRes = $this->driver->set($key, $value);
		if ($timeOut <= 0) {
			return $retRes;
		}
		$this->driver->expire($key, $timeOut);
		return true;
	}
	
	/**
	 * 构建一个集合(无序集合)
	 * @param string $key 集合Y名称
	 * @param string|array $value  值
	 */
	public function sadd(string $key, mixed $value) {
		return $this->driver->sadd($key, $value);
	}

	/**
	 * 构建一个集合(有序集合)
	 * @param string $key 集合名称
	 * @param $value
	 * @param int $score
	 * @return int
     */
	public function zadd(string $key, mixed $value, int $score = 1) {
		return $this->driver->zAdd($key, $score, $value);
	}

	/**
	 * 取集合对应元素
	 * @param string $setName 集合名字
	 * @return array
	 */
	public function smembers(string $setName){
		return $this->driver->smembers($setName);
	}

	/**
	 * 构建一个列表(先进后去，类似栈)
	 * @param string $key KEY名称
	 * @param string $value 值
	 * @return int
	 */
	public function lpush(string $key, mixed $value){
		return $this->driver->lPush($key, $value);
	}

	/**
	 * 构建一个列表(先进先去，类似队列)
	 * @param string $key KEY名称
	 * @param string $value 值
	 * @return int
	 */
	public function rpush(string $key, mixed $value){
		return $this->driver->rPush($key, $value);
	}

	/**
	 * 获取所有列表数据（从头到尾取）
	 * @param string $key KEY名称
	 * @param int $head 开始
	 * @param int $tail 结束
	 * @return array
	 */
	public function lranges(string $key, int $head, int $tail){
		return $this->driver->lrange($key, $head, $tail);
	}

	/**
	 * HASH类型
	 * @param string $tableName 表名字key
	 * @param string $field 字段名字
	 * @param string $value 值
	 * @return int
	 */
	public function hset(string $tableName, string $field, mixed $value){
		return $this->driver->hset($tableName, $field, $value);
	}

	public function setex(string $key, int $ttl, mixed $value) {
        return $this->driver->setex($key, $ttl, $value);
    }
	
	public function hget(string $tableName, string $field){
		return $this->driver->hget($tableName, $field);
	}


    /**
     * 设置多个值
     * @param array $keyArray KEY名称 获取得到的数据
     * @param int $timeout 时间
     * @return bool
     * @throws Exception
     */
	public function sets(array $keyArray, int $timeout): bool {
		$retRes = $this->driver->mset($keyArray);
		if ($timeout <= 0) {
			return $retRes;
		}
		foreach ($keyArray as $key => $value) {
			$this->driver->expire($key, $timeout);
		}
		return true;
	}

    /**
     * 通过key获取数据
     * @param string $key KEY名称
     * @return bool|string
     * @throws \RedisException
     */
	public function get(string $key): mixed {
		return $this->driver->get($key);
	}

    /**
     * 同时获取多个值
     * @param array $keyArray 获key数值
     * @return array|string
     * @throws \Exception
     */
	public function gets(array $keyArray): mixed {
        return $this->driver->mget($keyArray);
	}
	
	/**
	 * 获取所有key名，不是值
	 */
	public function keyAll(): array {
		return $this->driver->keys('*');
	}
	
	/**
	 * 删除一条数据key
	 * @param string $key 删除KEY的名称
	 */
	public function del(string $key) {
		$this->driver->del($key);
	}

    /**
     * 同时删除多个key数据
     * @param array $keyArray KEY集合
     * @return int|string
     * @throws \Exception
     */
	public function dels(array $keyArray) {
        return $this->driver->del($keyArray);
	}

	/**
	 * 数据自增
	 * @param string $key KEY名称
	 * @return int
	 */
	public function increment(string $key) {
		return $this->driver->incr($key);
	}

	/**
	 * 数据自减
	 * @param string $key KEY名称
	 * @return int
	 */
	public function decrement(string $key) {
		return $this->driver->decr($key);
	}


	/**
	 * 判断key是否存在
	 * @param string $key KEY名称
	 * @return bool
	 */
	public function isExists(string $key){
		return $this->driver->exists($key);
	}

	/**
	 * 重命名- 当且仅当newkey不存在时，将key改为newkey ，当newkey存在时候会报错哦RENAME
	 *  和 rename不一样，它是直接更新（存在的值也会直接更新）
	 * @param string $key KEY名称
	 * @param string $newKey 新key名称
	 * @return bool
	 */
	public function updateName(string $key, string $newKey): bool {
		return $this->driver->RENAMENX($key,$newKey);
	}

	/**
	 * 获取KEY存储的值类型
	 * none(key不存在) int(0)  string(字符串) int(1)   list(列表) int(3)  set(集合) int(2)   zset(有序集) int(4)    hash(哈希表) int(5)
	 * @param string $key KEY名称
	 * @return int
	 */
	public function dataType(string $key): int {
		return $this->driver->type($key);
	}
	
	
	/**
	 * 清空数据
	 */
	public function flushAll() {
		return $this->driver->flushAll();
	}

    public function flushDB() {
        return $this->driver->flushDB();
    }
	
	/**
	 * 返回redis对象
	 * redis有非常多的操作方法，我们只封装了一部分
	 * 拿着这个对象就可以直接调用redis自身方法
	 * eg:$redis->redisOtherMethods()->keys('*a*')   keys方法没封
	 */
    public function getDriver() {
        return $this->driver;
    }
}