<?php
/**
 * Created by YrPHP.
 * User: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 * GitHub:https://github.com/kwinH/YrPHP
 */

namespace YrPHP;


use App;
use response;

abstract class Controller
{
    protected static $instance;

    /**
     * 在控制器上注册的中间件
     * @var array
     */
    protected $middlewares = [];

    public function __construct()
    {
        self::$instance =& $this;

    }


    /**
     *
     * 定义一个静态全局Controller超级对象
     * 可通过引用的方式使用Controller对象
     * 返回当前实例控制器对象
     * @static
     * @return    \YrPHP\Controller
     */
    public static function &getInstance()
    {
        return self::$instance;
    }


    /**
     * 注册中间件
     *
     * @param  string $middleware
     * @param  array $options
     * @return void
     */
    public function middleware($middleware, array $options = [])
    {
        $this->middlewares[$middleware] = $options;
    }

    public function getMiddleware()
    {
        $middleware = [];
        foreach ($this->middlewares as $k => $v) {
            if (empty($v)) {
                $middleware[] = $k;
                continue;
            }
            $actName = config('actName');
            if (
                (isset($v['only']) && in_array($actName, $v['only']))
                || (isset($v['except']) && !in_array($actName, $v['except']))
            ) {
                $middleware[] = $k;
            }
        }
        return $middleware;
    }

    /**
     * 验证POST|GET提交的数据
     * @param array $rule 验证规则
     */
    public function validate($rule = [])
    {

        App::loadClass(FormRequest::class, App::loadClass('\YrPHP\Request'), $rule);
    }

    public function errorBackTo($message, $url = null)
    {
        response::errorBackTo($message, $url);
    }

    public function successBackTo($message, $url = null)
    {
        response::successBackTo($message, $url);
    }

    public function __call($method, $args)
    {
        sendHttpStatus(404);
        require config('errors_template.404');
        exit;
    }

    public function __get($name)
    {
        if (class_exists($name)) {
            return loadClass($name);
        }
        return null;
    }


}