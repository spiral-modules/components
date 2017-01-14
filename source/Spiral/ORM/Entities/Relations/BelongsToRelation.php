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
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\SyncCommandInterface;

/**
 * Complex relation with ability to mount inner_key context into parent save command.
 */
class BelongsToRelation extends SingularRelation
{
    /**
     * Always saved before parent.
     */
    const LEADING_RELATION = true;

    /**
     * No placeholder for belongs to.
     */
    const CREATE_PLACEHOLDER = false;

    /**
     * {@inheritdoc}
     */
    public function setRelated($value)
    {
        //Make sure value is accepted
        $this->assertValid($value);

        $this->loaded = true;
        $this->instance = $value;
    }

    /**
     * @param ContextualCommandInterface $command
     *
     * @return CommandInterface
     *
     * @throws RelationException
     */
    public function queueCommands(ContextualCommandInterface $command): CommandInterface
    {
        if (!empty($this->instance)) {
            return $this->queueRelated($command);
        }

        if (!$this->schema[Record::NULLABLE]) {
            throw new RelationException("No data presented in non nullable relation");
        }

        $command->addContext($this->schema[Record::INNER_KEY], null);

        return new NullCommand();
    }

    /**
     * Store related instance
     *
     * @param ContextualCommandInterface $command
     *
     * @return ContextualCommandInterface
     */
    private function queueRelated(ContextualCommandInterface $command): ContextualCommandInterface
    {
        $related = $this->instance->queueStore(true);
        $leadingCommand = $related instanceof TransactionalCommand ? $related->getLeading() : $related;

        //Primary key of associated entity
        $primaryKey = $this->orm->define(get_class($this->instance), ORMInterface::R_PRIMARY_KEY);

        if (
            $leadingCommand instanceof SyncCommandInterface
            && $primaryKey == $this->key(Record::OUTER_KEY)
        ) {
            /**
             * Particular case when parent entity exists but now saved yet AND outer key is PK.
             *
             * Promised by previous command.
             */
            $leadingCommand->onExecute(function (SyncCommandInterface $related) use ($command) {
                $command->addContext($this->key(Record::INNER_KEY), $related->primaryKey());
            });
        }

        return $related;
    }
}