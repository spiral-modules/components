<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Events\Traits;

use Spiral\Events\Dispatcher;
use Spiral\Events\DispatcherInterface;
use Spiral\Events\Entities\ObjectEvent;

/**
 * Allow class to have statically (class name) based event dispatcher.
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

        return self::$dispatchers[static::class] = new Dispatcher();
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