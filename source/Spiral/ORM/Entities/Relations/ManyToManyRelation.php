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
use Spiral\ORM\Entities\Relations\Traits\MatchTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\RecordInterface;

class ManyToManyRelation extends AbstractRelation implements \IteratorAggregate
{
    use MatchTrait;

    /**
     * @var bool
     */
    private $autoload = true;

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
     * Partial selections will not be autoloaded.
     *
     * Example:
     *
     * $post = $this->findPost(); //no comments
     * $post->tags->partial(true);
     * assert($post->tags->count() == 0); //never loaded
     *
     * $post->comments->add($comment);
     *
     * @param bool $partial
     *
     * @return ManyToManyRelation
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

    public function sync(array $records, array $pivotData = [], bool $force = true)
    {
        if ($force) {
            //Load existed data
            $this->loadData(true);
        }
    }

    public function setPivot($record, array $pivotData)
    {
    }

    public function getPivot($record)
    {
    }

    public function link($record, array $pivotData = [])
    {
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

        // return $this->initInstances();
    }
}