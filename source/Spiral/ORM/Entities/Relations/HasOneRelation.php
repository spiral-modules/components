<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\InsertCommand;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\Commands\SyncCommand;
use Spiral\ORM\Commands\TransactionalCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\SyncCommandInterface;

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

        if (empty($this->previous)) {
            //We are only keeping reference to the oldest (ie loaded) instance
            $this->previous = $this->instance;
        }

        $this->instance = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function queueCommands(ContextualCommandInterface $command): CommandInterface
    {
        if (!empty($this->previous)) {
            $transaction = new TransactionalCommand();

            //Delete old entity
            $transaction->addCommand($this->previous->queueDelete());

            //Store new entity if any
            $transaction->addCommand($this->queueRelated($command));

            //We don't need previous reference anymore
            $this->previous = null;

            return $transaction;
        }

        return $this->queueRelated($command);
    }

    /**
     * Store related instance.
     *
     * @param ContextualCommandInterface $command
     *
     * @return CommandInterface
     */
    private function queueRelated(ContextualCommandInterface $command): CommandInterface
    {
        if (empty($this->instance)) {
            return new NullCommand();
        }

        $related = $this->instance->queueStore(true);

        //Primary key of parent entity
        $primaryKey = $this->orm->define(get_class($this->parent), ORMInterface::R_PRIMARY_KEY);

        if (
            $command instanceof SyncCommandInterface
            && $primaryKey == $this->key(Record::INNER_KEY)
        ) {
            /**
             * Particular case when parent entity exists but now saved yet AND outer key is PK.
             *
             * Basically inversed case of BELONGS_TO.
             */
            $command->onExecute(function (SyncCommandInterface $command) use ($related) {
                $related->addContext($this->schema[Record::OUTER_KEY], $command->primaryKey());
            });
        } elseif ($this->changed) {
            //Delete old one!
            $related->addContext(
                $this->key(Record::OUTER_KEY),
                $this->parent->getField($this->schema[Record::INNER_KEY])
            );
        }

        return $related;
    }
}