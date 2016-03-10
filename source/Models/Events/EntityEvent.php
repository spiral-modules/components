<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models\Events;

use Spiral\Models\EntityInterface;

us  Symfony\Component\EventDispatcher\Event;

/**
 * Entity specific event.
 */
class EntityEvent extends Event
{
    /**
     * @var null|EntityInterface
     */
    private $entity = null;

    /**
     * @param EntityInterface $entity
     */
    public function __construct(EntityInterface $entity)
    {
        $this->entity = $entity;
    }

    /**
     * @return null|EntityInterface
     */
    public function entity()
    {
        return $this->entity;
    }
}
