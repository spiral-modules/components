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
use Spiral\ORM\Commands\TransactionalCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

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
     * Related object changed.
     *
     * @var bool
     */
    private $changed = false;

    /**
     * {@inheritdoc}
     */
    public function setRelated($value)
    {
        //Make sure value is accepted
        $this->assertValid($value);

        $this->loaded = true;
        $this->changed = true;
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
        if (empty($this->instance)) {
            if (!$this->schema[Record::NULLABLE]) {
                throw new RelationException("No data presented in non nullable relation");
            }

            $command->addContext($this->schema[Record::INNER_KEY], null);

            return new NullCommand();
        }

        return $this->queueRelated($command);
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
            $leadingCommand instanceof InsertCommand
            && $primaryKey == $this->schema[Record::OUTER_KEY]
        ) {
            /**
             * Particular case when parent entity exists but now saved yet AND outer key is PK.
             */
            $leadingCommand->onExecute(function (InsertCommand $related) use ($command) {
                $command->addContext($this->schema[Record::INNER_KEY], $related->getInsertID());
            });
        } elseif ($this->changed) {
            $command->addContext(
                $this->schema[Record::INNER_KEY],
                $this->instance->getField($this->schema[Record::OUTER_KEY])
            );
        }

        return $related;
    }
}