<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Events;

/**
 * Event carry information about some context and will be passed though the chain of listeners (if
 * any).
 */
interface EventInterface
{
    /**
     * Event name. Shorted for convenience. You can't change name anyway.
     *
     * @return string
     */
    public function name();

    /**
     * Get event context reference. Can point to anything (usually array) and should be editable.
     *
     * @return mixed
     */
    public function &context();

    /**
     * Event being stopped. Listeners chain should be aborted.
     *
     * @return bool
     */
    public function isStopped();

    /**
     * Has to be set by listener to indicate that no other listener has to be executed.
     */
    public function stopPropagation();
}