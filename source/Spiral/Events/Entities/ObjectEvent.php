<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Events\Entities;

/**
 * Event which being called by some object. Object will be available using parent() method.
 */
class ObjectEvent extends Event
{
    /**
     * @var object
     */
    private $parent = null;

    /**
     * @param object $parent
     * @param string $name
     * @param mixed  $context
     */
    public function __construct($parent, $name, $context)
    {
        $this->parent = $parent;

        parent::__construct($name, $context);
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