<?php
/**
 * Project: YrPHP.
 * User: Kwin
 * QQ: 284843370
 * Email: kwinwong@hotmail.com
 * GitHub: https://github.com/kwinH/YrPHP
 */

namespace YrPHP;

use App;

class Event
{
    protected $listeners = [];
    protected $firing = [];
    protected $wildcards = [];
    protected $sorted = [];

    /**
     * 注册事件侦听器
     *
     * @param  string|array $events
     * @param  mixed $listener
     * @param  int $priority
     * @return void
     */
    public function listen($events, $listener)
    {
        foreach ((array)$events as $event) {
            if (strpos($events, '*') !== false) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$events][] = $this->makeListener($listener);

                unset($this->sorted[$event]);
            }
        }

    }


    /**
     * 设置通配符侦听器回调
     *
     * @param  string $event
     * @param  mixed $listener
     * @return void
     */
    protected function setupWildcardListen($event, $listener)
    {
        $this->wildcards[$event][] = $this->makeListener($listener);
    }

    /**
     * 创建事件侦听器。
     *
     * @param  mixed $listener
     * @return mixed
     */
    public function makeListener($listener)
    {
        return is_string($listener) ? $this->createClassListener($listener) : $listener;
    }

    /**
     * 使用依赖注入创建基于类的侦听器。
     *
     * @param  mixed $listener
     * @return \Closure
     */
    public function createClassListener($listener)
    {
        return function () use ($listener) {
            list($class, $method) = $this->parseClassCallable($listener);
            return App::runMethod($class, $method, $listener);
        };
    }

    /**
     * 将类监听器解析为类和方法。
     *
     * @param  string $listener
     * @return array
     */
    protected function parseClassCallable($listener)
    {
        $segments = explode('@', $listener);

        return [$segments[0], count($segments) == 2 ? $segments[1] : 'handle'];
    }

    /**
     * 判断给定事件名是否有监听器。
     *
     * @param  string $eventName
     * @return bool
     */
    public function hasListeners($eventName)
    {
        return isset($this->listeners[$eventName]) || isset($this->wildcards[$eventName]);
    }


    /**
     * 使用调度器注册事件订阅服务器。
     *
     * @param  object|string $subscriber
     * @return void
     */
    public function subscribe($subscriber)
    {
        if (is_string($subscriber)) {
            $subscriber = App::loadClass($subscriber);
        }

        $subscriber->subscribe($this);
    }

    /**
     * 当调用到一个返回不是`null`值时，不再执行其他事件
     *
     * @param  string $event
     * @param  array $payload
     * @return mixed
     */
    public function until($event, $payload = [])
    {
        return $this->fire($event, $payload, true);
    }


    /**
     * 触发事件并调用监听器。
     *
     * @param  string|object $event
     * @param  mixed $payload
     * @param  bool $halt
     * @return array|null
     */
    public function fire($event, $payload = [], $halt = false)
    {
        //当给定的“事件”实际上是一个对象时，我们将假定它是一个事件。
        if (is_object($event)) {
            list($payload, $event) = [[$event], get_class($event)];
        }

        $responses = [];

        if (!is_array($payload)) {
            $payload = [$payload];
        }

        $this->firing[] = $event;


        foreach ($this->getListeners($event) as $listener) {
            $response = call_user_func_array($listener, $payload);

            if (!is_null($response) && $halt) {
                array_pop($this->firing);

                return $response;
            }

            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        array_pop($this->firing);

        return $halt ? null : $responses;
    }


    /**
     * 获取指定事件名下的所有监听器
     *
     * @param  string $eventName
     * @return array
     */
    public function getListeners($eventName)
    {
        $wildcards = $this->getWildcardListeners($eventName);

        if (!isset($this->sorted[$eventName])) {
            $this->sortListeners($eventName);
        }

        return array_merge($this->sorted[$eventName], $wildcards);

    }

    /**
     * 在通配符列表中匹配指定的监听器
     * rr*
     * @param  string $eventName
     * @return array
     */
    public function getWildcardListeners($eventName)
    {
        $wildcards = [];

        foreach ($this->wildcards as $key => $listeners) {
            if (
            (bool)preg_match('#^' . str_replace('\*', '.*', preg_quote($key, '#')) . '\z' . '#u', $eventName)
            ) {
                array_push($wildcards, $listeners);
            }
        }

        return $wildcards;
    }

    /**
     * 按优先级排序给定事件的侦听器。
     *
     * @param  string $eventName
     * @return array
     */
    protected function sortListeners($eventName)
    {
        $this->sorted[$eventName] = [];

        // 如果监听器存在给定事件，我们将按优先级排序它们，
        // 我们可以按正确的顺序调用它们。
        if (isset($this->listeners[$eventName])) {
            krsort($this->listeners[$eventName]);
            $this->sorted[$eventName] = $this->listeners[$eventName];
        }
        return $this->sorted;
    }

    /**
     * 获取当前正在触发的事件
     *
     * @return string
     */
    public function firing()
    {
        return end($this->firing);
    }

    /**
     * 删除指定事件名中的一组监听器
     *
     * @param  string $event
     * @return void
     */
    public function forget($event)
    {
        if (strpos($event, '*') !== false) {
            unset($this->wildcards[$event]);
        } else {
            unset($this->listeners[$event], $this->sorted[$event]);
        }
    }


}