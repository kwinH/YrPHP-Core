<?php
/**
 * Created by YrPHP.
 * User: Kwin
 * QQ: 284843370
 * Email: kwinwong@hotmail.com
 * GitHub: https://github.com/kwinH/YrPHP
 */

namespace YrPHP\Routing;

use App;
use Closure;
use Exception;
use Pipeline;
use ReflectionClass;
use ReflectionMethod;
use YrPHP\Config;
use YrPHP\Session;


class Router
{
    /**
     * 在所有路由上应用的正则匹配
     *
     * @var array
     */
    protected $patterns = [];

    /**
     * 所有路由数组
     *
     * @var array
     */
    protected $allRoutes = [];

    /**
     * 所有路由数组，按方法分类
     *
     * @var array
     */
    protected $routes = [];

    /**
     * 通过控制器动作的路径查找表
     *
     * @var array
     */
    protected $actionList = [];

    /**
     * 按名称查找路线表
     *
     * @var array
     */
    protected $nameList = [];

    /**
     * 当前路由
     * @var Route
     */
    protected $currentRoute;

    /**
     * 当前URI
     * @var string
     */
    protected $currentUri = '';

    /**
     * 当前 HTTP 请求方法
     * @var string
     */
    protected $currentMethod = '';

    /**
     * 当前控制器名
     * @var string
     */

    protected $ctlName = '';

    /**
     * 当前方法名
     * @var string
     */
    protected $actName = '';

    /**
     * 路由组属性堆栈
     *
     * @var array
     */
    protected $groupStack = [];

    /**
     * 资源控制器的默认操作
     *
     * @var array
     */
    protected $resourceDefaults = [
        'index' => ['methods' => ['GET'], 'uri' => '/$name'],
        'create' => ['methods' => ['GET'], 'uri' => '/$name/create'],
        'save' => ['methods' => ['POST'], 'uri' => '/$name'],
        'show' => ['methods' => ['GET'], 'uri' => '/$name/{$name}'],
        'update' => ['methods' => ['PUT', 'PATCH'], 'uri' => '/$name/{$name}'],
        'delete' => ['methods' => ['DELETE'], 'uri' => '/$name/{$name}']
    ];

    /**
     * 路由器支持的所有动作
     *
     * @var array
     */
    protected $verbs = [
        'any', 'get', 'post', 'put', 'patch', 'delete'
    ];

    /**
     * uri地址是否自动定位到控制器
     * @var bool
     */
    protected $uriAutoAddressing = false;


    /**
     * 全局中间件
     *
     * @var array
     */
    protected $middleware = [];


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

    protected static $routeFiles = [];

    public function __construct()
    {
        $this->currentMethod = $this->getmethod();
        $this->getCurrentUri();
        $this->uriAutoAddressing = Config::get('uriAutoAddressing', false);
    }


    /**
     * 获取适用的资源方法
     *
     * @param array $defaults
     * @param array $options
     * @return array
     */
    protected function getResourceMethods(array $defaults, array $options)
    {
        if (isset($options['only'])) {
            return array_intersect_key($defaults, array_flip((array)$options['only']));
        } elseif (isset($options['except'])) {
            return array_diff_key($defaults, array_flip((array)$options['except']));
        }

        return $defaults;
    }

    /**
     * 将资源路由到控制器
     *
     * @param string $name
     * @param string $controller
     * @param array $options
     * @return void
     */
    public function resource($name = '', $controller = '', array $options = [])
    {
        $defaults = $this->resourceDefaults;
        foreach ($this->getResourceMethods($defaults, $options) as $m => $v) {
            $methods = $v['methods'];
            $uri = '';
            foreach (explode('.', $name) as $vv) {
                $uri .= str_replace('$name', $vv, $v['uri']);
            }

            $action = [
                'uses' => $controller . '@' . $m,
                'as' => isset($options['names'][$m]) ? $options['names'][$m] : $name . '.' . $m,
            ];
            $this->addRoute($methods, $uri, $action);
        }
    }

