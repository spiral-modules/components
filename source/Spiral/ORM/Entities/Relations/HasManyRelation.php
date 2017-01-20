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
use Spiral\ORM\Entities\RecordIterator;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Entities\Relations\Traits\LookupTrait;
use Spiral\ORM\Entities\Relations\Traits\MatchTrait;
use Spiral\ORM\Entities\Relations\Traits\PartialTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\Helpers\WhereDecorator;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\RelationInterface;

/**
 * Attention, this relation delete operation works inside loaded scope!
 *
 * When empty array assigned to relation it will schedule all related instances to be deleted.
 *
 * If you wish to load with relation WITHOUT loading previous records use [] initialization.
 */
class HasManyRelation extends AbstractRelation implements \IteratorAggregate, \Countable
{
    use MatchTrait, PartialTrait, LookupTrait;

    /**
     * Loaded list of records. SplObjectStorage?
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
     */
    public function hasRelated(): bool
    {
        if (!$this->isLoaded()) {
            //Lazy loading our relation data
            $this->loadData();
        }

        return !empty($this->instances);
    }

    /**
     * {@inheritdoc}
     */
    public function withContext(
        RecordInterface $parent,
        bool $loaded = false,
        array $data = null
    ): RelationInterface {
        $hasMany = parent::withContext($parent, $loaded, $data);

        /** @var self $hasMany */
        return $hasMany->initInstances();
    }

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    public function setRelated($value)
    {
        $this->loadData(true);

        if (is_null($value)) {
            $value = [];
        }

        if (!is_array($value)) {
            throw new RelationException("HasMany relation can only be set with array of entities");
        }

        //todo: optimize this section!?

        //Cleaning existed instances
        $this->deletedInstances = array_unique(array_merge(
            $this->deletedInstances,
            $this->instances
        ));

        $this->instances = [];
        foreach ($value as $item) {
            if (!is_null($item)) {
                $this->assertValid($item);
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
        return $this;
    }

    /**
     * Iterate over instance set.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->loadData(true)->instances);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->loadData(true)->instances);
    }

    /**
     * Iterate over deleted instances.
     *
     * @return \ArrayIterator
     */
    public function getDeleted()
    {
        return new \ArrayIterator($this->deletedInstances);
    }

    /**
     * Method will autoload data.
     *
     * @param array|RecordInterface|mixed $query Fields, entity or PK.
     *
     * @return bool
     */
    public function has($query): bool
    {
        return !empty($this->matchOne($query));
    }

    /**
     * Add new record into entity set. Attention, usage of this method WILL load relation data
     * unless partial.
     *
     * @param RecordInterface $record
     *
     * @return self
     *
     * @throws RelationException
     */
    public function add(RecordInterface $record): self
    {
        $this->assertValid($record);
        $this->loadData(true)->instances[] = $record;

        return $this;
    }

    /**
     * Delete one record, strict compaction, make sure exactly same instance is given.
     *
     * @param RecordInterface $record
     *
     * @return self
     *
     * @throws RelationException
     */
    public function delete(RecordInterface $record): self
    {
        $this->loadData(true);
        $this->assertValid($record);

        foreach ($this->instances as $index => $instance) {
            if ($this->match($instance, $record)) {
                //Remove from save
                unset($this->instances[$index]);
                $this->deletedInstances[] = $instance;
                break;
            }
        }

        $this->instances = array_values($this->instances);

        return $this;
    }

    /**
     * Fine one entity for a given query or return null. Method will autoload data.
     *
     * Example: ->matchOne(['value' => 'something', ...]);
     *
     * @param array|RecordInterface|mixed $query Fields, entity or PK.
     *
     * @return RecordInterface|null
     */
    public function matchOne($query)
    {
        foreach ($this->loadData(true)->instances as $instance) {
            if ($this->match($instance, $query)) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Return only instances matched given query, performed in memory! Only simple conditions are
     * allowed. Not "find" due trademark violation. Method will autoload data.
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
        foreach ($this->loadData()->instances as $instance) {
            if ($this->match($instance, $query)) {
                $result[] = $instance;
            }
        }

        return new \ArrayIterator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function queueCommands(ContextualCommandInterface $parentCommand): CommandInterface
    {
        //No autoloading here

        if (empty($this->instances) && empty($this->deletedInstances)) {
            return new NullCommand();
        }

        $transaction = new TransactionalCommand();

        //Delete old instances first
        foreach ($this->deletedInstances as $deleted) {
            //To de-associate use BELONGS_TO relation
            $transaction->addCommand($deleted->queueDelete());
        }

        //Store all instances
        foreach ($this->instances as $instance) {
            $transaction->addCommand($this->queueRelated($parentCommand, $instance));
        }

        //Flushing instances
        $this->deletedInstances = [];

        return $transaction;
    }

    /**
     * {@inheritdoc}
     *
     * @return self
     *
     * @throws SelectorException
     * @throws QueryException (needs wrapping)
     */
    protected function loadData(bool $autoload = true): self
    {
        if ($this->loaded) {
            return $this;
        }

        $this->loaded = true;

        if (empty($this->data) || !is_array($this->data)) {
            if ($this->autoload && $autoload) {
                //Only for non partial selections (excluded already selected)
                $this->data = $this->loadRelated();
            } else {
                $this->data = [];
            }
        }

        return $this->initInstances();
    }

    /**
     * Fetch data from database. Lazy load.
     *
     * @return array
     */
    protected function loadRelated(): array
    {
        $innerKey = $this->parent->getField($this->key(Record::INNER_KEY));
        if (!empty($innerKey)) {
            return $this->createSelector($innerKey)->fetchData();
        }

        return [];
    }

    /**
     * Create outer selector for a given inner key value.
     *
     * @param mixed $innerKey
     *
     * @return RecordSelector
     */
    protected function createSelector($innerKey): RecordSelector
    {
        $selector = $this->orm->selector($this->class)->where(
            $this->key(Record::OUTER_KEY),
            $innerKey
        );

        if (!empty($this->schema[Record::WHERE])) {
            //Configuring where conditions with alias resolution
            $decorator = new WhereDecorator($selector, 'where', $selector->getAlias());
            $decorator->where($this->schema[Record::WHERE]);

            return $selector;
        }

        return $selector;
    }

    /**
     * Init pre-loaded data.
     *
     * @return HasManyRelation
     */
    private function initInstances(): self
    {
        if (is_array($this->data) && !empty($this->data)) {
            //Iterates and instantiate records
            $iterator = new RecordIterator($this->data, $this->class, $this->orm);

            foreach ($iterator as $item) {
                if ($this->has($item)) {
                    //Skip duplicates
                    continue;
                }

                $this->instances[] = $item;
            }
        }

        //Memory free
        $this->data = null;

        return $this;
    }

    /**
     * @param ContextualCommandInterface $parentCommand
     * @param RecordInterface            $instance
     *
     * @return CommandInterface
     */
    private function queueRelated(
        ContextualCommandInterface $parentCommand,
        RecordInterface $instance
    ): CommandInterface {
        //Related entity store command
        $innerCommand = $instance->queueStore(true);

        if (!$this->isSynced($this->parent, $instance)) {
            //Delayed linking
            $parentCommand->onExecute(function ($outerCommand) use ($innerCommand) {
                $innerCommand->addContext(
                    $this->key(Record::OUTER_KEY),
                    $this->lookupKey(Record::INNER_KEY, $this->parent, $outerCommand)
                );
            });
        }

        return $innerCommand;
    }
}