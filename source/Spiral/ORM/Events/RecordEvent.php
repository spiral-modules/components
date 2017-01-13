<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Events;

use Spiral\Models\EntityInterface;
use Spiral\Models\Events\EntityEvent;
use Spiral\ORM\CommandInterface;

class RecordEvent extends EntityEvent
{
    /**
     * @var CommandInterface
     */
    private $command;

    /**
     * @param EntityInterface  $entity
     * @param CommandInterface $command questionable
     */
    public function __construct(EntityInterface $entity, CommandInterface $command)
    {
        parent::__construct($entity);
        $this->command = $command;
    }

    /**
     * Command associated with entity operation. [QUESTIONABLE]
     *
     * @return CommandInterface
     */
    public function getCommand(): CommandInterface
    {
        return $this->command;
    }
}