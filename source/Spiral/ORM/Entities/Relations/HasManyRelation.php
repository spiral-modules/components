<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\Database\Exceptions\QueryException;
use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\Commands\TransactionalCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Entities\Relations\Traits\MatchTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\SyncCommandInterface;

/**
 * Attention, this relation delete operation works inside loaded scope!
 *
 * When empty array assigned to relation it will schedule all related instances to be deleted.
 */
class HasManyRelation extends AbstractRelation implements \IteratorAggregate
{
    use MatchTrait;

    /**
     * Loaded list of records.
     *
     * @var RecordInterface[]
     */
    private $instances = [];

    /**
     * Records deleted from list. Potentially pre-schedule command?
     *
     * @var RecordInterface[]
     */
    private $deletedInstances = [];

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    public function setRelated($value)
    {
        if (!is_array($value)) {
            throw new RelationException("HasMany relation can only be set with array of entities");
        }

        //Cleaning existed instances
        $this->deletedInstances = array_merge($this->deletedInstances, $this->instances);
        $this->instances = [];

        foreach ($value as $item) {
            if (!is_null($item)) {
                $this->assertValid($value);
                $this->instances[] = $item;
            }
        }
    }

    /**
     * Has many relation represent itself (see getIterator method).
     *
     * @return $this
     */
    public function getRelated()
    {
        if (!$this->isLoaded()) {
            //Lazy loading our relation data
            $this->loadData();
        }

        return $this;
    }

    /**
     * Iterate over instance set.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->instances);
    }

    /**
     * Iterate over deleted instanes.
     *
     * @return \ArrayIterator
     */
    public function getDeletedInstances()
    {
        return new \ArrayIterator($this->deletedInstances);
    }

    /**
     * {@inheritdoc}
     */
    public function queueCommands(ContextualCommandInterface $command): CommandInterface
    {
        if (empty($this->instances) && empty($this->deletedInstances)) {
            return new NullCommand();
        }

        $transaction = new TransactionalCommand();

        //Delete old instances first
        foreach ($this->deletedInstances as $deleted) {
            $transaction->addCommand($deleted->queueDelete());
        }

        //Leading (parent command)
        $transaction->addCommand($command, true);

        //Store all instances
        foreach ($this->instances as $instance) {
            $transaction->addCommand($this->queueRelated($command, $instance));
        }

        //Flushing instances
        $this->deletedInstances = [];

        return $transaction;
    }

    /**
     * Add new record into entity set.
     *
     * @param RecordInterface $record
     *
     * @throws RelationException
     */
    public function add(RecordInterface $record)
    {
        $this->assertValid($record);
        $this->instances[] = $record;
    }

    /**
     * Delete one record, strict compaction, make sure exactly same instance is given.
     *
     * @param RecordInterface $record
     */
    public function delete(RecordInterface $record)
    {
        $this->deleteMultiple([$record]);
    }

    /**
     * Delete multiple records, strict compaction, make sure exactly same instance is given.
     *
     * @param array|\Traversable $records
     */
    public function deleteMultiple($records)
    {
        foreach ($records as $record) {
            foreach ($this->instances as $index => $instance) {
                if ($instance === $record) {
                    $this->deletedInstances[] = $instance;
                    unset($this->instances[$index]);
                }
            }
        }

        $this->instances = array_values($this->instances);
    }

    /**
     * @param array|RecordInterface|mixed $query Fields, entity or PK.
     *
     * @return bool
     */
    public function has($query): bool
    {
        return !empty($this->matchOne($query));
    }

    /**
     * Fine one entity for a given query or return null.
     *
     * Example: ->matchOne(['value' => 'something', ...]);
     *
     * @param array|RecordInterface|mixed $query Fields, entity or PK.
     *
     * @return RecordInterface|null
     */
    public function matchOne($query)
    {
        foreach ($this->instances as $instance) {
            if ($this->match($instance, $query)) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Return only instances matched given query, performed in memory! Only simple conditions are
     * allowed. Not "find" due trademark violation.
     *
     * Example: ->matchMultiple(['value' => 'something', ...]);
     *
     * @param array|RecordInterface|mixed $query Fields, entity or PK.
     *
     * @return \ArrayIterator
     */
    public function matchMultiple($query)
    {
        $result = [];
        foreach ($this->instances as $instance) {
            if ($this->match($instance, $query)) {
                $result[] = $instance;
            }
        }

        return new \ArrayIterator($result);
    }

    /**
     * {@inheritdoc}
     *
     * @throws SelectorException
     * @throws QueryException
     */
    protected function loadData()
    {
        $this->loaded = true;

//        $innerKey = $this->key(Record::INNER_KEY);
//        if (empty($this->parent->getField($innerKey))) {
//            //Unable to load
//            return;
//        }
//
//        $this->data = $this->orm->selector($this->class)->where(
//            $this->key(Record::OUTER_KEY),
//            $this->parent->getField($innerKey)
//        )->fetchData();
//
//        if (!empty($this->data[0])) {
//            //Use first result
//            $this->data = $this->data[0];
//        }
    }

    /**
     * @param ContextualCommandInterface $command
     * @param RecordInterface            $instance
     *
     * @return CommandInterface
     */
    private function queueRelated(
        ContextualCommandInterface $command,
        RecordInterface $instance
    ): CommandInterface {
        //Inner storing inner instance
        $inner = $instance->queueStore(true);

        if ($this->primaryColumnOf($this->parent) == $this->key(Record::INNER_KEY)) {
            /**
             * Particular case when parent entity exists but now saved yet AND outer key is PK.
             * Basically inversed case of BELONGS_TO.
             */
            $command->onExecute(function (ContextualCommandInterface $command) use ($inner) {
                $inner->addContext($this->schema[Record::OUTER_KEY], $command->primaryKey());
            });
        } else {
            //Must already be set
            $inner->addContext(
                $this->key(Record::OUTER_KEY),
                $this->parent->getField($this->schema[Record::INNER_KEY])
            );
        }

        return $inner;
    }
}