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
use Spiral\ORM\ORMInterface;
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
            //Init all nested models immidiatelly
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
     * assert($post->comments->count() == 0); //never loaded
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
     * @param bool $autoload When true all existed records will be loaded and removed.
     *
     * @throws RelationException
     */
    public function setRelated($value, bool $autoload = true)
    {
        $this->autoload = $autoload;
        $this->loadData();

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
        return $this->loadData(true);
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
     * Add new record into entity set. Attention, usage of this method WILL make relation partial.
     *
     * @param RecordInterface $record
     *
     * @throws RelationException
     */
    public function add(RecordInterface $record)
    {
        $this->assertValid($record);

        $this->autoload = false;
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
     * Delete multiple records, strict compaction, make sure exactly same instance is given. Method
     * would not autoload instance and will mark it as partial.
     *
     * @param array|\Traversable $records
     */
    public function deleteMultiple($records)
    {
        //Partial
        $this->autoload = false;

        foreach ($records as $record) {
            $this->assertValid($record);

            foreach ($this->instances as $index => $instance) {
                if ($instance === $record) {
                    //Remove from save
                    unset($this->instances[$index]);
                }

                $this->deletedInstances[] = $instance;
            }
        }

        $this->instances = array_values($this->instances);
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
        foreach ($this->loadData()->instances as $instance) {
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
     * @throws QueryException
     */
    protected function loadData(bool $autoload = true): self
    {
        if ($this->loaded) {
            return $this;
        }

        $this->loaded = true;

        if (empty($this->data) || !is_array($this->data)) {
            if ($this->autoload && $autoload) {
                //Only for non partial selections
                $this->data = $this->loadRelated();
            } else {
                $this->data = [];
            }
        }

        return $this->initInstances();
    }

    /**
     * Init pre-loaded data.
     *
     * @return HasManyRelation
     */
    private function initInstances(): self
    {
        if (is_array($this->data) && !empty($this->data)) {
            foreach ($this->data as $item) {
                $this->instances[] = $this->orm->make(
                    $this->class,
                    $item,
                    ORMInterface::STATE_LOADED,
                    true
                );
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

    /**
     * Fetch data from database.
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
}