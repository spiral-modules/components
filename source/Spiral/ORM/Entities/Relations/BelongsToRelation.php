<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Exceptions\RelationException;
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
        //Command or command set needed to store
        $related = $this->instance->queueStore(true);

        if (!$this->isSynced($this->parent, $this->instance)) {
            //Syncing FKs
            if ($this->key(Record::OUTER_KEY) != $this->primaryColumnOf($this->parent)) {
                $command->addContext(
                    $this->key(Record::INNER_KEY),
                    $this->parent->getField($this->key(Record::OUTER_KEY))
                );
            } else {
                //Syncing using promise
                $related->onExecute(function (ContextualCommandInterface $related) use ($command) {
                    //Giving our child our context in a form of FK value
                    $command->addContext($this->key(Record::INNER_KEY), $related->primaryKey());
                });
            }
        }

        return $related;
    }
}