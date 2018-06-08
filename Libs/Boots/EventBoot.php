<?php
/**
 * Project: YrPHP.
 * User: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 * GitHub:https://github.com/kwinH/YrPHP
 */

namespace YrPHP\Boots;

use YrPHP\Event;

class EventBoot
{

    /**
     * @var Event
     */
    protected $events;

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [

    ];

    protected $subscribe = [

    ];

    public function __construct(Event $events)
    {
        $this->events = $events;
    }

    public function init()
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $this->events->listen($event, $listener);
            }
        }

        foreach ($this->subscribe as $subscriber) {
            $this->events->subscribe($subscriber);
        }
    }
}