<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Events;

/**
 * Object specific event.
 */
interface ObjectEventInterface extends EventInterface
{
    /**
     * Object which raised an event.
     *
     * @return object
     */
    public function parent();
}