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
     * {@inheritdoc}
     *
     * Attempts to load parent from memory map!
     */
    protected function loadData()
    {
        $outerKey = $this->parent->getField($this->key(Record::OUTER_KEY));
        /*
         * Attempt to load entity from entity map, when entity map is set, parent is loaded and outer
         * key is set. If failed - lazy load will happen. This code can help to drastically reduce
         * amount of queries in cases where child call parent without inload or with inload).
         *
         * First child which will initiate specific parent WILL share same instance with other child,
         * save collisions are resolved via SCHEDULE statuses.
         */
        if (!$this->isLoaded() && $this->orm->hasMap() && !empty($outerKey)) {
            if ($this->orm->getMap()->has($this->class, $outerKey)) {
                $this->instance = $this->orm->getMap()->get($this->class, $outerKey);
                $this->loaded = true;

                return;
            }
        }

        parent::loadData();
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