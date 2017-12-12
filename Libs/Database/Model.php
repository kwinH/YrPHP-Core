<?php
/**
 * Project: YrPHP.
 * User: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 * GitHub:https://github.com/kwinH/YrPHP
 */

namespace YrPHP\Database;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use YrPHP\Arr;


/**
 * Class Model
 * @package YrPHP\Database
 *
 * @method \PDO getMasterPdo()
 * @method \PDO getSlavePdo()
 * @method bool startTrans()
 * @method bool rollback()
 * @method bool commit()
 * @method bool|object transaction(\Closure $callback)
 * @method int count()
 * @method int sum()
 * @method int min()
 * @method int max()
 * @method int avg()
 * @method DB having($where = '', $logical = "and")
 * @method DB where($where = '', $logical = "and")
 * @method DB order($sql = '')
 * @method DB group($sql = '')
 * @method DB select($field = [])
 * @method DB except($field = [])
 * @method null|string table($tableName = "", $auto = true)
 * @method null toSql()
 * @method array|bool|int query($sql = '', $parameters = [])
 * @method DB get($field = '*')
 * @method Model first($field = '*')
 * @method DB limit($offset, $length = null)
 * @method DB page($page, $listRows = null)
 * @method DB join($table = '', $cond = [], $type = '', $auto = true)
 * @method int delete($where = "")
 * @method int duplicateKey($data)
 * @method int insert($data = [])
 * @method int replace($data = [])
 * @method int inserts($data = [])
 * @method int replaces($data = [])
 * @method bool|int getLastId()
 * @method array|bool tableField()
 * @method int update($data = [], $where = "")
 * @method array history()
 * @method string lastQuery()
 * @method string lastSql()
 *
 */
class Model implements IteratorAggregate, ArrayAccess
{
    protected static $mutatorCache = [];
    /**
     * 原始数据
     * @var array
     */
    protected $original = [];
    /**
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * 模型的加载关系
     *
     * @var array
     */
    protected $relations = [];

    protected $relationType = null;

    protected $primaryKey = 'id';
    protected $table = null;

    protected $connection = null;

    protected $sql;

    protected $parameters = [];

    public function __construct(array $attributes = [])
    {
        $this->original = $attributes;
        $this->attributes = $attributes;
    }


    /**
     * 获取当前连接
     * @return null
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * 获取与模型相关联的表
     *
     * @return string
     */
    public function getTable()
    {
        if (is_null($this->table)) {
            return parseNaming(basename(str_replace('\\', '/', get_class($this))), 2);
        }
        return $this->table;
    }

    /**
     * 设置与模型关联的表
     *
     * @param  string $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * 获取模型的主键
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     *
     * @param  string $key
     * @return $this
     */
    public function setKeyName($key)
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * 以主键为条件 查询
     * @param int $id 查询的条件主键值
     * @return Model
     */
    public function find($id = 0)
    {
        return $this->newQuery($this)
            ->where([$this->getKeyName() => $id])
            ->first();
    }

    /**
     * 集合
     * @param string $field
     * @return Collection
     */
    public function all($field = '*')
    {
        return $this->newQuery($this)->get($field);
    }

    /**
     * 添加/修改
     * @return bool|mixed|string
     */
    public function save()
    {
        if (empty($this->attributes)) {
            return true;
        }

        if (empty($this->original)) {
            $this->attributes[$this->primaryKey] = $this->newQuery($this)->insert($this->attributes);
            $this->original = $this->attributes;
            return $this->attributes[$this->primaryKey];
        } else {
            $data = array_merge(
                array_intersect_key($this->attributes, $this->original),
                array_diff_key($this->attributes, $this->original)
            );
            if (isset($this->original[$this->primaryKey])) {
                $where[$this->primaryKey] = $this->original[$this->primaryKey];
            } else {
                $where = $this->original;
            }
            return $this->newQuery($this)->update($data, $where);
        }
    }

    /**
     * 删除
     * @param null $ids
     * @return int
     */
    public final function destroy($ids = null)
    {
        $db = new DB($this);
        $argsNum = func_num_args();
        $primaryKey = $this->getKeyName();
        if (func_num_args() === 0) {
            if (isset($this->original[$this->primaryKey])) {
                $where[$this->primaryKey] = $this->original[$this->primaryKey];
            } else {
                $where = $this->original;
            }
        } else if ($argsNum > 1) {
            $where[$primaryKey . ' in'] = func_get_args();
        } else if (is_array($ids)) {
            $where[$primaryKey . ' in'] = $ids;
        } else {
            $where[$primaryKey] = $ids;
        }

        return $db->delete($where);
    }

