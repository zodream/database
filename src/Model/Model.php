<?php
namespace Zodream\Database\Model;
/**
 * 数据基类
 *
 * @author Jason
 */
use Zodream\Database\Model\Concerns\AutoModel;
use Zodream\Database\Model\Concerns\HasAttributes;
use Zodream\Database\Model\Concerns\HasRelation;
use Zodream\Database\Model\Concerns\HasTimestamps;
use Zodream\Database\Model\Concerns\SaveModel;
use Zodream\Database\Model\Concerns\ValidateData;
use Zodream\Database\Query\Record;
use Zodream\Infrastructure\Base\MagicObject;
use Zodream\Infrastructure\Traits\ErrorTrait;
use Zodream\Infrastructure\Traits\EventTrait;

abstract class Model extends MagicObject {

    use ErrorTrait, AutoModel, EventTrait, HasRelation, HasAttributes, ValidateData, HasTimestamps, SaveModel;

    const BEFORE_SAVE = 'before save';
    const AFTER_SAVE = 'after save';
    const BEFORE_INSERT = 'before insert';
    const AFTER_INSERT = 'after insert';
    const BEFORE_UPDATE = 'before update';
    const AFTER_UPDATE = 'after update';

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    public $isNewRecord = true;

    /**
     * 是否保存所有字段
     * @var bool
     */
    protected $isFullColumns = false;



	/**
	 * 标签
	 * @return array
	 */
	protected function labels() {
		return [];
	}

	/**
	 * 表名
	 * @return string
	 */
	public static function tableName() {
	    return '';
    }

    public static function className() {
	    return static::class;
    }

	/**
	 * 主键
	 * @var array
	 */
	protected $primaryKey = [
		'id'
	];

	public function __construct($data = []) {
	    if (!empty($data)) {
	        $this->load($data);
        }
		$this->init();
	}
	
	public function init() {
		
	}

    /**
     * 判断是否是新增
     * @return bool
     */
	public function getIsNewRecord() {
	    return $this->isNewRecord;
    }

	/**
	 * @param string $key
	 * @return string
	 */
	public function getLabel($key) {
		$labels = $this->labels();
		if (array_key_exists($key, $labels)) {
			return $labels[$key];
		}
		return ucwords(str_replace('_', ' ', $key));
	}


    /**
     * @return Record
     */
	public static function record() {
		return (new Record())
            ->setTable(static::tableName());
	}


    /**
     * SELECT ONE BY QUERY
     * 查询一条数据
     *
     * @access public
     *
     * @param array|string $param 条件
     * @param string $field
     * @param array $parameters
     * @return static|boolean
     * @throws \Exception
     */
	public static function find($param, $field = '*', $parameters = array()) {
	    if (empty($param)) {
	        return false;
        }
		$model = new static;
		if (is_numeric($param)) {
			$param = [$model->primaryKey[0] => $param];
		}
		if (!is_array($param) || !array_key_exists('where', $param)) {
			$param = [
				'where' => $param
			];
		}
		return static::query()
			->load($param)
			->select($field)
			->addBinding($parameters)
			->one();
	}

    /**
     * 查找或新增
     * @param $param
     * @param string $field
     * @param array $parameters
     * @return bool|Model|static
     */
	public static function findOrNew($param, $field = '*', $parameters = array()) {
	    $model = static::find($param, $field, $parameters);
	    if (empty($model)) {
	        return new static();
        }
        return $model;
    }

    /**
     * 查找或报错
     * @param $param
     * @param string $field
     * @param array $parameters
     * @return bool|Model
     */
    public static function findOrThrow($param, $field = '*', $parameters = array()) {
        $model = static::find($param, $field, $parameters);
        if (empty($model)) {
            throw new \InvalidArgumentException($param);
        }
        return $model;
    }



	/**
	 * 查询数据
	 *
	 * @access public
	 *
	 * @return Query 返回查询结果,
	 */
	public static function query() {
		return (new Query())
            ->setModelName(static::className())
            ->from(static::tableName());
	}

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false) {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static((array) $attributes);
        $model->isNewRecord = !$exists;
        return $model;
    }


    /**
     * @param $method
     * @param $arguments
     * @return Query|array
     */
	public static function __callStatic($method, $arguments) {
		return call_user_func_array([
           	static::query(), $method], $arguments);
    }
}