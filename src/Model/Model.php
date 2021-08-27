<?php
declare(strict_types=1);
namespace Zodream\Database\Model;
/**
 * 数据基类
 *
 * @author Jason
 */
use Zodream\Database\Model\Concerns\AutoModel;
use Zodream\Database\Model\Concerns\ExtendQuery;
use Zodream\Database\Model\Concerns\HasAttributes;
use Zodream\Database\Model\Concerns\HasRelation;
use Zodream\Database\Model\Concerns\HasTimestamps;
use Zodream\Database\Model\Concerns\SaveModel;
use Zodream\Database\Model\Concerns\ValidateAttributes;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Base\MagicObject;
use Zodream\Infrastructure\Concerns\ErrorTrait;
use Zodream\Infrastructure\Concerns\EventTrait;

abstract class Model extends MagicObject {

    use ErrorTrait, ExtendQuery, AutoModel, EventTrait, HasRelation, HasAttributes, ValidateAttributes, HasTimestamps, SaveModel;

    const BEFORE_SAVE = 'before save';
    const AFTER_SAVE = 'after save';
    const BEFORE_INSERT = 'before insert';
    const AFTER_INSERT = 'after insert';
    const BEFORE_UPDATE = 'before update';
    const AFTER_UPDATE = 'after update';
    const ERROR_NOT_DATA_CHANGE = '__not_change';

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

    public bool $isNewRecord = true;

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