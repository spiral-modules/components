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
use Spiral\Events\Entities\ObjectEvent;

/**
 * Allow class to have statically (class name) based event dispatcher. Will try to use container
 * to resolve dispatcher instance or create one default.
 *
 * Trait requires static (!) implementation of container() method to automatically resolve dispatcher
 * instance (allowed to return null).
 */
trait EventsTrait
{
    /**
     * List of dispatchers associated with their class name.
     *
     * @var DispatcherInterface[]
     */
    private static $dispatchers = [];

    /**
     * @return ContainerInterface|null
     */
    abstract public function container();

    /**
     * Set event dispatchers manually for current class. Can erase existed dispatcher by providing
     * null as value.
     *
     * @param DispatcherInterface|null $dispatcher
     */
    public static function setEvents(DispatcherInterface $dispatcher = null)
    {
        self::$dispatchers[static::class] = $dispatcher;
    }

    /**
     * Get class associated event dispatcher or create default one.
     *
     * @return DispatcherInterface
     */
    public static function events()
    {
        if (isset(self::$dispatchers[static::class])) {
            return self::$dispatchers[static::class];
        }

        if (empty($container = self::container()) || !$container->hasBinding(DispatcherInterface::class)) {
            //Let's use default Dispatcher, no one will be harmed
            return self::$dispatchers[static::class] = new Dispatcher();
        }

        return self::$dispatchers[static::class] = $container->get(DispatcherInterface::class);
    }

    /**
     * Fire object specific event (ObjectEvent will be used) with pointer to parent class (can be
     * called only in runtime).
     *
     * @param string $event   Event name.
     * @param mixed  $context Passed context.
     * @return mixed          Processed event content.
     */
    protected function fire($event, $context = null)
    {
        if (empty(self::$dispatchers[static::class])) {
            //We can bypass dispatcher creation
            return $context;
        }

        return self::$dispatchers[static::class]->fire(new ObjectEvent($this, $event, $context));
    }
}