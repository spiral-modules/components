<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Events;

use Spiral\Events\Exceptions\InvalidArgumentException;

/**
 * Used by models and other classes to add events support.
 */
interface DispatcherInterface
{
    /**
     * Add listener for specified event.
     *
     * @param string   $event
     * @param callable $listener
     * @return self
     */
    public function listen($event, $listener);

    /**
     * Remove event specific listener.
     *
     * @param string   $event
     * @param callable $listener
     * @return self
     */
    public function remove($event, $listener);

    /**
     * Check if event has specified listener.
     *
     * @param string   $event
     * @param callable $listener
     * @return bool
     */
    public function hasListener($event, $listener);

    /**
     * List of every event listener.
     *
     * @param string $event
     * @return callable[]
     */
    public function listeners($event);

    /**
     * Fire event instance or create default event implementation with specified context.
     *
     * @param EventInterface|string $event   Event instance or event name (default implementation to
     *                                       use).
     * @param mixed                 $context Event context to be passed.
     * @return mixed                         Passed event context.
     * @throws InvalidArgumentException
     */
    public function fire($event, $context = null);
}