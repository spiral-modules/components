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
    private static $dispatchers = [];

    /**
     * Global container access is required in some cases. Method should be declared statically.
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
        self::$dispatchers[static::class] = $dispatcher;
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
        if (isset(self::$dispatchers[static::class]))
        {
            return self::$dispatchers[static::class];
        }

        $container = self::getContainer();
        if (empty($container) || !$container->hasBinding(DispatcherInterface::class))
        {
            //Let's use default Dispatcher, no one will be harmed
            return self::$dispatchers[static::class] = new Dispatcher();
        }

        return self::$dispatchers[static::class] = $container->get(DispatcherInterface::class);
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
        if (empty(self::$dispatchers[static::class]))
        {
            //We can bypass dispatcher creation
            return $context;
        }

        return self::$dispatchers[static::class]->fire(new ObjectEvent($event, $this, $context));
    }
}