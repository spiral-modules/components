<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Events\Entities;

use Spiral\Events\ObjectEventInterface;

/**
 * Event which being called by some object. Object will be available using parent() method.
 */
class ObjectEvent extends Event implements ObjectEventInterface
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
     * {@inheritdoc}
     */
    public function parent()
    {
        return $this->parent;
    }
}