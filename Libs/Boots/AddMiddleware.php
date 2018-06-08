<?php
/**
 * Project: swoole.
 * Author: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 */

namespace YrPHP\Boots;

use YrPHP\Routing\Route;

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

    /**
     * @var Route
     */
    protected $router;


    public function __construct(Route $router)
    {
        $this->router = $router;
    }

    public function init()
    {
        foreach ($this->beforeMiddleware as $middleware) {
            $this->router->beforeMiddleware($middleware);
        }

        foreach ($this->middleMiddleware as $middleware) {
            $this->router->middleMiddleware($middleware);
        }

        foreach ($this->afterMiddleware as $middleware) {
            $this->router->afterMiddleware($middleware);
        }

        foreach ($this->middlewareGroups as $key => $middleware) {
            $this->router->middlewareGroup($key, $middleware);
        }

        foreach ($this->routeMiddleware as $key => $middleware) {
            $this->router->aliasMiddleware($key, $middleware);
        }
    }
}