<?php
declare(strict_types=1);
namespace Zodream\Database\Engine;

abstract class BaseEngine {
	
	protected $driver = null;
	protected $result;
	protected $version;
	
	protected $configs = [
	    'type'     => 'mysql',                //数据库类型
		'host'     => '127.0.0.1',                //服务器
		'port'     => '3306',						//端口
		'database' => 'test',				//数据库
		'user'     => 'root',						//账号
		'password' => '',					//密码
		'prefix'   => '',					//前缀
		'encoding' => 'utf8mb4',					//编码
		'persistent' => false,                   //使用持久化连接
        'result_type' => 'array',               // 结果返回类型 array | object
        'cache_expire' => false
	];
	 
	//私有克隆
	protected function __clone() {}

    /**
     * BaseEngine constructor.
     * @param array|resource|\mysqli|\PDO $config
     * @throws \Exception
     */
	public function __construct($config) {
        if (is_array($config)) {
            timer('db engine init');
            $this->configs = array_merge($this->configs, $config);
            $this->open();
            timer('db engine end');
            return;
        }
        $this->setDriver($config);
	}

	public function config(string $name = '') {
	    if (empty($name)) {
	        return $this->configs;
        }
	    return array_key_exists($name, $this->configs) ? $this->configs[$name] : null;
    }
	
	abstract public function open(): bool;

    /**
     * @return resource|\mysqli|\PDO
     */
	public function getDriver() {
		return $this->driver;
	}

    /**
     * @param mixed $driver
     */
	public function setDriver($driver) {
	    $this->driver = $driver;
    }

    /**
     * 是否需要结果返回object
     * @return bool
     */
    public function isObject(): bool {
	    return $this->configs['result_type'] == 'object'
            || $this->configs['result_type'] == '{}'
            || $this->configs['result_type'] === true;
    }


	/**
	 * 执行事务
	 * @param array|callable $cb
	 * @return bool
	 */
	public function transaction($cb): bool {
		$this->transactionBegin();
		try {
		    if (is_callable($cb)) {
		        call_user_func($cb, $this);
                $cb = [];
            }
			$this->transactionCommit($cb);
			return true;
		} catch (\Exception $ex) {
			$this->transactionRollBack();
			throw $ex;
		}
	}

    abstract public function transactionBegin(): bool;
    abstract public function transactionCommit(array $args = []): bool;
    abstract public function transactionRollBack(): bool;

	
}