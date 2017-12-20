<?php
/**
 * Project: YrPHP.
 * User: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 * GitHub:https://github.com/kwinH/YrPHP
 */

namespace YrPHP\Database;

use Closure;
use Exception;
use PDO;
use PDOException;
use YrPHP\Arr;
use YrPHP\Cache;
use YrPHP\Config;
use Event;

/**
 * @method int count()
 * @method int sum()
 * @method int min()
 * @method int max()
 * @method int avg()
 */
class DB
{
    /**
     * PDO服务器群
     * @var PDO[]
     */
    public static $PdoFarm = [];

    /**
     * 确保事务是操作同一个PDO
     * @var PDO
     */
    protected $transactionPdo = null;

    /**
     * 最后一次操作的数据库实例
     * @var PDO
     */
    public $lastPdo = null;


    //是否验证 将验证字段在数据库中是否存在，不存在 则舍弃 再验证 $validate验证规则 不通过 则报错
    public $_validate = true;


    // 数据表前缀
    protected $tablePrefix = null;

    // 链操作方法列表
    protected $methods = [
        'field' => '',
        'where' => '',
        'order' => '',
        'limit' => '',
        'group' => '',
        'having' => '',
        'join' => [],
        'on' => '',
    ];

    /**
     * 默认表名称
     * @var null
     */
    protected $tableName = null;

    /**
     * 临时表名
     * @var null
     */
    protected $tempTableName = null;


    //要被预处理和执行的 SQL 语句
    protected $statement;

    //是否开启缓存 bool
    protected $openCache;

    //是否开启缓存,单次有效 bool
    protected $tmpOpenCache;

    // query 预处理绑定的参数
    protected $parameters = [];

    /**
     * 注册的事件
     * @var array
     */
    protected $events = [];

    //执行过的sql
    private $queries = [];

    /**
     *
     * @var mixed|null
     */
    protected $connection = null;

    /**
     * 数据库配置
     * @var array|mixed
     */
    private $dbConfig = [];


    /**
     * 表模型是否定义
     * @var bool
     */
    public $exists = false;

    /**
     * @var Model
     */
    protected $model;


    public function __construct(Model $model)
    {
        $this->model = $model;
        if (is_subclass_of($model, Model::class)) {
            $this->tableName = $this->model->getTable();
            $this->exists = true;
        }

        $this->dbConfig = Config::get('database');
        if ($this->exists && ($connection = $this->model->getConnection())) {
            $this->setConnection($connection);
        } else {
            $this->setConnection($this->dbConfig['defaultConnection']);
        }

        $this->openCache = Config::get('openCache');
        $this->tmpOpenCache = $this->openCache;
    }


    /**
     * 获取主服务器PDO
     * @return PDO
     */
    public function getMasterPdo()
    {
        if (($this->transactionPdo instanceof PDO)) {
            return $this->transactionPdo;
        }

        $dbConfig = $this->dbConfig[$this->getConnection()];
        if (isset($dbConfig['master'])) {
            $dbConfig = array_merge($dbConfig, array_rand($dbConfig['master']));
        }

        return $this->getPdo($dbConfig);
    }

    /**
     * 获取从服务器PDO
     * @return PDO
     */
    public function getSlavePdo()
    {
        $dbConfig = $this->dbConfig[$this->getConnection()];
        if (isset($dbConfig['slave'])) {
            $dbConfig = array_merge($dbConfig, array_rand($dbConfig['slave']));
        } else {
            return $this->getMasterPdo();
        }

        return $this->getPdo($dbConfig);
    }

