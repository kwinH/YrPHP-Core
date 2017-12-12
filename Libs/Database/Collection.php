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

    public function toArray()
    {
        return array_map(function ($value) {
            return $value->getAttributes();
        }, $this->items);
    }

    public function toJson()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }


    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }


    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }


    public function offsetSet($offset, $value)
    {
        $this->items[$offset] = $value;
    }


    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    function __get($name)
    {
        if (isset($this->items[$name])) {
            return $this->items[$name];
        }
        return null;
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param array $items
     */
    public function setItems($items)
    {
        $this->items = $items;
    }
}