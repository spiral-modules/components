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
use Spiral\ORM\Entities\Relations\Traits\MatchTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Exceptions\SelectorException;
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
class HasManyRelation extends AbstractRelation implements \IteratorAggregate
{
    use MatchTrait;

    /**
     * @var bool
     */
    private $autoload = true;

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
    public function withContext(
        RecordInterface $parent,
        bool $loaded = false,
        array $data = null
    ): RelationInterface {
        $hasMany = parent::withContext($parent, $loaded, $data);

        /**
         * @var self $hasMany
         */
        if ($hasMany->loaded) {
            //Init all nested models immediately
            $hasMany->initInstances();
        }

        return $hasMany->initInstances();
    }

    /**
     * Partial selections will not be autoloaded.
     *
     * Example:
     *
     * $post = $this->findPost(); //no comments
     * $post->comments->partial(true);
     * $post->comments->add(new Comment());
     * assert($post->comments->count() == 1); //no other comments to be loaded
     *
     * $post->comments->add($comment);
     *
     * @param bool $partial
     *
     * @return HasManyRelation
     */
    public function partial(bool $partial = true): self
    {
        $this->autoload = !$partial;

        return $this;
    }

    /**
     * @return bool
     */
    public function isPartial(): bool
    {
        return !$this->autoload;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    public function setRelated($value)
    {
        $this->loadData();

        if (is_null($value)) {
            $value = [];
        }

        if (!is_array($value)) {
            throw new RelationException("HasMany relation can only be set with array of entities");
        }

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
     * Iterate over deleted instances.
     *
     * @return \ArrayIterator
     */
    public function getDeleted()
    {
        return new \ArrayIterator($this->deletedInstances);
    }

    /**
     * Add new record into entity set. Attention, usage of this method WILL load relation data
     * unless partial.
     *
     * @param RecordInterface $record
     *
     * @throws RelationException
     */
    public function add(RecordInterface $record)
    {
        $this->assertValid($record);
        $this->loadData(true)->instances[] = $record;
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
     * Delete one record, strict compaction, make sure exactly same instance is given.
     *
     * @param RecordInterface $record
     */
    public function delete(RecordInterface $record)
    {
        $this->deleteMultiple([$record]);
    }

    /**
     * Delete multiple records, strict compaction, make sure exactly same instance is given. Method
     * will autoload relation data unless partial.
     *
     * @param array|\Traversable $records
     */
    public function deleteMultiple($records)
    {
        $this->loadData(true);

        foreach ($records as $record) {
            $this->assertValid($record);

            foreach ($this->instances as $index => $instance) {
                if ($instance === $record) {
                    //Remove from save
                    unset($this->instances[$index]);
                    $this->deletedInstances[] = $instance;
                    break;
                }
            }
        }

        $this->instances = array_values($this->instances);
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
    public function queueCommands(ContextualCommandInterface $command): CommandInterface
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
            $transaction->addCommand($this->queueRelated($command, $instance));
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
        $innerKey = $this->key(Record::INNER_KEY);
        if (!empty($this->parent->getField($innerKey))) {
            return $this->orm
                ->selector($this->class)
                ->where($this->key(Record::OUTER_KEY), $this->parent->getField($innerKey))
                ->fetchData();
        }

        return [];
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
     * @param ContextualCommandInterface $command
     * @param RecordInterface            $instance
     *
     * @return CommandInterface
     */
    private function queueRelated(
        ContextualCommandInterface $command,
        RecordInterface $instance
    ): CommandInterface {
        //Related entity store command
        $inner = $instance->queueStore(true);

        if (!$this->isSynced($this->parent, $instance)) {
            //Syncing FKs
            if ($this->key(Record::INNER_KEY) != $this->primaryColumnOf($this->parent)) {
                $command->addContext(
                    $this->key(Record::OUTER_KEY),
                    $this->parent->getField($this->key(Record::INNER_KEY))
                );
            } else {
                //Syncing FKs
                $command->onExecute(function (ContextualCommandInterface $command) use ($inner) {
                    $inner->addContext(
                        $this->key(Record::OUTER_KEY),
                        $command->primaryKey()
                    );
                });
            }
        }

        return $inner;
    }
}