<?php
/**
 * Created by YrPHP.
 * User: Kwin
 * QQ: 284843370
 * Email: kwinwong@hotmail.com
 * GitHub: https://github.com/kwinH/YrPHP
 */

namespace YrPHP;

use App;
use Closure;
use ReflectionClass;
use ReflectionMethod;
use Pipeline;

class Route
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
     * @var array
     */
    protected $currentRoute = [];

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
     * 控制器基础命名空间
     * @var string
     */
    protected $namespacePrefix = '';

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


    public function __construct()
    {
        $this->setNamespacePrefix(APP . '\\' . C('ctrBaseNamespace'));
        $this->currentMethod = $this->getmethod();
        $this->getCurrentUri();
        $this->uriAutoAddressing = C('uriAutoAddressing', false);
    }


    /**
     * 设置控制器基础命名空间
     * @param string $namespacePrefix
     */
    public function setNamespacePrefix($namespacePrefix)
    {
        $this->namespacePrefix = rtrim($namespacePrefix, '\\') . '\\';
    }


    /**
     * 获取适用的资源方法
     *
     * @param  array $defaults
     * @param  array $options
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
     * @param  string $name
     * @param  string $controller
     * @param  array $options
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
     */
    public function controller($uri = '', $controller = '', $names = array())
    {
        $uri = '/' . $uri;
        $action = $this->namespacePrefix;
        if (isset($this->groupStack['namespace'])) {
            $action .= $this->groupStack['namespace'];
        }
        $action .= $controller;

        $reflection = new ReflectionClass($action);

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $v) {

            if ($v->class !== $action) {
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
     * 添加路由
     * @param array $methods
     * @param string $uri
     * @param array|string|Closure $action
     * @return $this
     */
    private function addRoute($methods = [], $uri = '', $action = '')
    {
        if ($action instanceof Closure || is_string($action)) {
            $action = ['uses' => $action];
        }

        $action['middleware'] = isset($action['middleware']) ? (array)$action['middleware'] : [];

        if (!empty($this->groupStack)) {
            if (!empty($this->groupStack['prefix'])) {
                $uri = $this->groupStack['prefix'] . ($uri == '/' || empty($uri) ? '' : '/' . trim($uri, '/'));
            }

            if (isset($action['as'])) {
                $action['as'] = $this->groupStack['as'] . $action['as'];
            }


            $action['namespace'] = $this->namespacePrefix . $this->groupStack['namespace'];
            $action['middleware'] = array_merge($this->groupStack['middleware'], $action['middleware']);
        }

        $arr = [
            'uri' => $uri,
            'method' => $methods,
            'params' => [],
            'paramsName' => [],
            'action' => $action,
            'regex' => $uri
        ];

        if (preg_match_all('/\/{(.*)}/U', $uri, $matches)) {
            $patterns = array_merge($this->patterns, $action['pattern']);
            foreach ($matches[1] as $k => $v) {
                $pattern = isset($patterns[$v]) ? $patterns[$v] : '[^/]*';
                if (strpos($v, '=') === false) {
                    $arr['regex'] = str_replace($matches[0][$k], '/(?P<' . $v . '>' . $pattern . ')', $arr['regex']);
                    $arr['paramsName'][] = $v;
                    $arr['params'][$v] = null;
                } else {
                    $param = explode('=', $v);
                    $arr['regex'] = str_replace($matches[0][$k], '(?:/(?P<' . $param[0] . '>' . $pattern . '))', $arr['regex']);
                    $arr['paramsName'][] = $param[0];
                    $arr['params'][$param[0]] = $param[1];
                }
            }
        }
        $arr['regex'] = '#' . $arr['regex'] . '?$#is';

        $this->allRoutes[$uri] = $arr;

        if (is_string($action['uses'])) {
            $this->actionList[$action['uses']] = $arr;
        }

        if (isset($action['as'])) {
            $this->nameList[$action['as']] = $arr;
        }

        foreach ($methods as $method) {
            $this->routes[$method][$uri] = $arr;
        }
        return $this;
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
     * @param  array $patterns
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
     * @param  string $key
     * @param  string $pattern
     * @return void
     */
    public function pattern($key, $pattern)
    {
        $this->patterns[$key] = $pattern;
    }

    /**
     * 查找当前路由
     * @return array
     */
    protected function findRoute()
    {
        if (isset($this->routes[$this->currentMethod]) && is_array($this->routes[$this->currentMethod])) {
            foreach ($this->routes[$this->currentMethod] as $k => $v) {
                if (preg_match($v['regex'], $this->currentUri, $matches)) {
                    $this->currentRoute = $v;

                    $this->currentRoute['params'] = array_merge($v['params'], array_intersect_key($matches, $v['params']));

                    return $this->currentRoute;
                }
            }
        }
        if ($this->uriAutoAddressing) {
            $this->uriMapping();
        }

        return $this->currentRoute;
    }

    /**
     * URL自动映射到路由
     */
    private function uriMapping()
    {
        $namespace = '';

        $ctlPath = APP_PATH . Config::get('ctrBaseNamespace') . '/';
        //默认方法
        $defaultAct = Config::get('defaultAct');

        $uri = array_filter(explode('/', $this->currentUri));

        foreach ($uri as $k => $v) {
            $v = ucfirst(strtolower($v));
            if (is_dir($ctlPath . $v)) {
                $ctlPath .= empty($v) ? '' : $v . '/';

                $namespace .= $v . '\\';
                unset($uri[$k]);
            } else {
                $this->ctlName = ucfirst(strtolower($v));
                $this->actName = empty($uri[$k + 1]) ? $defaultAct : strtolower($uri[$k + 1]);
                unset($uri[$k], $uri[$k + 1]);
                break;
            }
        }

        if (empty($this->ctlName)) {
            //默认控制器文件
            $this->ctlName = Config::get('defaultCtl');
            $this->actName = $defaultAct;
        }

        $this->currentRoute = [
            'uri' => $this->currentUri,
            'method' => [$this->currentMethod],
            'params' => array_values($uri),
            'paramsName' => [],
            'action' => [
                'uses' => $namespace . $this->ctlName . '@' . $this->actName,
                'middleware' => [],
            ]
        ];

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

        $urlSuffix = C('urlSuffix');
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
    protected function getmethod()
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


    /**
     * 调度当前路由
     * @throws Exception
     */
    public function dispatch()
    {
        $this->findRoute();

        if (empty($this->currentRoute)) {
            throw  new Exception('Route Not Found');
        }

        $action = $this->currentRoute['action'];
        if (is_array($this->currentRoute['action'])) {
            $action = $this->currentRoute['action']['uses'];
        }

        $middlewareBefore = array_merge(Config::get('middleware.before', []), $this->currentRoute['action']['middleware']);

        Pipeline::send(App::request())
            ->through($middlewareBefore)
            ->then(function ($request) use ($action) {
                $this->before($request, $action);
            });

    }

    /**
     * 获取当前URI对应的控制器类名
     * @param $action
     * @return string
     */
    protected function getControllerName($action)
    {
        list($this->ctlName, $this->actName) = explode('@', $action);
        $controller = $this->namespacePrefix . $this->ctlName;
        $this->currentRoute['action']['uses'] = $this->namespacePrefix . $action;
        $this->currentRoute['ctlPath'] = ROOT_PATH . str_replace('\\', '/', $controller) . '.php';

        Config::set([
            'ctlPath' => $this->currentRoute['ctlPath'],
            'ctlName' => trim(strrchr($controller, '/')),
            'actName' => $this->actName,
            'nowAction' => $this->currentRoute['action']['uses'],
            'param' => $this->currentRoute['params'],
        ]);

        return $controller;
    }

    /**
     * 在实例化控制器之前调用中间件
     * @param $request
     * @param $action
     */
    protected function before($request, $action)
    {
        if ($action instanceof Closure) {
            $request->view = call_user_func_array($action, $this->currentRoute['params']);
            Pipeline::send($request)
                ->through(Config::get('middleware.after'))
                ->then(function ($request) {
                    $this->after($request);
                });
        } else {
            $controller = $this->getControllerName($action);
            $ctlObj = App::loadClass($controller);
            $middleware = array_merge(Config::get('middleware.middle', []), $ctlObj->getMiddleware());

            Pipeline::send($request)
                ->through($middleware)
                ->then(function ($request) use ($ctlObj) {
                    $this->middle($request, $ctlObj);
                });


        }

    }


    /**
     * 在实例化控制器实例化之后，未调用方法之前调用中间件
     * @param $action
     */
    protected function middle($request, $ctlObj)
    {
        $params = $this->currentRoute['params'];
        array_unshift($params, $ctlObj, $this->actName);
        $request->view = call_user_func_array('App::runMethod', $params);

        App::pipeline()
            ->send($request)
            ->through(Config::get('middleware.after'))
            ->then(function ($request) {
                $this->after($request);
            });

    }

    /**
     * 调用方法之后调用中间件
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
            $url = str_replace(['\\', '@'], '/', substr($routeName, strlen($this->namespacePrefix)));
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
        $namespace = isset($attributes['namespace']) ? $attributes['namespace'] : '';
        $middleware = isset($attributes['middleware']) ? (array)$attributes['middleware'] : [];
        if (empty($this->groupStack)) {
            $attributes['as'] = $as;
            $attributes['prefix'] = $prefix;
            $attributes['namespace'] = $namespace;
            $attributes['middleware'] = $middleware;
            $this->groupStack = $attributes;
        } else {
            $this->groupStack['as'] .= $as;
            $this->groupStack['prefix'] .= $prefix;
            $this->groupStack['namespace'] .= '\\' . $namespace;
            $this->groupStack['middleware'] = array_merge($this->groupStack['middleware'], $middleware);
        }

        call_user_func($callback, $this);

        array_pop($this->groupStack);
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
     * @return array
     */
    public function getAllRoutes()
    {
        return $this->allRoutes;
    }

    /**
     * @return array
     */
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

    /**
     * @return string
     */
    public function getCurrentMethod()
    {
        return $this->currentMethod;
    }

}