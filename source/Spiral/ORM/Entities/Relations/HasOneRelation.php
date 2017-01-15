<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\Commands\TransactionalCommand;
use Spiral\ORM\ContextualCommandInterface;
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

            //Store new entity if any (leading)
            $transaction->addCommand($this->queueRelated($command), true);

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

        //Related entity store command
        $inner = $this->instance->queueStore(true);

        if ($this->primaryColumnOf($this->parent) == $this->key(Record::INNER_KEY)) {
            /**
             * Particular case when parent entity exists but now saved yet AND outer key is PK.
             * Basically inversed case of BELONGS_TO.
             */
            $command->onExecute(function (ContextualCommandInterface $command) use ($inner) {
                $inner->addContext(
                    $this->schema[Record::OUTER_KEY],
                    $command->primaryKey()
                );
            });
        } elseif ($this->changed) {
            //We should have already non empty field (?)
            $inner->addContext(
                $this->key(Record::OUTER_KEY),
                $this->parent->getField($this->schema[Record::INNER_KEY])
            );
        }

        return $inner;
    }
}