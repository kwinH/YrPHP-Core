<?php
/**
 * Created by PhpStorm.
 * User: TOYOTA
 * Date: 2016/12/12
 * Time: 15:49
 */

namespace YrPHP;


class Request
{
    protected $exceptKey = [];
    protected $onlyKey = [];
    protected static $getData = [];
    protected static $postData = [];
    //最后生成的视图
    public $view = '';

    public function __construct()
    {
        static::$getData = array_map([$this, 'filter'], $_GET);//回调过滤数据;
        static::$postData = array_map([$this, 'filter'], $_POST);
    }


    /**
     * 支持连贯查询 排除不需要的数据
     * @param $keys
     * @return $this
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $this->exceptKey = $keys;
        return $this;
    }

    /**
     * 只取需要的数据
     * @param $keys
     * @return $this
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $this->onlyKey = $keys;
        return $this;
    }


    /**
     * 获取头http客户端请求信息
     * @param null $key
     * @param null $default
     * @return array|false|mixed|null
     */
    public function header($key = null, $default = null)
    {
        $headers = [];
        if (!function_exists('getallheaders')) {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }

        } else {
            $headers = getallheaders();
        }

        if (is_null($key)) {
            return $headers;
        }

        if ($value = Arr::arrayIGet($headers, $key)) {
            return $value;
        }
        return $default;

    }

    /**
     * 获取GET请求数据
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     */
    public function get($key = null, $default = null)
    {
        $data = static::$getData;

        if (is_null($key)) {
            return $this->all($data);
        } else {
            return isset($data[$key]) ? $data[$key] : $default;
        }

    }

    /**
     * 获取POST请求数据
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     */
    public function post($key = null, $default = null)
    {
        $data = static::$postData;

        if (is_null($key)) {
            return $this->all($data);
        } else {
            return isset($data[$key]) ? $data[$key] : $default;
        }

    }

    /**
     * 获取GET和POST的数据
     * @param null $data
     * @return array|null
     */
    public function all($data = null)
    {
        $data = $data ?: array_merge(static::$getData, static::$postData);
        if ($this->onlyKey) {
            Arr::only($data, $this->onlyKey);

        } else if ($this->exceptKey) {
            Arr::except($data, $this->exceptKey);
        }

        $this->onlyKey = $this->exceptKey = [];
        return $data;
    }


    /**
     * @param array $data
     * @param string $method
     * @return array|bool
     */
    public function replace(array $data, $method = 'get')
    {
        switch (strtolower($method)) {
            case 'post':
                static::$postData = $data;
                break;
            case 'get':
                static::$getData = $data;
                break;
            default:
                return false;
        }

        return $data;
    }

    /**
     * @param array $data
     * @param string $method
     */
    public function merge(array $data, $method = 'get')
    {
        switch (strtolower($method)) {
            case 'post':
                static::$postData = array_merge(static::$postData, $data);
                break;
            case 'get':
                static::$getData = array_merge(static::$getData, $data);
                break;
        }
    }

    /**
     * @param array $keys
     * @param string $method
     * @return array|bool
     */
    public function pop(array $keys, $method = 'get')
    {
        $keys = array_flip($keys);

        switch (strtolower($method)) {
            case 'post':
                $data = static::$postData;
                break;
            case 'get':
                $data = static::$getData;
                break;
            default:
                return false;
        }

        $data = array_diff_key($data, $keys);
        $this->replace($data, $method);
        return $data;
    }

    public function filter($data = [])
    {
        $filters = config('defaultFilter');
        if (is_string($filters)) {
            $filters = explode('|', $filters);
        }

        foreach ($filters as $filter) {
            if (function_exists($filter)) {
                if (is_array($data)) {
                    array_map($filter, $data);
                } else {
                    $data = $filter($data);
                }
            }
        }

        return $data;
    }

    /**
     * 获取当前URI
     * @return bool|string
     */
    public function getPath()
    {
        return \Route::getCurrentUri();
    }

    public function is($rule)
    {
        $rule = preg_quote($rule, '/');
        $rule = str_replace('\*', '.*', $rule) . '\z';

        $path = $this->getPath();
        return (bool)preg_match('/' . $rule . '/Ui', $path);


    }


    /**
     * 在问号 ? 之后的所有字符串
     * @return mixed
     */
    public function getQuery()
    {
        return $_SERVER['QUERY_STRING'];
    }

    /**
     * 判断是不是 AJAX 请求
     * 测试请求是否包含HTTP_X_REQUESTED_WITH请求头。
     * @return    bool
     */
    public function isAjax()
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }


    /**
     *
     * @return mixed
     */
    public function method()
    {
        return \Route::getmethod();
    }

    /**
     * 判断是不是 POST 请求
     * @return    bool
     */
    public function isPost()
    {
        return ($_SERVER['REQUEST_METHOD'] == 'POST');
    }

    /**
     * 判断是否SSL协议
     * @return boolean
     */
    public function isHttps()
    {
        if (
            (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
        ) {
            return true;
        }

        return false;
    }

    /**
     * 端口号
     * @return mixed
     */
    public function port()
    {
        return $_SERVER['SERVER_PORT'];
    }

    /**
     * 主机地址
     * @return mixed
     */
    public function host()
    {
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * 当前的完整地址
     * @return string
     */
    function currentUrl()
    {
        return ($this->isHttps() ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"];
    }

    /**
     * 来源地址
     * @return mixed
     */
    public function referer()
    {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_SERVER['HTTP_HOST'];
    }


    /**
     * 返回uri 数组
     * @param null $n
     * @param null $no_result
     * @return array|mixed|null|string
     */
    public function part($n = null, $no_result = null)
    {
        $path = $this->getPath();

        $uri = explode('/', $path);
        unset($uri[0]);
        if (is_int($n)) return isset($uri[$n]) ? $uri[$n] : $no_result;
        return $uri;
    }

}