<?php
/**
 * Project: spider.
 * Author: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 */

namespace YrPHP\Database;

use ArrayAccess;
use ArrayIterator;
use Closure;
use IteratorAggregate;

class Collection implements IteratorAggregate, ArrayAccess
{
    protected $items = [];

    public function __construct($array = [])
    {
        $this->items = $array;
    }

    public function first()
    {
        return reset($this->items);
    }

    public function last()
    {
        return end($this->items);
    }

    public function pop()
    {
        array_pop($this->items);
    }

    public function random()
    {
        array_rand($this->items);
    }

    public function isEmpty()
    {
        return empty($this->items);
    }

    public function isNotEmpty()
    {
        return !empty($this->items);
    }

    public function all()
    {
        return $this->items;
    }

    public function count()
    {
        return count($this->items);
    }

    public function each(Closure $callback)
    {
        foreach ($this->items as $key => $item) {
            if ($callback($key, $item) === false) {
                break;
            }
        }
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param  Closure $callback
     * @param  mixed $initial
     * @return mixed
     */
    public function reduce(Closure $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }


    /**
     * @return array
     */
    public function toArray()
    {
        return array_map(function (Model $value) {
            return $value->toArray();
        }, $this->items);
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return ArrayIterator|\Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }


    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }


    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }


    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->items[$offset] = $value;
    }


    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

}