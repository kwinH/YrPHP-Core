<?php
/**
 * Project: YrPHP.
 * Author: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 */

namespace YrPHP\Database;

trait HasRelationships
{
    /**
     * 数据是单条还是多条 one|many
     * @var null
     */
    protected $relationType = null;

    /**
     * 自动填充的数据 如外键
     * @var array
     */
    protected $autoFill = [];

    /**
     * 定义一对一关系
     *
     * @param  string $related
     * @param  string $foreignKey
     * @param  string $localKey
     */
    protected function hasOne($related, $foreignKey = null, $localKey = null)
    {
        /**
         * @var $instance Model
         */
        $instance = new $related;

        $foreignKey = $foreignKey ? $foreignKey : $this->getTable() . '_id';
        $localKey = $localKey ? $localKey : $this->getKeyName();

        $data = [$foreignKey => $this->original[$localKey]];

        $instance->setRelationType('one')->setAutoFill($data);

        return $this->newQuery($instance)
            ->where($data);


    }

    /**
     * 定义一对多关系
     * @param $related
     * @param null $foreignKey
     * @param null $localKey
     * @return DB|array
     */
    protected function hasMany($related, $foreignKey = null, $localKey = null)
    {
        /**
         * @var $instance Model
         */
        $instance = new $related;

        $foreignKey = $foreignKey ? $foreignKey : $this->getTable() . '_id';
        $localKey = $localKey ? $localKey : $this->getKeyName();

        $data = [$foreignKey => $this->original[$localKey]];

        $instance->setRelationType('many')->setAutoFill($data);

        return $this->newQuery($instance)
            ->where($data);
    }


    /**
     * 定义一个远层一对多的关系
     *
     * @param  string $related 最终访问的模型的名称
     * @param  string $through 中间模型的名称
     * @param  string|null $firstKey 中间模型的外键
     * @param  string|null $secondKey 最终访问的模型的外键
     * @param  string|null $localKey 当前模型的主键
     * @param  string|null $secondLocalKey 最终访问的模型的主键
     * @throws \Exception
     */
    protected function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        /**
         * @var $through Model
         */
        $through = new $through;

        $firstKey = $firstKey ? $firstKey : $this->getTable() . '_id';
        $secondKey = $secondKey ? $secondKey : $through->getTable() . '_id';
        $localKey = $localKey ? $localKey : $this->getKeyName();
        $secondLocalKey = $secondLocalKey ? $secondLocalKey : $through->getKeyName();

        $range = $through->select($secondLocalKey)
            ->where([$firstKey => $this->original[$localKey]])
            ->get()
            ->map(function ($val) use ($localKey) {
                return $val->$localKey;
            });

        /**
         * @var $related Model
         */
        $related = new $related;

        return $this->newQuery($related)
            ->where([$secondKey . ' in' => $range]);
    }


    /**
     * 定义多态的一对一关系
     *
     * @param  string $related
     * @param  string $name
     * @param  string $type
     * @param  string $id
     * @param  string $localKey
     */
    protected function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        /**
         * @var $related Model
         */
        $related = new $related;

        if (is_null($name)) {
            $trace = debug_backtrace(false, 2);
            $name = parseNaming($trace[1]['function']);
        }

        $type = $name . '_' . ($type ? $type : 'type');
        $id = $name . '_' . ($id ? $id : 'id');
        $localKey = $localKey ? $localKey : $this->getKeyName();

        return $this->newQuery($related)
            ->where([
                $type => $this->getTable(),
                $id => $this->original[$localKey]
            ]);

    }


    /**
     * 定义一个多对多的关系
     * @param string $related 模型名
     * @param string $table 中间表名
     * @param string $relatedForeignKey 关联外键
     * @param string $privotForeignKey 中间表名关联外键
     * @return mixed
     */
    protected function belongsToMany($related, $table = null, $relatedForeignKey = null, $privotForeignKey = null)
    {
        /**
         * @var $related Model
         */
        $related = new $related;

        if (is_null($table)) {
            $table = [$this->getTable(), $related->getTable()];
            sort($table, SORT_STRING);
            $table = implode("_", $table);
        }

        $relatedForeignKey = $relatedForeignKey ? $relatedForeignKey : $related->getTable() . '_id';
        $privotForeignKey = $privotForeignKey ? $privotForeignKey : $this->getTable() . '_id';


        return $this->newQuery($related)
            ->join($table, [$related->getTable() . '.' . $related->getKeyName() => $table . '.' . $relatedForeignKey])
            ->where([
                $table . '.' . $privotForeignKey => $this->original[$this->getKeyName()]
            ]);

    }

    /**
     * 定义多态的多对多关系
     *
     * @param  string $related
     * @param  string $name
     * @param  string $table
     * @param  string $foreignPivotKey
     * @param  string $relatedPivotKey
     * @param  string $parentKey
     * @param  string $relatedKey
     * @param  bool $inverse
     */
    protected function morphToMany($related, $name, $table = null, $foreignPivotKey = null,
                                   $relatedPivotKey = null, $parentKey = null,
                                   $relatedKey = null, $inverse = false)
    {

    }


    /**
     * @param $autoFill
     * @return $this
     */
    protected function setAutoFill($autoFill)
    {
        $this->autoFill = $autoFill;
        return $this;
    }

    /**
     * @return array
     */
    public function getAutoFill()
    {
        return $this->autoFill;
    }

    /**
     * 设置返回数据是单条还是多条
     * @param null $relationType one|many
     */
    protected function setRelationType($relationType)
    {
        $this->relationType = $relationType;
        return $this;
    }

    /**
     * @return null
     */
    public function getRelationType()
    {
        return $this->relationType;
    }

    /**
     * 获取关联数据
     * @param $key
     * @return mixed|null
     * @throws \Exception
     */
    protected function getRelationValue($key)
    {
        if (!isset($this->relations[$key]) && method_exists($this, $key)) {
            /**
             * @var $query DB
             */
            $query = $this->$key();

            if ($query->getModel()->getRelationType() == 'one') {
                $this->relations[$key] = $query->first();
            } else {
                $this->relations[$key] = $query->get();
            }
        }

        return empty($this->relations[$key]) ? null : $this->relations[$key];

    }

}