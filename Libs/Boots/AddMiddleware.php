<?php
/**
 * Project: swoole.
 * Author: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 */

namespace App\Boots;

use YrPHP\Route;

class AddMiddleware
{
    /**
     * 在实例化控制器之前全局中间件
     *
     * @var array
     */
    protected $beforeMiddleware = [];


    /**
     * 在实例化控制器实例化之后，未调用方法之前全局中间件
     *
     * @var array
     */
    protected $middleMiddleware = [];

    /**
     * 调用方法之后全局中间件
     *
     * @var array
     */
    protected $afterMiddleware = [];


    /**
     * 所有的中间件
     *
     * @var array
     */
    protected $routeMiddleware = [];

    /**
     * 所有的中间件组
     * @var array
     */
    protected $middlewareGroups = [];


    public function __construct(Route $router)
    {
        foreach ($this->beforeMiddleware as $middleware) {
            $router->beforeMiddleware($middleware);
        }

        foreach ($this->middleMiddleware as $middleware) {
            $router->middleMiddleware($middleware);
        }

        foreach ($this->afterMiddleware as $middleware) {
            $router->afterMiddleware($middleware);
        }

        foreach ($this->middlewareGroups as $key => $middleware) {
            $router->middlewareGroup($key, $middleware);
        }

        foreach ($this->routeMiddleware as $key => $middleware) {
            $router->aliasMiddleware($key, $middleware);
        }
    }

}