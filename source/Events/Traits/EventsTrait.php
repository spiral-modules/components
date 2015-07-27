<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Events\Traits;

use Spiral\Core\ContainerInterface;
use Spiral\Events\Dispatcher;
use Spiral\Events\DispatcherInterface;
use Spiral\Events\EventsException;
use Spiral\Events\ObjectEvent;

/**
 * Class should be instance of Component or declare STATIC getContainer() method.
 */
trait EventsTrait
{
    /**
     * List of statically associated event dispatchers.
     *
     * @var DispatcherInterface[]
     */
    private static $eventDispatchers = [];

    /**
     * Global container access is required in some cases. Method should be declared statically!
     *
     * @return ContainerInterface
     */
    abstract public function getContainer();

    /**
     * Sets event dispatcher. Event dispatcher will be associated with specific component by it's class
     * name.
     *
     * @param DispatcherInterface $dispatcher
     */
    public static function setDispatcher(DispatcherInterface $dispatcher = null)
    {
        self::$eventDispatchers[static::class] = $dispatcher;
    }

    /**
     * EventDispatcher instance which is currently attached to component implementation, can be redefined
     * using setDispatcher() method. EventDispatcher instance will be created on demand and depends on
     * "events" binding in spiral core. Every new EventDispatcher will receive "name" argument which
     * is equal to getAlias() method result and declares events namespace.
     *
     * If no "events" binding presented, default dispatcher will be used (performance reasons).
     *
     * @return DispatcherInterface
     * @throws EventsException
     */
    public static function events()
    {
        if (isset(self::$eventDispatchers[static::class]))
        {
            return self::$eventDispatchers[static::class];
        }

        if (empty(self::getContainer()))
        {
            throw new EventsException("Unable to create event dispatcher, global container not set.");
        }

        return self::$eventDispatchers[static::class] = self::getContainer()->get(
            DispatcherInterface::class
        );
    }

    /**
     * Add event listener to specified event.
     *
     * @param string   $event    Event name.
     * @param callable $listener Callback.
     */
    public static function on($event, $listener)
    {
        self::events()->addListener($event, $listener);
    }

    /**
     * Fire object associated event. Object instance will always be passed in context key "parent".
     * No event dispatcher will not be created automatically on method call.
     *
     * @param string $event
     * @param mixed  $context Event context.
     * @return mixed
     */
    protected function fire($event, $context = null)
    {
        if (empty(self::$eventDispatchers[static::class]))
        {
            return $context;
        }

        return self::$eventDispatchers[static::class]->fire(
            new ObjectEvent($event, $this, $context)
        );
    }
}