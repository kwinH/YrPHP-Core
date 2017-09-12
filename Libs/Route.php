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

class Route
{
    /**
     * The globally available parameter patterns.
     *
     * @var array
     */
    protected $patterns = [];
    /**
     * An array of the routes keyed by method.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * An flattened array of all of the routes.
     *
     * @var array
     */
    protected $allRoutes = [];

    /**
     * A look-up table of routes by controller action.
     *
     * @var array
     */
    protected $actionList = [];

    /**
     * A look-up table of routes by their names.
     *
     * @var array
     */
    protected $nameList = [];
    protected $currentRoute = [];
    protected $currentUri = '';
    protected $currentMethod = '';
    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = [];
    protected $namespacePrefix = '';
    /**
     * The default actions for a resourceful controller.
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
     * An array of HTTP verbs.
     *
     * @var array
     */
    protected $verbs = [
        'any', 'get', 'post', 'put', 'patch', 'delete'
    ];


    public function __construct()
    {
        $this->namespacePrefix = APP . '\\' . C('ctrBaseNamespace') . '\\';
        $this->currentMethod = $this->getmethod();
        $this->getCurrentUri();
    }


    public function setNamespacePrefix($namespacePrefix)
    {
        $this->namespacePrefix = rtrim($namespacePrefix, '\\') . '\\';
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    /**
     * Get the applicable resource methods.
     *
     * @param  array $defaults
     * @param  array $options
     * @return array
     */
    protected function getResourceMethods($defaults, $options)
    {
        if (isset($options['only'])) {
            return array_intersect_key($defaults, array_flip((array)$options['only']));
        } elseif (isset($options['except'])) {
            return array_diff_key($defaults, array_flip((array)$options['except']));
        }

        return $defaults;
    }

    /**
     * Route a resource to a controller.
     *
     * @param  string $name
     * @param  string $controller
     * @param  array $options
     * @return void
     */
    public function resource($name, $controller, array $options = [])
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

    public function controller($uri, $controller, $names = array())
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

    private function addRoute($methods, $uri, $action)
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

        $this->allRoutes = $arr;
        if (isset($action['as'])) {
            $this->nameList[$action['as']] = $arr;
        }

        foreach ($methods as $method) {
            $this->routes[$method][$uri] = $arr;
        }
        return $this;
    }

    public function get($uri, $action)
    {
        return $this->addRoute(['GET'], $uri, $action);
    }

    public function post($uri, $action)
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put($uri, $action)
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch($uri, $action)
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete($uri, $action)
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function match($methods, $uri, $action)
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
     * Set a group of global where patterns on all routes.
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
     * Set a global where pattern on all routes.
     *
     * @param  string $key
     * @param  string $pattern
     * @return void
     */
    public function pattern($key, $pattern)
    {
        $this->patterns[$key] = $pattern;
    }

    public function findRoute()
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
        if (C('uriAutoAddressing', false)) {
            $this->uriMapping();
        }

        return $this->currentRoute;
    }

    private function uriMapping()
    {
        $namespace = '';
        $actName = '';
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
                $ctlName = ucfirst(strtolower($v));
                $actName = empty($uri[$k + 1]) ? $defaultAct : strtolower($uri[$k + 1]);
                unset($uri[$k], $uri[$k + 1]);
                break;
            }
        }

        if (!isset($ctlName)) {
            //默认控制器文件
            $ctlName = Config::get('defaultCtl');
            $actName = $defaultAct;
        }

        $this->currentRoute = [
            'uri' => $this->currentUri,
            'method' => [$this->currentMethod],
            'params' => array_values($uri),
            'paramsName' => [],
            'action' => [
                'uses' => $namespace . $ctlName . '@' . $actName,
                'middleware' => [],
            ]
        ];

    }

    public function getCurrentUri()
    {
        if (!empty($this->currentUri)) {
            return $this->currentUri;
        }

        if (isset($_SERVER['REDIRECT_URL'])) {
            //$dir = preg_replace('/(.*)\/.*php$/', '$1', str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
            $dir = trim(substr($_SERVER['SCRIPT_NAME'], 0, strpos($_SERVER['SCRIPT_NAME'], basename($_SERVER['SCRIPT_FILENAME']))), '/');
            $this->currentUri = str_replace($dir, '', $_SERVER['REDIRECT_URL']);
        } else {
            $this->currentUri = $this->currentUri = explode('?', str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['REQUEST_URI']))[0];
        }


        $urlSuffix = C('urlSuffix');
        $urlSuffixLen = strlen($urlSuffix);

        if (substr($this->currentUri, -$urlSuffixLen) == $urlSuffix) {
            $this->currentUri = substr($this->currentUri, 0, -$urlSuffixLen);
        }

        return $this->currentUri;
    }

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

        App::pipeline()
            ->send(App::request())
            ->through($middlewareBefore)
            ->then(function ($request) use ($action) {
                if ($action instanceof Closure) {
                    $request->view = call_user_func_array($action, $this->currentRoute['params']);
                    App::pipeline()
                        ->send($request)
                        ->through(Config::get('middleware.after'))
                        ->then(function ($request) {
                            echo $request->view;
                            Session::delete(Session::get('flash', []));
                            Session::set('flash', []);
                        });
                } else {
                    list($ctlName, $actName) = explode('@', $action);

                    $controller = $this->namespacePrefix . $ctlName;
                    $this->currentRoute['action']['uses'] = $this->namespacePrefix . $action;
                    $this->currentRoute['ctlPath'] = ROOT_PATH . str_replace('\\', '/', $controller) . '.php';

                    Config::set([
                        'ctlPath' => $this->currentRoute['ctlPath'],
                        'ctlName' => trim(strrchr($controller, '/')),
                        'actName' => $actName,
                        'nowAction' => $this->currentRoute['action']['uses'],
                        'param' => $this->currentRoute['params'],
                    ]);

                    $ctlObj = App::loadClass($controller);
                    $middleware = array_merge(Config::get('middleware.middle', []), $ctlObj->getMiddleware());

                    App::pipeline()
                        ->send($request)
                        ->through($middleware)
                        ->then(function ($request) use ($ctlObj, $actName) {
                            $params = $this->currentRoute['params'];
                            array_unshift($params, $ctlObj, $actName);
                            $request->view = call_user_func_array('App::runMethod', $params);

                            App::pipeline()
                                ->send($request)
                                ->through(Config::get('middleware.after'))
                                ->then(function ($request) {
                                    echo $request->view;
                                    Session::delete(Session::get('flash', []));
                                    Session::set('flash', []);
                                });

                        });


                }
            });

        return;
    }


    public function url($name, $params = [])
    {
        if (isset($this->nameList[$name])) {
            $route = $this->nameList[$name];

            foreach ($route['params'] as $key => $v) {
                if (isset($params[$key])) {
                    $route['params'][$key] = $params[$key];
                }

                $route['uri'] = str_replace('{' . $key . '}', $route['params'][$key], $route['uri']);
            }

            return $route['uri'];
        }
        throw new Exception('route not found');
    }

    public function group($attributes, $callback)
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

    public function getRoutes()
    {
        return $this->routes;
    }

    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

}