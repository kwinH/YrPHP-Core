<?php

namespace YrPHP;


class ErrorsMessage
{

    /**
     * All of the registered messages.
     *
     * @var array
     */
    protected $messages = [];

    public function __construct($messages = [])
    {
        $this->messages = $messages;
    }


    /**
     * @param $message
     * @param null $key
     */
    public function add($message, $key = null)
    {
        if (is_null($key)) {
            $this->messages[] = $message;
        } else {
            $this->messages[$key] = $message;
        }
    }

    /**
     * @return array
     */
    public function all()
    {
        $all = [];

        foreach ($this->messages as $key => $messages) {
            $all = array_merge($all, $messages);
        }

        return $all;
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->messages[$key]);
    }

    public function first($key = null)
    {
        $messages = is_null($key) ? $this->all() : $this->get($key);

        return reset($messages);
    }

    /**
     * @param array $keys
     * @return bool
     */
    public function hasAny($keys = [])
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        return Arr::get($this->messages, $key);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->messages, COUNT_RECURSIVE);
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return $this->count() === 0;
    }

    /**
     * @return bool
     */
    public function isNotEmpty()
    {
        return $this->count() !== 0;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->getMessages();
    }

    /**
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->getMessages(), $options);
    }

    protected function toSession()
    {
        session('errors', $this);
    }

    public function __destruct()
    {
        $this->toSession();
    }
}
