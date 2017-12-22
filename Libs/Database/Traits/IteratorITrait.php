<?php
/**
 * Project: YrPHP.
 * Author: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 */

namespace YrPHP\Database\Traits;

use ArrayIterator;

trait IteratorITrait
{

    /**
     * @return ArrayIterator|\Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->attributes);
    }
}