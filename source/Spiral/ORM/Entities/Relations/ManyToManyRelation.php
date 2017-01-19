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
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Entities\RecordIterator;
use Spiral\ORM\Entities\Relations\Traits\MatchTrait;
use Spiral\ORM\Entities\Relations\Traits\PartialTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\RelationInterface;

class ManyToManyRelation extends AbstractRelation implements \IteratorAggregate
{
    use MatchTrait, PartialTrait;

    /**
     * @var \SplObjectStorage
     */
    private $pivotData;

    /**
     * @var RecordInterface[]
     */
    private $linked = [];

    /**
     * @var RecordInterface[]
     */
    private $unlinked = [];

    /**
     * {@inheritdoc}
     */
    public function hasRelated(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function withContext(
        RecordInterface $parent,
        bool $loaded = false,
        array $data = null
    ): RelationInterface {
        /**
         * @var self $relation
         */
        $relation = parent::withContext($parent, $loaded, $data);
        $relation->pivotData = new \SplObjectStorage();

        return $relation;
    }

    /**
     * {@inheritdoc}
     */
    public function setRelated($value)
    {
        if (is_null($value)) {
            $value = [];
        }

        if (!is_array($value)) {
            throw new RelationException("HasMany relation can only be set with array of entities");
        }

        //Sync values without forcing it (no autoloading), i.e. clear CURRENT associations
        $this->sync($value, [], false);
    }

    /**
     * @return $this
     */
    public function getRelated()
    {
        return $this;
    }

    /**
     * Iterate over linked instances, will force pre-loading unless partial.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->loadData(true)->linked);
    }

    /**
     * Get all unlinked records.
     *
     * @return \ArrayIterator
     */
    public function getUnlinked()
    {
        return new \ArrayIterator($this->unlinked);
    }

    /**
     * todo: write docs
     *
     * @param array $records
     * @param array $pivotData
     * @param bool  $force
     */
    public function sync(array $records, array $pivotData = [], bool $force = true)
    {
        if ($force) {
            //Load existed data
            $this->loadData(true);
        }
    }

    public function setPivot($record, array $pivotData)
    {
        $this->pivotData->offsetSet($record, $pivotData);
    }

    public function getPivot($record)
    {
        return $this->pivotData->offsetGet($record);
    }

    public function link($record, array $pivotData = [])
    {
        //Linkage!
        //$this->linked[] = $record;

        $this->pivotData->offsetSet($record, $pivotData);
    }

    public function unlink($record)
    {
    }

    public function has($query)
    {

    }

    public function matchOne($query)
    {
    }

    public function matchMultiple($query)
    {

    }

    public function queueCommands(ContextualCommandInterface $command): CommandInterface
    {
        return new NullCommand();
    }

    /**
     * Load related records from database.
     *
     * @param bool $autoload
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
                //Only for non partial selections
                //   $this->data = $this->loadRelated();
            } else {
                $this->data = [];
            }
        }

        return $this->initInstances();
    }

    /**
     * Init relations and populate pivot map.
     *
     * @return ManyToManyRelation
     */
    private function initInstances(): self
    {
        if (is_array($this->data) && !empty($this->data)) {
            //Iterates and instantiate records
            $iterator = new RecordIterator($this->data, $this->class, $this->orm);

            foreach ($iterator as $pivotData => $item) {
                if ($this->has($item)) {
                    //Skip duplicates
                    continue;
                }

                $this->pivotData->attach($item, $pivotData);
                $this->linked[] = $item;
            }
        }

        //Memory free
        $this->data = null;

        return $this;
    }
}