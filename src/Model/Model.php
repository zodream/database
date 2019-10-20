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
use Zodream\Database\Model\Concerns\ValidateAttributes;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Base\MagicObject;
use Zodream\Infrastructure\Traits\ErrorTrait;
use Zodream\Infrastructure\Traits\EventTrait;

abstract class Model extends MagicObject {

    use ErrorTrait, AutoModel, EventTrait, HasRelation, HasAttributes, ValidateAttributes, HasTimestamps, SaveModel;

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
     * 主键
     * @var string
     */
    protected $primaryKey = 'id';



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



	public function __construct($data = []) {
	    if (!empty($data)) {
	        $this->load($data);
        }
		$this->init();
	}
	
	public function init() {
		
	}

    /**
     * 判断字段是不是主键
     * @param $key
     * @return bool
     */
	public function isPrimaryKey($key) {
	    return $key == $this->primaryKey;
    }

    public function getKeyName() {
	    return $this->primaryKey;
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
		if (isset($labels[$key])) {
			return $labels[$key];
		}
		return ucwords(str_replace('_', ' ', $key));
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
			$param = [$model->getKeyName() => $param];
		}
		if (!is_array($param) || !array_key_exists('where', $param)) {
			$param = [
				'where' => $param
			];
		}
		$query = static::query()
            ->load($param);
		if ($field !== '*' || empty($query->selects)) {
            $query->select($field);
        }
		return $query->addBinding($parameters)
			->first();
	}

    /**
     * @param integer|string $id
     * @param string $key
     * @return static
     * @throws \Exception
     */
	public static function findWithAuth($id, $key = 'id') {
	    return static::where($key, $id)->where('user_id', auth()->id())->one();
    }

    /**
     * 查找或新增
     * @param $param
     * @param string $field
     * @param array $parameters
     * @return bool|Model|static
     * @throws \Exception
     */
	public static function findOrNew($param, $field = '*', $parameters = array()) {
	    if (empty($param)) {
	        return new static();
        }
	    $model = static::find($param, $field, $parameters);
	    if (empty($model)) {
	        return new static();
        }
        return $model;
    }

    /**
     * Set not found default data
     * @param $param
     * @param array $attributes
     * @return bool|Model
     * @throws \Exception
     */
    public static function findOrDefault($param, array $attributes) {
        $model = self::findOrNew($param);
        if ($model->isNewRecord) {
            $model->set($attributes);
        }
        return $model;
    }

    /**
     * Set new attr
     * @param $param
     * @param array $attributes
     * @return bool|Model
     * @throws \Exception
     */
    public static function findWithReplace($param, array $attributes) {
        $model = self::findOrNew($param);
        $model->set($attributes);
        return $model;
    }

    /**
     * 查找或报错
     * @param $param
     * @param string $field
     * @param array $parameters
     * @return bool|Model
     * @throws \Exception
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

    public function qualifyColumn($column) {
        if (Str::contains($column, '.')) {
            return $column;
        }

        return static::tableName().'.'.$column;
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