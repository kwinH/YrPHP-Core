<?php
/**
 * Created by YrPHP.
 * User: Kwin
 * QQ: 284843370
 * Email: kwinwong@hotmail.com
 * GitHub: https://github.com/kwinH/YrPHP
 */

namespace YrPHP\Routing;

use Closure;

class Route
{

    protected $uri;
    protected $method;
    protected $paramsName = [];
    protected $params = [];
    protected $action;
    protected $regex;
    protected $router;

    protected $ctlPath;
//    public function __construct(array $route)
//    {
//
//        $this->uri = $route['uri'];
//        $this->method = $route['method'];
//        $this->params = $route['params'];
//        $this->paramsName = $route['paramsName'];
//        $this->action = $route['action'];
//        $this->regex = $route['regex'];
//
//    }

    public function __construct(Router $router)
    {
        $this->router = $router;

    }

    public function add($methods = [], $uri = '', $action = '')
    {
        $this->method = $methods;
        if ($action instanceof Closure || is_string($action)) {
            $action = ['uses' => $action];
        }

        $action['middleware'] = isset($action['middleware']) ? (array)$action['middleware'] : [];

        if (isset($action['as'])) {
            $action['as'] = $this->router->getGroupStack('as') . $action['as'];
        }

        $action['view'] = $this->router->getGroupStack('view');
        $action['namespace'] = $this->router->getGroupStack('namespace');
        $action['middleware'] = array_merge($this->router->getGroupStack('middleware') ?: [], $action['middleware']);

        $this->setAction($action);

        $uri = $this->router->getGroupStack('prefix') . (empty($uri) ? '' : '/' . trim($uri, '/'));

        $this->uri = $uri;
        $this->setRegex($uri);

        return $this;
    }


    protected function setRegex($regex)
    {
        if (preg_match_all('/\/{(.*)}/U', $regex, $matches)) {
            $patterns = array_merge($this->router->getPatterns(), $this->action['pattern']);
            foreach ($matches[1] as $k => $v) {
                $pattern = isset($patterns[$v]) ? $patterns[$v] : '[^/]++';
                if (strpos($v, '=') === false) {
                    $regex = str_replace($matches[0][$k], '/(?P<' . $v . '>' . $pattern . ')', $regex);
                    $this->paramsName[] = $v;
                    $this->params[$v] = null;
                } else {
                    $param = explode('=', $v);
                    $regex = str_replace($matches[0][$k], '(?:/(?P<' . $param[0] . '>' . $pattern . '))', $regex);
                    $this->paramsName[] = $param[0];
                    $this->params[$param[0]] = $param[1];
                }
            }
        }

        $this->regex = '#^' . $regex . '$#isDu';

        return $this;
    }


    public function middleware($middleware = null)
    {
        if (is_null($middleware)) {
            return (array)($this->action['middleware'] ?? []);
        }

        if (func_num_args() > 1) {
            $middleware = func_get_args();
        }


        $this->action['middleware'] = array_merge(
            (array)($this->action['middleware'] ?? []), $middleware
        );

        return $this;
    }

    public function name($name)
    {
        if (!empty($this->action['as'])) {
            $this->router->nameListRemove($this->action['as']);
        }

        $this->action['as'] = $name;

        $this->router->nameListPush($name, $this);

        return $this;
    }

    /**
     * Get the name of the route instance.
     *
     * @return string
     */
    public function getName()
    {
        return $this->action['as'] ?? null;
    }

    /**
     * @return mixed
     */
    public function getUri()
    {
        return $this->uri;
    }


    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param mixed $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParamsName()
    {
        return $this->paramsName;
    }

    /**
     * @param mixed $paramsName
     */
    public function setParamsName($paramsName)
    {
        $this->paramsName = $paramsName;
    }

    /**
     * @return mixed
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param mixed $params
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAction($key = null)
    {
        return is_null($key) ? $this->action : ($this->action[$key] ?? null);
    }

    /**
     * @param mixed $action
     */
    public function setAction()
    {
        $field = func_get_args();
        switch (func_num_args()) {
            case 1:
                $this->action = $field[0];
                break;
            case 2:
                $this->action[$field[0]] = $field[1];
                break;
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRegex()
    {
        return $this->regex;
    }

    /**
     * @return mixed
     */
    public function getCtlPath()
    {
        return $this->ctlPath;
    }

    /**
     * @param mixed $ctlPath
     */
    public function setCtlPath($ctlPath)
    {
        $this->ctlPath = $ctlPath;
        return $this;
    }
}