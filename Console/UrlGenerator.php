<?php

/**
 * Created by YrPHP.
 * User: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 * GitHub:https://github.com/kwinH/YrPHP
 */

namespace YrPHP\Console;

use Route;
use YrPHP\File;

class UrlGenerator
{
    protected $cacheFile;

    public function __construct()
    {
        $this->cacheFile = C('setCacheDir') . 'routes.php';
    }

    /**
     * php index.php route cache
     */
    public function cache()
    {
        require APP_PATH . 'Config/routes.php';
        $route = Route::getRoutes();
        if (file_put_contents($this->cacheFile, '<?php' . PHP_EOL . 'return ' . var_export($route, true) . ';')) {
            echo 'OK';
        } else {
            echo 'ERROR';
        }
    }

    /**
     * php index.php route clear
     */
    public function clear()
    {
        if (File::rm($this->cacheFile)) {
            echo 'OK';
        } else {
            echo 'ERROR';
        }
    }
}