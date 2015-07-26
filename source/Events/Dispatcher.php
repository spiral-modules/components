<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Events;

class Dispatcher implements DispatcherInterface
{
    /**
     * Event listeners, use addHandler, removeHandler for adding new handlers and raiseEvent to
     * perform specific events.
     *
     * @var array
     */
    protected $listeners = [];

    /**
     * All registered listeners will be performed in same order they were registered.
     *
     * @param string   $event    Event name.
     * @param callback $listener Valid callback or closure.
     * @return $this
     */
    public function addListener($event, $listener)
    {
        if (!isset($this->listeners[$event]))
        {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $listener;

        return $this;
    }

    /**
     * Will remove known callback from specified event.
     *
     * @param string   $event    Event name.
     * @param callback $listener Valid callback or closure.
     * @return $this
     */
    public function removeListener($event, $listener)
    {
        if ($this->hasListener($event, $listener))
        {
            unset($this->listeners[$event][array_search($listener, $this->listeners[$event])]);
        }

        return $this;
    }

    /**
     * Check if specified event listened by known callback.
     *
     * @param string   $event    Event name.
     * @param callback $listener Valid callback or closure.
     * @return bool
     */
    public function hasListener($event, $listener)
    {
        if (isset($this->listeners[$event]))
        {
            return in_array($listener, $this->listeners[$event]);
        }

        return false;
    }

    /**
     * Retrieve all event listeners.
     *
     * @param string $event Event name.
     * @return array
     */
    public function getListeners($event)
    {
        if (array_key_exists($event, $this->listeners))
        {
            return $this->listeners[$event];
        }

        return [];
    }

    /**
     * Fire event by name. All attached event handlers will be performed in order they were registered.
     * Method will return resulted event context which will be passed thought all event listeners.
     *
     * @param string $event   Event name.
     * @param mixed  $context Primary event content.
     * @return mixed
     */
    public function fire($event, $context = null)
    {
        if (is_object($event) && !($event instanceof EventInterface))
        {
            throw new \InvalidArgumentException(
                "Only instances of EventInterface can be used by event dispatcher."
            );
        }

        if (is_string($event))
        {
            $event = new Event($event, $context);
        }

        if (empty($this->listeners[$event->getName()]))
        {
            return $context;
        }

        foreach ($this->listeners[$event->getName()] as $listener)
        {
            call_user_func($listener, $event);
            if ($event->isStopped())
            {
                break;
            }
        }

        return $event->context();
    }
}