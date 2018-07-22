<?php
/**
 * Project: swoole.
 * Author: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 */

namespace YrPHP\Boots;

use YrPHP\Routing\Router;

class AddMiddleware
{

    /**
     * 全局中间件
     *
     * @var array
     */
    protected $middleware = [];


    /**
     * 所有的中间件别名
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
     * @var Router
     */
    protected $router;


    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function init()
    {
        foreach ($this->middleware as $middleware) {
            $this->router->middleware($middleware);
        }

        foreach ($this->middlewareGroups as $key => $middleware) {
            $this->router->middlewareGroup($key, $middleware);
        }

        foreach ($this->routeMiddleware as $key => $middleware) {
            $this->router->aliasMiddleware($key, $middleware);
        }
    }
}