    /**
     * 隐式控制器
     * @param string $uri
     * @param string $controller 控制器类名
     * @param array $names 设置别名
     * @throws \ReflectionException
     */
    public function controller($uri = '', $controller = '', $names = array())
    {
        $uri = '/' . $uri;
        $namespace = $this->groupStack['namespace'] ?? '';

        $controllerClass = ($namespace ? rtrim($namespace, '\\') : '') . '\\' . $controller;

        $reflection = new ReflectionClass($controllerClass);

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $v) {

            if ($v->class !== $controllerClass) {
                break;
            }

            $methodName = $v->name;
            foreach ($this->verbs as $m) {
                if (strpos($methodName, $m) === 0) {
                    $method = strtolower(substr($methodName, strlen($m)));
                    $uriFull = $uri . '/' . $method;
                    $hasparam = false;
                    foreach ($v->getParameters() as $param) {
                        if ($param->getClass()) continue;
                        $hasparam = true;
                        $uriFull .= '/{' . $param->getName();
                        if ($param->isDefaultValueAvailable()) {
                            $uriFull .= '=' . $param->getDefaultValue();
                        }

                        $uriFull .= '}';
                    }

                    $action = ['uses' => $controller . '@' . $methodName];

                    if (isset($names[$methodName])) {
                        $action['as'] = $names[$methodName];
                    }

                    $this->{$m}($uriFull, $action);
                    if ($method == 'index' && $hasparam === false) {
                        $this->{$m}($uri, $action);
                    }
                    break;
                }

            }
        }
    }


    /**
     * @param null $key
     * @return array|mixed|string
     */
    public function getGroupStack($key = null)
    {
        return is_null($key) ? $this->groupStack :
            (isset($this->groupStack[$key]) ? $this->groupStack[$key] : '');
    }

    /**
     * @return array
     */
    public function getPatterns()
    {
        return $this->patterns;
    }

    /**
     * @param array $patterns
     */
    public function setPatterns($patterns)
    {
        $this->patterns = $patterns;
    }


    /**
     * 添加路由
     * @param array $methods
     * @param string $uri
     * @param array|string|Closure $action
     * @return Route
     */
    private function addRoute($methods = [], $uri = '', $action = '')
    {
        $route = (new Route($this))->add($methods, $uri, $action);

        $this->allRoutes[$uri] = $route;

        if (is_array($action)) {
            if (is_string($action['uses'])) {
                $this->actionList[$action['uses']] = $route;
            }

            if (isset($action['as'])) {
                $this->nameList[$action['as']] = $route;
            }
        }

        $uri = $route->getUri();
        $urlSegment = explode('/', $uri);
        $urlSegmentCount = count($urlSegment);
        $urlSegmentFirstStrLen = strlen($urlSegment[1] ?? []);

        foreach ($methods as $method) {
            $this->routes[$method][$urlSegmentCount][$urlSegmentFirstStrLen][$uri] = $route;
        }

        return $route;
    }


    public function nameListPush($name, Route $route)
    {
        $this->nameList[$name] = $route;
    }

    public function nameListRemove($name)
    {
        unset($this->nameList[$name]);
    }

    /**
     * 注册 GET 响应路由
     * @param string $uri
     * @param array|string|Closure $action
     * @return Route
     */
    public function get($uri, $action)
    {
        return $this->addRoute(['GET'], $uri, $action);
    }

    /**
     * 注册 POST 响应路由
     * @param string $uri
     * @param array|string|Closure $action
     * @return Route
     */
    public function post($uri, $action)
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    /**
     * 注册 PUT 响应路由
     * @param string $uri
     * @param array|string|Closure $action
     * @return Route
     */
    public function put($uri, $action)
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    /**
     * 注册 PATCH 响应路由
     * @param string $uri
     * @param array|string|Closure $action
     * @return Route
     */
    public function patch($uri, $action)
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    /**
     * 注册 DELETE 响应路由
     * @param string $uri
     * @param array|string|Closure $action
     * @return Route
     */
    public function delete($uri, $action)
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    /**
     * 注册可响应多个 HTTP 请求的路由
     * @param array $methods
     * @param string $uri
     * @param $action
     * @return Route
     */
    public function match($methods = [], $uri, $action)
    {
        $methods = array_intersect(
            ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
            array_map('strtoupper', (array)$methods)
        );
        return $this->addRoute($methods, $uri, $action);
    }

    public function any($uri, $action)
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        return $this->addRoute($methods, $uri, $action);
    }

    /**
     * 在所有路由上添加一组正则匹配
     *
     * @param array $patterns
     * @return void
     */
    public function patterns($patterns)
    {
        foreach ($patterns as $key => $pattern) {
            $this->pattern($key, $pattern);
        }
    }

    /**
     * 在所有路由上添加一个正则匹配
     *
     * @param string $key
     * @param string $pattern
     * @return void
     */
    public function pattern($key, $pattern)
    {
        $this->patterns[$key] = $pattern;
    }

    /**
     * 查找当前路由
     * @return Route
     */
    protected function findRoute()
    {
        if (file_exists(APP_PATH . 'Runtime/cache/routes.php')) {
            $routeCache = unserialize(file_get_contents(APP_PATH . 'Runtime/cache/routes.php'));
            $this->setRoutes($routeCache->getRoutes());
            $this->setAllRoutes($routeCache->getAllRoutes());
            $this->setNameList($routeCache->getNameList());
            $this->setActionList($routeCache->getActionList());
            unset($routeCache);
        } else {
            $this->requireRoueFiiles();
        }

        if (isset($this->routes[$this->currentMethod]) && is_array($this->routes[$this->currentMethod])) {
            $uri = $this->currentUri;
            $urlSegment = explode('/', $uri);
            $urlSegmentCount = count($urlSegment);
            $urlSegmentFirstStrLen = strlen($urlSegment[1] ?? []);

            $currentMethodRange = $this->routes[$this->currentMethod][$urlSegmentCount][$urlSegmentFirstStrLen];

            foreach ($currentMethodRange as $k => $route) {

                if (preg_match($route->getRegex(), $this->currentUri, $matches)) {
                    $this->currentRoute = $route;

                    $params = $route->getParams();
                    $this->currentRoute->setParams(array_merge($params, array_intersect_key($matches, $params)));

                    return $this->currentRoute;
                }
            }
        }

        return $this->currentRoute;
    }

    /**
     * @param array $routes
     */
    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    /**
     * @param array $allRoutes
     */
    public function setAllRoutes($allRoutes)
    {
        $this->allRoutes = $allRoutes;
    }

    /**
     * @return array
     */
    public function getActionList()
    {
        return $this->actionList;
    }

    /**
     * @param array $actionList
     */
    public function setActionList($actionList)
    {
        $this->actionList = $actionList;
    }

    /**
     * @return array
     */
    public function getNameList()
    {
        return $this->nameList;
    }

    /**
     * @param array $nameList
     */
    public function setNameList($nameList)
    {
        $this->nameList = $nameList;
    }


    /**
     * 获取当前URI
     * @return bool|string
     */
    public function getCurrentUri()
    {
        if (!empty($this->currentUri)) {
            return $this->currentUri;
        }

        if (strpos($_SERVER['REQUEST_URI'], basename($_SERVER['SCRIPT_NAME'])) !== false) {
            $requestUri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
        } elseif (($scriptLen = strlen(dirname($_SERVER['SCRIPT_NAME']))) != 1) {
            $requestUri = substr($_SERVER['REQUEST_URI'], $scriptLen);
        } else {
            $requestUri = $_SERVER['REQUEST_URI'];
        }

        $this->currentUri = explode('?', $requestUri)[0];

        $urlSuffix = Config::get('urlSuffix');
        $urlSuffixLen = -(strlen($urlSuffix));

        if (substr($this->currentUri, $urlSuffixLen) == $urlSuffix) {
            $this->currentUri = substr($this->currentUri, 0, $urlSuffixLen);
        }

        return $this->currentUri;
    }

    /**
     * 获取当前 HTTP 请求方法
     * @return string
     */
    public function getmethod()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == 'GET') {
            return $method;
        }
        if (isset($_POST['_method'])) {
            $_method = ucfirst($_POST['_method']);
            switch ($_method) {
                case 'GET':
                case 'POST':
                case 'PUT':
                case 'PATCH':
                case 'DELETE':
                    return $_method;
                default:
                    return 'POST';
            }
        }
        return 'POST';
    }


    public function requireRoueFiiles()
    {
        foreach (self::$routeFiles as $routeFile) {
            require $routeFile;
        }
        return $this;
    }

    /**
     * 调度当前路由
     */
    public function dispatch()
    {

        $this->findRoute();

        if (empty($this->currentRoute)) {
            sendHttpStatus(404);
            require config('errors_template.404');
            exit;
        }

        $action = $this->currentRoute->getAction();

        if (!empty($action['view'])) {
            Config::set('setTemplateDir', $action['view']);
        }


        Pipeline::send(App::request())
            ->through($this->middleware)
            ->then(function ($request) use ($action) {

                $this->before($request, $action);
            });


    }


    protected function gatherRouteMiddleware($middlewares)
    {
        $newMiddlewares = [];
        foreach ($middlewares as $middleware) {
            if (isset($this->middlewareGroups[$middleware])) {
                foreach ($this->middlewareGroups as $groupMiddleware) {
                    if (isset($this->routeMiddleware[$groupMiddleware])) {
                        $newMiddlewares[] = $this->routeMiddleware[$groupMiddleware];
                    } else {
                        $newMiddlewares[] = $groupMiddleware;
                    }
                }
            } else if (isset($this->routeMiddleware[$middleware])) {
                $newMiddlewares[] = $this->routeMiddleware[$middleware];
            }
        }

        return $newMiddlewares;
    }


    /**
     * 获取当前URI对应的控制器类名
     * @param $action
     * @return string
     */
    protected function getControllerName($action)
    {
        list($this->ctlName, $this->actName) = explode('@', $action['uses']);
        $controller = (empty($action['namespace']) ? '' : ($action['namespace'] . '\\')) . $this->ctlName;

        $ctlPath = ROOT_PATH . str_replace('\\', '/', $controller) . '.php';

        $this->currentRoute->setAction('uses', $action['uses']);
        $this->currentRoute->setCtlPath($ctlPath);

        return $controller;
    }

    /**
     * @param $request
     * @param $action
     */
    protected function before($request, $action)
    {
        if ($action['uses'] instanceof Closure) {
            $request->view = call_user_func_array($action['uses'], $this->currentRoute->getParams());
            $middlewares = $this->gatherRouteMiddleware($action['middleware']);

            Pipeline::send($request)
                ->through($middlewares)
                ->then(function ($request) use ($action) {
                    $this->after($request);
                });


        } else {
            $controller = $this->getControllerName($action);
            $ctlObj = App::loadClass($controller);
            $middlewares = $this->gatherRouteMiddleware(
                array_merge($action['middleware'], $ctlObj->getMiddleware())
            );

            Pipeline::send($request)
                ->through($middlewares)
                ->then(function ($request) use ($ctlObj) {
                    $this->middle($request, $ctlObj);
                });
        }

    }


    /**
     * @param $action
     */
    protected function middle($request, $ctlObj)
    {
        $params = $this->currentRoute->getParams();
        array_unshift($params, $ctlObj, $this->actName);
        $request->view = call_user_func_array('App::runMethod', $params);

        $this->after($request);
    }

    /**
     * @param $request
     */
    protected function after($request)
    {
        echo $request->view;
        Session::delete(Session::get('flash', []));
        Session::set('flash', []);
    }


    /**
     * 根据路由别名获取真正的URL
     * @param string $routeName 路由别名
     * @param array $params
     * @return string
     * @throws Exception
     */
    public function url($routeName, $params = [])
    {
        if (isset($this->nameList[$routeName])) {
            $route = $this->nameList[$routeName];
        } elseif (isset($this->actionList[$routeName])) {
            $route = $this->actionList[$routeName];
        } elseif ($this->uriAutoAddressing && strpos($routeName, '@') !== false) {
            $url = str_replace(['\\', '@'], '/', $routeName);
            if (!empty($params)) {
                $url .= '/' . implode('/', $params);
            }
            return getUrl($url);
        } else {
            throw new Exception('route not found');
        }

        foreach ($route['params'] as $key => $v) {
            $route['params'][$key] = Arr::pop($params, $key, $v);
            $route['uri'] = str_replace('{' . $key . '}', $route['params'][$key], $route['uri']);
        }

        if (!empty($params)) {
            $route['uri'] .= '?' . http_build_query($params);
        }
        return getUrl($route['uri']);


    }


    /**
     * 定义路由群组
     * @param array $attributes
     * @param Closure $callback
     */
    public function group($attributes = [], Closure $callback)
    {
        $as = isset($attributes['as']) ? $attributes['as'] : '';
        $prefix = isset($attributes['prefix']) ? '/' . trim($attributes['prefix'], '/') : '';
        $namespace = isset($attributes['namespace']) ? trim($attributes['namespace'], '\\') : '';
        $middleware = isset($attributes['middleware']) ? (array)$attributes['middleware'] : [];
        if (empty($this->groupStack)) {
            $this->groupStack = [
                'as' => $as,
                'prefix' => $prefix,
                'namespace' => $namespace,
                'middleware' => $middleware,
            ];

        } else {
            $this->groupStack['as'] .= $as;
            $this->groupStack['prefix'] .= $prefix;
            $this->groupStack['namespace'] .= empty($namespace) ? '' : ('\\' . $namespace);
            $this->groupStack['middleware'] = array_merge($this->groupStack['middleware'], $middleware);
        }

        if (isset($attributes['view'])) {
            $this->groupStack['view'] = $attributes['view'];
        }

        call_user_func($callback, $this);

        $this->groupStack = [];
    }

    /**
     * 所有路由列表
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * 所有路由数组
     * @return array
     */
    public function getAllRoutes()
    {
        return $this->allRoutes;
    }

    /**
     * 当前路由
     * @return Route
     */
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

    /**
     * 当前 HTTP 请求方法
     * @return string
     */
    public function getCurrentMethod()
    {
        return $this->currentMethod;
    }

    /**
     * 当前控制器名
     * @return string
     */
    public function getCtlName()
    {
        return $this->ctlName;
    }

    /**
     * 当前方法名
     * @return string
     */
    public function getActName()
    {
        return $this->actName;
    }

    /**
     * Register a short-hand name for a middleware.
     *
     * @param string $name
     * @param string $class
     * @return $this
     */
    public function aliasMiddleware($name, $class)
    {
        $this->routeMiddleware[$name] = $class;

        return $this;
    }

    /**
     * Register a group of middleware.
     *
     * @param string $name
     * @param array $middleware
     * @return $this
     */
    public function middlewareGroup($name, array $middleware)
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    public static function loadRoutesFrom($path)
    {
        self::$routeFiles[] = $path;
    }

    /**
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * @param array $middleware
     */
    public function middleware($middleware)
    {
        $this->middleware[] = $middleware;
    }
}