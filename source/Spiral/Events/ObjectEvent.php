<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Events;

/**
 * Event which being called by some object. Object
 */
class ObjectEvent extends Event
{
    /**
     * @var object
     */
    protected $parent = null;

    /**
     * @param object $parent
     * @param string $name
     * @param mixed  $context
     */
    public function __construct($parent, $name, $context = null)
    {
        parent::__construct($name, $context);
        $this->parent = $parent;
    }

    /**
     * Object which raised an event.
     *
     * @return object
     */
    public function parent()
    {
        return $this->parent;
    }
}