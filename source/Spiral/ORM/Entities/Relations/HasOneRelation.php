<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\Commands\InsertCommand;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;

class HasOneRelation extends SingularRelation
{
    const CREATE_PLACEHOLDER = true;

    /**
     * Previously binded instance, to be deleted.
     *
     * @var RecordInterface
     */
    private $previous = null;

    /**
     * Related object changed.
     *
     * @var bool
     */
    private $changed = true;

    /**
     * {@inheritdoc}
     */
    public function setRelated($value)
    {
        //Make sure value is accepted
        $this->assertValid($value);

        $this->loaded = true;
        $this->changed = true;

        $this->previous = $this->instance;
        $this->instance = $value;
    }

    public function queueCommands(ContextualCommandInterface $command)
    {
        if (empty($this->instance)) {
            return new NullCommand();
        }

        $related = $this->instance->queueStore(true);

        //Primary key of parent entity
        $primaryKey = $this->orm->define(get_class($this->parent), ORMInterface::R_PRIMARY_KEY);

        if (
            $command instanceof InsertCommand
            && $primaryKey == $this->schema[Record::INNER_KEY]
        ) {
            /**
             * Particular case when parent entity exists but now saved yet AND outer key is PK.
             *
             * Basically inversed case of BELONGS_TO.
             */
            $command->onExecute(function (InsertCommand $command) use ($related) {
                $related->addContext($this->schema[Record::OUTER_KEY], $command->getInsertID());
            });
        } elseif ($this->changed) {
            //Delete old one!
            $related->addContext(
                $this->schema[Record::OUTER_KEY],
                $this->parent->getField($this->schema[Record::INNER_KEY])
            );
        }

        return $related;
    }
}