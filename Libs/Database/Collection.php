<?php
/**
 * Project: spider.
 * Author: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 */

namespace YrPHP\Database;

use ArrayAccess;
use Closure;
use IteratorAggregate;
use YrPHP\Database\Traits\ArrayAccessTrait;
use YrPHP\Database\Traits\IteratorITrait;

class Collection implements IteratorAggregate, ArrayAccess
{
    use IteratorITrait,
        ArrayAccessTrait;

    protected $attributes = [];

    public function __construct($array = [])
    {
        $this->attributes = $array;
    }

    /**
     * 取集合第一条数据
     * @return Model
     */
    public function first()
    {
        return reset($this->attributes);
    }

    /**
     * 取集合最后一条数据
     * @return Model
     */
    public function last()
    {
        return end($this->attributes);
    }


    /**
     * 将集合最后一个单元弹出（出栈）
     * @return Model
     */
    public function pop()
    {
        return array_pop($this->attributes);
    }

    /**
     * 从集合中随机取一条数据
     * @return Model
     */
    public function random()
    {
        return array_rand($this->attributes);
    }

    /**
     * 集合如果为空 则为真
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->attributes);
    }

    /**
     * 集合如果不为空 则为真
     * @return bool
     */
    public function isNotEmpty()
    {
        return !empty($this->attributes);
    }

    /**
     * 读取集合
     * @return array
     */
    public function all()
    {
        return $this->attributes;
    }

    /**
     * 集合总条数
     * @return int
     */
    public function count()
    {
        return count($this->attributes);
    }

    /**
     * 迭代集合中的内容并将其传递到回调函数中
     * @param Closure $callback
     */
    public function each(Closure $callback)
    {
        foreach ($this->attributes as $key => $item) {
            if ($callback($key, $item) === false) {
                break;
            }
        }
    }

    /**
     * 用回调函数迭代地将集合数组简化为单一的值
     *
     * @param  Closure $callback
     * @param  mixed $initial
     * @return mixed
     */
    public function reduce(Closure $callback, $initial = null)
    {
        return array_reduce($this->attributes, $callback, $initial);
    }

    /**
     * 将回调函数作用到集合数组的单元上
     * @param Closure $callback
     * @return array
     */
    public function map(Closure $callback)
    {
        return array_map($callback, $this->attributes);
    }


    /**
     * 将集合转换成 PHP 数组
     * @return array
     */
    public function toArray()
    {
        return array_map(function (Model $value) {
            return $value->toArray();
        }, $this->attributes);
    }

    /**
     * 将集合转换成 JSON 字符串
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }


}