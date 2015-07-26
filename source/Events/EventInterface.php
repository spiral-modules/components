<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Events;

interface EventInterface
{
    /**
     * Event name.
     *
     * @return string
     */
    public function getName();

    /**
     * Get event content reference. Get word is removed to notify user that this is not usual getter.
     *
     * @return mixed
     */
    public function &context();

    /**
     * Indication that event chain were stopped by one of handlers.
     *
     * @return bool
     */
    public function isStopped();

    /**
     * Stops event chain, EventDispatcher will end performing right after listener called this method.
     */
    public function stopPropagation();
}