    /**
     * 连接PDO
     * @param array $dbConfig
     * @return PDO
     */
    protected function getPdo(array $dbConfig)
    {
        $dsn = $dbConfig['type'] . ":host=" . $dbConfig['host'] . ";port=" . $dbConfig['port'] . ";dbname=" . $dbConfig['dbname'];
        $key = md5($dsn);

        if (!isset(static::$PdoFarm[$key])) {
            try {
                static::$PdoFarm[$key] = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], array(PDO::ATTR_PERSISTENT => true));
                static::$PdoFarm[$key]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);// 设置为异常模式)))

            } catch (PDOException $e) {
                echo '数据库连接失败: ' . $e->getMessage();
                exit;
            }
        }

        static::$PdoFarm[$key]->exec("SET NAMES '{$dbConfig['charset']}'");
        return static::$PdoFarm[$key];
    }


    /**
     * 启动事务处理模式
     * @return bool 成功返回true，失败返回false
     */
    public final function startTrans()
    {
        if (is_null($this->transactionPdo)) {
            $this->transactionPdo = $this->getMasterPdo();

            $this->transactionPdo->beginTransaction();
        }
        return true;
    }

    /**
     * 事务回滚
     * @return bool 成功返回true，失败返回false
     */
    public final function rollback()
    {
        if ($this->transactionPdo instanceof PDO) {
            $this->transactionPdo->rollback();
        }
        $this->transactionPdo = null;
        return true;
    }


    /**
     * 提交事务
     * @return bool 成功返回true，失败返回false
     */
    public final function commit()
    {
        if ($this->transactionPdo instanceof PDO) {
            $this->transactionPdo->commit();
        }
        $this->transactionPdo = null;
        return true;
    }


    /**
     * 启动事务处理模式
     * @return bool|object 恒等于true时，成功，其余情况为失败
     */
    public final function transaction(Closure $callback)
    {
        try {
            $this->startTrans();

            $callback($this);

            $this->commit();
            return true;
        } catch (\Exception $err) {
            $this->rollback();
            return (object)['code' => $err->getCode(), 'message' => $err->getMessage()];
        }
    }

    /**
     * @param bool $tmpOpenCache
     */
    public function setOpenCache($OpenCache = true)
    {
        $this->tmpOpenCache = $OpenCache;
    }

    /**
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }


    /**
     * 给字符串添加反引号
     * @param string $field
     * @return array|string
     */
    protected function escapeId($field = '')
    {
        if (empty($field)) {
            return '';
        } elseif (is_array($field)) {
            return trim(array_reduce($field, function ($result, $item) {
                return $result . ',' . $this->escapeId($item);
            }), ',');
        } elseif ($field instanceof Closure) {
            return call_user_func($field);
        } else {
            //$fullField[1]为别名
            $fullField = preg_split('/\s(as|\s+)\s*/', trim($field));
            $field = $fullField[0];

            if ($re = preg_match('/(.+)\((.+)\)/', $field, $matches)) {
                $methodName = $matches[1];
                $field = $matches[2];
            }

            $field = explode('.', $field);

            if (isset($field[1])) {
                $field = "`{$this->tablePrefix}{$field[0]}`." . (strpos($field[1], '*') === false ? "`{$field[1]}`" : $field[1]);
            } else {
                $field = strpos($field[0], '*') === false ? "`{$field[0]}`" : $field[0];
            }

            if (isset($methodName)) {
                $field = $methodName . '(' . $field . ')';
            }

            if (isset($fullField[1])) {
                return $field . ' as `' . $fullField[1] . '`';
            }
            return $field;
        }
    }

    /**
     * 特殊字符进行转义，防止sql的注入
     * @param string $value
     * @return bool|mixed|string
     */
    protected function escape($value = '')
    {
        if (is_array($value)) {
            if (Arr::isAssoc($value)) {
                $val = '';
                foreach ($value as $k => $v) {
                    $val .= $this->escapeId($k) . '=' . $this->escape($v) . ',';
                }
                return trim($val, ',');
            } else {
                return '(' . array_reduce($value, function ($result, $item) {
                        return $result . ($result ? ',' : '') . $this->escape($item);
                    }) . ')';
            }

        } elseif (is_string($value)) {
            return '"' . trim(addslashes($value)) . '"';
        } elseif (is_numeric($value)) {
            return $value;
        } elseif ($value instanceof Closure) {
            return $value();
        }

        return false;
    }


    /**
     * 利用__call方法实现一些特殊的Model方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return $this|mixed|null
     * @throws Exception
     */
    public function __call($method = '', $args = [])
    {
        $method = strtolower($method);
        if ($method == 'count') {
            return $this->first($method . '(*) as c')->c;
        } elseif (in_array($method, array('sum', 'min', 'max', 'avg'))) {
            return $this->first($method . '(' . $args[0] . ') as c')->c;
        }

        return $this;
    }

    /**
     * @param string $where
     * @param string $logical
     * @return $this
     */
    public function where($where = '', $logical = "and")
    {
        return $this->condition($where, $logical);
    }

    /**
     * @param string $where
     * @param string $logical
     * @return $this
     */
    public function having($where = '', $logical = "and")
    {
        return $this->condition($where, $logical, 'having');
    }

    /**
     * @param string $where id=1 || ['id'=>1,'or id'=>2,'age >'=>15,'or id in'=>[1,2,3,4,5]]
     * @param string $logical and | or
     * @param string $type where | having
     * @return $this
     */
    protected function condition($where = '', $logical = "and", $type = "where")
    {
        if (empty($where)) return $this;

        if (empty($this->methods[$type])) {
            $this->methods[$type] = " {$type} ";
        } else {
            $this->methods[$type] .= " {$logical} ";
        }

        $this->methods[$type] .= '(';

        if (is_string($where)) {
            $this->methods[$type] .= '(' . $where . ')';;
            return $this;
        }

        $count = 0;
        foreach ((array)$where as $k => $v) {
            if ($count != 0) {
                $this->methods[$type] .= " {$logical} ";
            }

            $filed = preg_split('/\s+/', $k);

            if (count($filed) == 3) {
                $logical = $filed[0];
                $operator = $filed[1];
                $filed = $this->escapeId($filed[2]);
            } elseif (preg_match('/(and|or)\s+/i', $k, $matches)) {
                $logical = $matches[0];
                $operator = '=';
                $filed = $this->escapeId($filed[1]);
            } else {
                $operator = isset($filed[1]) ? $filed[1] : '=';
                $filed = $this->escapeId($filed[0]);
            }


            if ($v instanceof Closure) {
                $this->methods[$type] .= $filed . ' ' . $operator . ' (' . call_user_func($v, new static($this->model)) . ')';
                $this->parameters = array_merge($this->parameters, $this->model->getParameters());
            } elseif (is_null($v) || (is_string($v) && strripos($v, 'null') !== false)) {
                $this->methods[$type] .= $filed . ' is null';
            } else {
                $operator = strtoupper($operator);
//                    if (is_string($v)) {
//                        $v = explode(',', $v);
//                    }

                if (strpos($type, 'on') !== false) {
                    $val = $this->escapeId($v);
                } else if (strpos($operator, 'BETWEEN') !== false) {
                    array_push($this->parameters, $v[0], $v[1]);
                    $val = '? and  ?';
                } else if (strpos($operator, 'IN') !== false) {
                    $this->parameters = array_merge($this->parameters, (array)$v);
                    $val = '(' . substr(str_repeat(',?', count($v)), 1) . ')';
                } else {
                    array_push($this->parameters, $v);
                    $val = '?';
                }

                $this->methods[$type] .= $filed . ' ' . $operator . ' ' . $val;
            }

            $count++;
        }

        $this->methods[$type] .= ')';

        return $this;

    }


    /**
     * @param string $sql
     * @param string $method
     * @return $this
     */
    protected function orderOrGroup($sql = '', $method = 'order')
    {
        $args = array_filter(explode(',', trim($sql)));

        foreach ($args as $v) {
            if ($this->methods[$method] != "") {
                $this->methods[$method] .= ',';
            }

            $order = preg_split("/[\s,]+/", trim($v));
            $dot = explode('.', $order[0]);
            $this->methods[$method] .= '`' . $dot[0] . '`';

            if (isset($dot[1])) {
                $this->methods[$method] .= ".`$dot[1]`";
            }

            if (isset($order[1])) {
                $this->methods[$method] .= ' ' . $order[1];
            }

        }
        return $this;
    }

    /**
     * @param string $sql “id desc,createTime desc”=>“order by id desc,createTime desc”
     * @return string
     */
    public function order($sql = '')
    {
        return $this->orderOrGroup($sql, 'order');
    }

    /**
     * @param string $sql “name,price”=>“group by name,price”
     * @return string
     */
    public function group($sql = '')
    {
        return $this->orderOrGroup($sql, 'group');
    }


    /**
     * @param array $field
     * @return $this
     */
    public final function select($field = [])
    {
        if (func_num_args() > 1) {
            $field = func_get_args();
        }

        if (is_array($field)) {
            $fieldArr = $field;
        } else {
            $fieldArr = explode(',', $field);
        }

        $field = $this->escapeId($fieldArr);

        $this->methods['field'] .= ',' . $field;
        $this->methods['field'] = trim($this->methods['field'], ',');


        return $this;
    }


    /**
     * @param array $field
     * @param string $tableName
     * @param bool $auto
     * @return $this
     */
    public final function except($field = [])
    {
        $tableField = $this->tableField();

        $field = array_diff($tableField, $field);

        $this->methods['field'] .= implode(',', $field);

        return $this;
    }

    /**
     * @param string $tableName
     * @param bool $auto 是否自动添加前缀
     * @return $this
     */
    public final function table($tableName = "", $auto = true)
    {
        $this->setTempTableName($tableName, $auto);
        return $this;
    }


    protected final function setTempTableName($tableName = "", $auto = true)
    {
        if (empty($tableName)) {
            $tableName = $this->tableName;
        } elseif ($tableName instanceof \Closure) {
            return $this->tempTableName = ' (' . call_user_func($tableName, new Model($this->tableName)) . ') as tmp' . uniqid();
        }

        if ($auto && !empty($this->tablePrefix)) {
            $tableName = strpos($tableName, $this->tablePrefix) === false
                ? $this->tablePrefix . $tableName
                : $tableName;
        }

        $tableName = preg_split('/\s+|as/', $tableName);
        if (isset($tableName[1])) {
            $this->tempTableName = "`{$tableName[0]}` `{$this->tablePrefix}{$tableName[1]}`";
        } else {
            $this->tempTableName = "`{$tableName[0]}`";
        }

        return $this->tempTableName;
    }

    protected final function getTempTableName()
    {
        if (empty($this->tempTableName)) {
            $this->setTempTableName($this->tableName);
        }
        return $this->tempTableName;
    }

    /**
     * 获得表名
     * @return null|string
     */
    public function getTable()
    {
        return $this->tableName;
    }

    /**
     * 组合成SQL
     * @return string
     */
    public function toSql()
    {
        $field = $this->methods['field'] ? $this->methods['field'] : '*';
        $order = $this->methods["order"] != "" ? " ORDER BY {$this->methods["order"]} " : "";
        $group = $this->methods["group"] != "" ? " GROUP BY {$this->methods["group"]}" : "";
        $having = $this->methods["having"] != "" ? "{$this->methods["having"]}" : "";

        $this->statement = "SELECT $field FROM  {$this->getTempTableName()} ";

        foreach ((array)$this->methods['join'] as $v) {
            $this->statement .= " " . $v . " ";
        }

        $this->statement .= "{$this->methods['where']}{$group}{$having}{$order}{$this->methods['limit']}";

        $this->model->setParameters($this->parameters);
        $this->cleanLastSql();
        return $this->statement;
    }


    /**
     * @param string $sql
     * @param array $parameters
     * @return array|bool|mixed
     */
    protected final function cache($sql = '', $parameters = [])
    {
        $dbCacheKey = md5($sql . json_encode($parameters));
        $cache = Cache::getInstance();

        if ($this->tmpOpenCache && !$cache->isExpired($dbCacheKey)) {
            $this->tmpOpenCache = $this->openCache;
            return $cache->get($dbCacheKey);
        }

        $this->lastPdo = $this->getSlavePdo();
        $PDOStatement = $this->lastPdo->prepare($sql);
        $PDOStatement->execute($parameters);
        $result = $PDOStatement->fetchAll(PDO::FETCH_ASSOC);

        if ($this->openCache) {
            $cache->set($dbCacheKey, $result);
        }

        return $result;
    }


    /**
     * @param string $sql
     * @param array $parameters
     * @return array|bool|int
     * @throws Exception
     */
    public function query($sql = '', $parameters = [])
    {
        $start = microtime(true);
        $this->statement = $sql;
        $this->queries[] = $sql;
        $this->model->setSql($sql);

        $sqlKey = strtoupper(substr($sql, 0, strpos($sql, ' ')));

        try {
            if ($sqlKey === 'SELECT') {
                $result = $this->cache($sql, $parameters);
            } else {
                $this->lastPdo = $this->getMasterPdo();
                $PDOStatement = $this->lastPdo->prepare($sql);
                $result = $PDOStatement->execute($parameters);
                if (in_array($sql, ['DELETE', 'INSERT', 'UPDATE'])) {
                    $result = $PDOStatement->rowCount();
                }
            }
            return $result;
        } catch (PDOException $e) {
            echo '<pre>';
            $errorMsg = $e->getMessage();
            $errorSql = 'ERROR SQL: ' . $sql . PHP_EOL . $errorMsg;

            throw  new Exception($errorSql, $e->getCode());

        } finally {
            $this->parameters = [];
            $time = round((microtime(true) - $start) * 1000, 2);
            Event::fire('illuminate.query', [$sql, $parameters, $time]);
        }
    }


    /**
     * 触发监听事件
     *
     * @param $bindings
     */
    protected function fire($bindings)
    {
        foreach ((array)$this->events as $k => $v) {
            call_user_func_array($v, $bindings);
        }
    }

    /**
     * 注册监听事件
     *
     * @param Closure $callback
     */
    public function listen(Closure $callback)
    {
        Event::listen('illuminate.query', $callback);
    }

    /**
     * @param $data
     * @return Model
     */
    protected function newModel($data)
    {
        return new $this->model($data);
    }


    /**
     * @param string $field
     * @return Collection
     * @throws Exception
     */
    public final function get($field = '*')
    {
        $this->newModel([]);
        $models = [];
        $this->statement = $this->select($field)->toSql();

        foreach ($this->query($this->statement, $this->parameters) as $k => $v) {
            $models[$k] = $this->newModel($v);
        }

        return $this->model->newCollection($models);
    }


    /**
     * @param string $field
     * @return Model
     * @throws Exception
     */
    public final function first($field = '*')
    {
        $this->statement = $this->select($field)->limit(1)->toSql();

        $data = $this->query($this->statement, $this->parameters);
        $data = isset($data[0]) ? $data[0] : [];
        $model = $this->newModel($data);

        return $model;
    }


    /**
     * 清除上次组合的SQL记录，避免重复组合
     */
    protected final function cleanLastSql()
    {
        $this->methods = [
            'field' => '',
            'where' => '',
            'order' => '',
            'limit' => '',
            'group' => '',
            'having' => '',
            'join' => [],
            'on' => '',
        ];
        $this->tempTableName = null;
    }

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return $this
     */
    public final function limit($offset, $length = null)
    {
        if (is_null($length) && strpos($offset, ',')) {
            list($offset, $length) = explode(',', $offset);
        }
        $this->methods['limit'] = " LIMIT " . (int)$offset . ($length ? ',' . (int)$length : '');
        return $this;
    }

    /**
     * 指定分页
     * @access public
     * @param mixed $page 页数
     * @param mixed $listRows 每页数量
     * @return $this
     */
    public final function page($page, $listRows = null)
    {
        if (is_null($listRows) && strpos($page, ',')) {
            list($page, $listRows) = explode(',', $page);
        }
        $this->methods['limit'] = " LIMIT " . ((int)$page - 1) * (int)$listRows . ',' . (int)$listRows;
        return $this;
    }

    /**
     * @param string $table 表名称
     * @param string $cond 连接条件
     * @param string $type 连接类型
     * @param bool $auto 是否自动添加表前缀
     * @return $this
     */
    public final function join($table = '', $cond = [], $type = '', $auto = true)
    {
        $table = $auto ? $this->tablePrefix . $table : $table;
        $table = preg_split('/\s+as\s+|\s+/', $table);
        $tableAlias = isset($table[1]) ? $this->escapeId($this->tablePrefix . $table[1]) : '';
        $table = $this->escapeId($table[0]);

        if ($type != '') {
            $type = strtoupper(trim($type));

            if (!in_array($type, array(
                'LEFT',
                'RIGHT',
                'OUTER',
                'INNER',
                'LEFT OUTER',
                'RIGHT OUTER'
            ))
            ) {
                $type = '';
            } else {
                $type .= ' ';
            }
        }
        $this->methods['on'] = '';
        $this->condition($cond, 'and', 'on');

        $this->methods['join'][] = $type . 'JOIN ' . $table . ' ' . $tableAlias . $this->methods['on'];

        return $this;
    }


    /**
     * 数据库删除
     * @param string|array $where 数据库表名
     * @return array|bool|int 返还受影响行数
     * @throws Exception
     */
    public final function delete($where = "")
    {
        if (!empty($where)) {
            $this->where($where);
        }

        $where = $this->methods['where'];
        $limit = $this->methods['limit'];

        $this->statement = "DELETE FROM {$this->getTempTableName()} {$where} {$limit}";

        return $this->query($this->statement);

    }


    /**
     *  添加数据 如果主键冲突 则修改
     * @param array $data
     * @return array|bool|int
     * @throws Exception
     */
    public function duplicateKey(array $data)
    {
        $data = $this->setDataPreProcessFill($data);

        $voluation = '';
        foreach ($data as $k => $v) {
            $voluation .= "`$k`=";
            if ($v instanceof \Closure) {
                $voluation .= $v() . ',';
            } else {
                $voluation .= "?,";
                array_unshift($this->parameters, $v);
            }
        }

        $voluation = trim($voluation, ',');
        $sql = 'INSERT  INTO ' . $this->getTempTableName() . ' set ' . $voluation . ' on duplicate key update ' . $voluation;
        return $this->query($sql, $this->parameters);
    }

    /**
     * 添加单条数据
     * @param array $data 添加的数据
     * @param string $tableName 数据库表名
     * @param bool $auto 是否自动添加表前缀
     * @param string $act
     * @return int 成功返回最后一次插入的id
     */
    public final function insert($data = [])
    {
        if (!$data) {
            return false;
        }

        $act = func_get_arg(1) !== 'REPLACE' ? 'INSERT' : 'REPLACE';

        $this->inserts($data, $act);
        return $this->getLastId();
    }

    /**
     * 添加单条数据 如已存在则替换
     * @param array $data 添加的数据
     * @param string $tableName 数据库表名
     * @param bool $auto 是否自动添加表前缀
     * @return int 受影响行数
     */
    public function replace($data = [])
    {
        return $this->insert($data, 'REPLACE');
    }


    protected function insertData($data = [])
    {
        $data = $this->setDataPreProcessFill($data);
        return '(' . trim(array_reduce($data, function ($res, $item) {
                if ($item instanceof \Closure) {
                    return $res . ',' . $item();
                } else {
                    array_push($this->parameters, $item);
                    return $res . ',?';
                }

            }), ',') . ") ";
    }


    /**
     * 预处理，添加多条数据
     * @param array $data 添加的数据 单条：[filed=>val]| 多条：[[filed=>val],[filed=>val]]
     * @return array|bool|int 受影响行数
     * @throws Exception
     */
    public function inserts($data = [])
    {
        if (isset($data[0]) && is_array($data[0])) {
            $fields = array_keys($data[0]);
            $data = trim(array_reduce($data, function ($res, $item) {
                return $res . ',' . $this->insertData($item);
            }), ',');
        } else {
            $fields = array_keys($data);
            $data = $this->insertData($data);
        }

        $fields = $this->escapeId($fields);
        $act = func_get_arg(1) !== 'REPLACE' ? 'INSERT' : 'REPLACE';
        $sql = "{$act}  INTO " . $this->getTempTableName() . "(" . $fields . ")  VALUES" . $data;

        return $this->query($sql, $this->parameters);
    }

    /**
     * 预处理添加多条数据 如已存在则替换
     * @param array $filed 字段
     * @param array $data 添加的数据
     * @return int 受影响行数
     * @throws Exception
     */
    public function replaces($data = [])
    {
        return $this->inserts($data, 'REPLACE');
    }

    /**
     * 获取最后一次插入的自增值
     * @return bool|int 成功返回最后一次插入的id，失败返回false
     */
    public final function getLastId()
    {
        return $this->lastPdo->lastInsertId();
    }

    /**
     * 数据入库预处理修改
     * @param $filed
     * @param $data
     */
    protected function setDataPreProcessFill($data)
    {
        if (!$this->exists) {
            return $data;
        }
        $setPreProcessCache = $this->model->getMutatedAttributes('set');
        foreach ($setPreProcessCache as $k => $v) {
            if ($filed = Arr::arrayISearch($k, array_keys($data))) {
                $data[$filed] = $this->model->{$v}($data[$filed]);
            }
        }
        return $data;
    }


    /**
     * 自动获取表结构
     * @param string $tableName 数据库表名
     * @param bool $auto 是否自动添加表前缀
     * @return array|bool
     * @throws Exception
     */
    public final function tableField()
    {
        if (isset(static::$tableFields[$this->tempTableName])) {
            return static::$tableFields[$this->tempTableName];
        }
        $table = $this->getTempTableName();
        $sql = 'desc ' . $table;
        $result = $this->query($sql)->result();
        $this->tempTableName = $table;

        foreach ($result as $row) {

            if ($row->Key == "PRI") {
                $fields["pri"] = $row->Field;
            } elseif ($row->Extra == "auto_increment") {
                $fields["auto"] = $row["Field"];
            } else {
                $fields[] = $row->Field;
            }

        }
        //如果表中没有主键，则将第一列当作主键
        if (isset($fields)) {
            if (!array_key_exists("pri", $fields)) {
                $fields["pri"] = array_shift($fields);
            }
            static::$tableFields[$this->tableName] = $fields;

            return static::$tableFields[$this->tableName];
        }

        return false;
    }


    /**
     * @param array $data 更改的数据
     * @param string $where 更改条件
     * @return int 返回受影响行数
     * @throws Exception
     */
    public final function update($data = [], $where = "")
    {
        if (!empty($where)) {
            $this->where($where);
        }

        $where = $this->methods['where'];
        $limit = $this->methods['limit'];

        $data = $this->setDataPreProcessFill($data);

        $voluation = '';
        foreach ($data as $k => $v) {
            $voluation .= "`$k`=";
            if ($v instanceof \Closure) {
                $voluation .= $v() . ',';
            } else {
                $voluation .= "?,";
                array_unshift($this->parameters, $v);
            }
        }

        $sql = 'UPDATE ' . $this->getTempTableName() . ' SET ' . trim($voluation, ',') . ' ' . $where . ' ' . $limit;

        return $this->query($sql, $this->parameters);

    }


    /**
     * 所有sql语句
     * @return array
     */
    public final function history()
    {
        return $this->queries;
    }


    /**
     * 最后一条sql语句
     * @return string
     */
    public final function lastQuery()
    {
        return end($this->queries);
    }

    /**
     * 最后一条sql语句
     * @return mixed
     */
    public final function lastSql()
    {
        return end($this->queries);
    }





    /*--------------------------数据库操作功能---------------------------------*/

    /**
     * 创建数据库，并且主键是id
     * @param string $tableName 表名
     * @param string $key 主键
     * @param string $engine 引擎 默认InnoDB
     * @param bool $auto 是否自动添加表前缀
     * @throws Exception
     */
    public function createTable($tableName = '', $key = 'id', $engine = 'InnoDB', $auto = true)
    {
        $this->table($tableName, $auto);

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tempTableName} (`$key` INT NOT NULL AUTO_INCREMENT  primary key) ENGINE = {$engine};";
        $this->query($sql);

    }


    /**
     * 删除表
     * @param string $tableName 数据库表名
     * @param bool $auto 是否自动添加表前缀
     * @return mixed
     * @throws Exception
     */
    public function dropTable($tableName = '', $auto = true)
    {
        $this->table($tableName, $auto);

        $sql = " DROP TABLE IF EXISTS $this->tempTableName";
        return $this->query($sql);
    }


    /**
     * 检测表是否存在，也可以获取表中所有字段的信息(表里所有字段的信息)
     * @param string $tableName 要查询的表名
     * @param bool $auto 是否自动添加表前缀
     * @return mixed
     * @throws Exception
     */
    public function checkTable($tableName = '', $auto = true)
    {
        $this->table($tableName, $auto);
        $sql = "desc $this->tempTableName";
        return $this->query($sql);
    }


    /**
     * 检测字段是否存在，也可以获取字段信息(只能是一个字段)
     * @param string $field 字段名
     * @param string $tableName 表名
     * @param bool $auto 是否自动添加表前缀
     * @return mixed
     * @throws Exception
     */
    public function checkField($field = '', $tableName = '', $auto = true)
    {
        $this->table($tableName, $auto);
        $sql = "desc {$this->tempTableName} $field";
        return $this->query($sql);
    }


    /**
     * @param array $info 字段信息数组
     * @param string $tableName 表名
     * @param bool $auto 是否自动添加表前缀
     * @return array 字段信息
     * @throws Exception
     */
    public function addField($info = [], $tableName = '', $auto = true)
    {
        $this->table($tableName, $auto);


        $sql = "alter table {$this->tempTableName} add ";
        if (!$field = $this->filterFieldInfo($info)) {
            return false;
        }

        $sql .= $field;
        return $this->query($sql);
    }


    /**
     * 修改字段
     * 不能修改字段名称，只能修改字段类型、默认值、注释
     * @param array $info
     * @param string $tableName
     * @param bool $auto 是否自动添加表前缀
     * @return mixed
     * @throws Exception
     */
    public function editField($info = [], $tableName = '', $auto = true)
    {

        $this->table($tableName, $auto);

        $sql = "alter table {$this->tempTableName} modify ";

        if (!$field = $this->filterFieldInfo($info)) {
            return false;
        }

        $sql .= $field;
        return $this->query($sql);
    }

    /*
     * 字段信息数组处理，供添加更新字段时候使用
     * info[name]   字段名称
     * info[type]   字段类型
     * info[length]  字段长度
     * info[isNull]  是否为空
     * info['default']   字段默认值
     * info['comment']   字段备注
     */
    private function filterFieldInfo($info = [])
    {
        if (!is_array($info)) {
            return false;
        }

        $newInfo = [];
        $newInfo['name'] = $info['name'];
        $newInfo['type'] = strtolower($info['type']);
        switch ($newInfo['type']) {
            case 'varchar':
            case 'char':
                $newInfo['length'] = !isset($info['length']) ? 255 : $info['length'];
                $newInfo['default'] = 'DEFAULT "' . (isset($info['default']) ? $info['default'] : '') . '"';
                break;
            case 'text':
            case 'longtext':
            case 'date':
            case 'datetime':
            case 'timestamp':
                $newInfo['length'] = '';
                $newInfo['default'] = '';
                break;
            case 'tinyint':
            case 'int':
            case 'bigint':
                $newInfo['length'] = !isset($info['length']) ? null : $info['length'];
                $newInfo['default'] = 'DEFAULT ' . (isset($info['default']) ? (int)$info['default'] : 0);
                break;

            case 'float':
            case 'double':
            case 'decimal':
                $newInfo['length'] = !isset($info['length']) ? '10,2' :
                    ((is_array($info['length']) && count($info['length']) == 2 && $info['length'][0] > $info['length'][1]) ? implode(',', $info['length']) : '10,2');

                $newInfo['default'] = 'DEFAULT ' . (isset($info['default']) ? (int)$info['default'] : 0);
                break;

            case 'enum':
                if (!is_array($info['value']) || empty($info['value'])) {
                    return false;
                }

                $newInfo['length'] = implode(',', array_map(function ($item) {
                    return "'{$item}'";
                }, $info['value']));
                $newInfo['default'] = 'DEFAULT "' . (isset($info['default']) ? $info['default'] : reset($info['value'])) . '"';
                break;
            default:
                return false;
        }
        $newInfo['isNull'] = !empty($info['isNull']) ? ' NULL ' : ' NOT NULL ';
        $newInfo['comment'] = isset($info['comment']) ? ' ' : ' COMMENT "' . $info['comment'] . '" ';

        $sql = $this->escapeId($newInfo['name']) . ' ' . $newInfo['type'];
        $sql .= (!empty($newInfo['length'])) ? '(' . $newInfo['length'] . ')' . " " : ' ';

        $sql .= $newInfo['isNull'];
        $sql .= $newInfo['default'];

        return $sql . $newInfo['comment'];
    }


    /**
     * 删除字段
     * 如果返回了字段信息则说明删除失败，返回false，则为删除成功
     * @param string $field
     * @param string $tableName
     * @param bool $auto
     * @return mixed
     * @throws Exception
     */
    public function dropField($field = '', $tableName = '', $auto = true)
    {
        $this->table($tableName, $auto);

        $sql = "alter table {$this->tempTableName} drop column $field";
        return $this->query($sql);

    }


    /**
     * 获取指定表中指定字段的信息(多字段)
     * @param array $field
     * @param string $tableName
     * @param bool $auto
     * @return array
     * @throws Exception
     */
    public function getFieldInfo($field = [], $tableName = '', $auto = true)
    {
        $this->table($tableName, $auto);

        $info = [];
        if (is_string($field)) {
            $field = explode(',', $field);
        }

        foreach ($field as $v) {
            $info[$v] = $this->checkField($v);
        }

        return $info;
    }

    public function __toString()
    {
        return $this->toSql();
    }

    public function __destruct()
    {
        $this->cleanLastSql();
    }

    /**
     * @return mixed|null
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param mixed|null $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }
}