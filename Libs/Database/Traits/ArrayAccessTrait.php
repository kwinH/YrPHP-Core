<?php
/**
 * Project: YrPHP.
 * Author: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 */

namespace YrPHP\Database\Traits;

trait ArrayAccessTrait
{

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }


    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->attributes[$offset];
    }


    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }


    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

}