    /**
     * 获取子类中所有访问器或则修改器
     * @return array
     */
    public function getMutatedAttributes($type = 'get')
    {
        $class = get_class($this);

        if (!isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class][$type];
    }

    /**
     * 匹配子类中所有访问器和修改器
     * @param $class
     */
    public static function cacheMutatedAttributes($class)
    {
        $mutatedAttributes = [
            'get' => [],
            'set' => [],
        ];

        if (preg_match_all('/(?<=^|;)(get|set)([^;]+?)Attribute(;|$)/',
            implode(';', array_diff(get_class_methods($class), get_class_methods(__CLASS__))),
            $matches)) {

            foreach ($matches[2] as $key => $match) {

                //   $match = parseNaming($match, 2);

                if ($matches[1][$key] == 'get') {
                    $mutatedAttributes['get'][$match] = $matches[0][$key];
                } else {
                    $mutatedAttributes['set'][$match] = $matches[0][$key];
                }
            }
        }

        static::$mutatorCache[$class] = $mutatedAttributes;
    }


    /**
     * @param Model $model
     * @return DB
     */
    protected function newQuery(Model $model)
    {
        return loadClass(DB::class, $model);
    }

    public function __call($name, $arguments)
    {
        return $this->newQuery($this)->$name(...$arguments);
    }


    public function getPreProcess($key, $value)
    {
        $preProcessCaches = $this->getMutatedAttributes('get');

        if ($method = Arr::arrayIGet($preProcessCaches, $key)) {
            $value = $this->$method($value);
        }
        return $value;
    }


    /**
     * 字段修改器存在则运行
     *
     * @param  string $key
     * @param  mixed $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if ($method = $this->hasSetMutator($key)) {
            $this->attributes[$key] = $this->{$method}($value);
        } else {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * 判断字段是否存在修改器
     *
     * @param  string $key
     * @return bool|string
     */
    protected function hasSetMutator($key)
    {
        if (method_exists($this, ($method = 'set' . parseNaming($key, 1) . 'Attribute'))) {
            return $method;
        }
        return false;
    }


    /**
     * 判断是否为空
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->original);
    }

    /**
     * 定义一对一关系
     *
     * @param  string $related
     * @param  string $foreignKey
     * @param  string $localKey
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        if (!class_exists($related)) {
            return new static();
        }

        /**
         * @var $instance Model
         */
        $instance = new $related;

        $foreignKey = $foreignKey ? $foreignKey : $this->getTable() . '_id';
        $localKey = $localKey ? $localKey : $this->getKeyName();

        $instance->setRelationType('one');
        return $this->newQuery($instance)->where([$foreignKey => $this->original[$localKey]]);
    }

    /**
     * 定义一对多关系
     * @param $related
     * @param null $foreignKey
     * @param null $localKey
     * @return DB|array
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        if (!class_exists($related)) {
            return [];
        }
        /**
         * @var $instance Model
         */
        $instance = new $related;

        $foreignKey = $foreignKey ? $foreignKey : $this->getTable() . '_id';
        $localKey = $localKey ? $localKey : $this->getKeyName();

        $instance->setRelationType('many');
        return $this->newQuery($instance)->where([$foreignKey => $this->original[$localKey]]);
    }

    /**
     * 获取关联数据
     * @param $key
     * @return mixed|null
     */
    protected function getRelationValue($key)
    {
        if (!isset($this->relations[$key]) && method_exists($this, $key)) {
            /**
             * @var $query Model
             */
            $query = $this->$key();

            if ($query->getRelationType() == 'one') {
                $this->relations[$key] = $query->first();
            } else {
                $this->relations[$key] = $query->get();
            }
        }

        return empty($this->relations[$key]) ? null : $this->relations[$key];

    }

    /**
     * 动态设置模型上的属性
     *
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    function __get($name)
    {
        if (isset($this->attributes[$name])) {
            return $this->getPreProcess($name, $this->attributes[$name]);
        }

        return $this->getRelationValue($name);

    }

    /**
     * @return null
     */
    public function getRelationType()
    {
        return $this->relationType;
    }

    /**
     * 设置返回数据是单条还是多条
     * @param null $relationType one|many
     */
    protected function setRelationType($relationType)
    {
        $this->relationType = $relationType;
    }

    /**
     * @return mixed
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * @param mixed $sql
     */
    public function setSql($sql)
    {
        $this->sql = $sql;
    }

    /**
     * @return array
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * @param array $original
     */
    public function setOriginal($original)
    {
        $this->original = $original;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param array $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }


    /**
     * 获取预处理绑定数据
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * 设置预处理绑定数据
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->attributes);
    }


    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }


    public function offsetGet($offset)
    {
        return $this->getPreProcess($offset, $this->attributes[$offset]);

    }

    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }


    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }


}