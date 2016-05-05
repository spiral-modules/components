<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities;

use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordInterface;

/**
 * Provides iteration over set of specified records data using internal instances cache. Does not
 * implements classic "collection" methods besides 'has'.
 *
 * Each entity kept inside internal array and given back as clone to prevent issues caused by
 * mutability.
 */
class RecordIterator implements \Iterator, \Countable, \JsonSerializable
{
    /**
     * Current iterator position.
     *
     * @var int
     */
    private $position = 0;

    /**
     * @var string
     */
    protected $class = '';

    /**
     * Data to be iterated.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Constructed record instances. Cache.
     *
     * @var RecordInterface[]
     */
    protected $instances = [];

    /**
     * @invisible
     *
     * @var ORMInterface
     */
    protected $orm = null;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     * @param array        $data
     */
    public function __construct($class, ORMInterface $orm, array $data)
    {
        $this->class = $class;
        $this->orm = $orm;
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * Get all Records as array.
     *
     * @return RecordInterface[]
     */
    public function all()
    {
        $result = [];

        /**
         * Cloning to prevent position overlapping.
         *
         * @var self|RecordInterface[]
         */
        $iterator = clone $this;
        foreach ($iterator as $nested) {
            $result[] = $nested;
        }

        //Copying instances just in case
        $this->instances = $iterator->instances;

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @return RecordInterface
     *
     * @see ORMInterface::record()
     * @see RecordInterface::withContext()
     *
     * @throws ORMException
     */
    public function current()
    {
        if (!isset($this->instances[$this->position])) {
            //Constructing entity into local iterator cache
            $this->instances[$this->position] = $this->orm->record(
                $this->class,
                $this->data[$this->position]
            );
        }

        //We are breaking reference here so entity modifications can't alter iterator (todo: implement withPivot method?)
        return clone $this->instances[$this->position];
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return isset($this->data[$this->position]);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * Check if record or record with specified id presents in iteration.
     *
     * @param RecordInterface|string|int $record
     *
     * @return true
     */
    public function has($record)
    {
        foreach ($this->all() as $nested) {
            if (
                is_array($record) && array_intersect_assoc($nested->getFields(), $record) == $record
            ) {
                //Comparing via fields intersection
                return true;

            }

            if (
                is_scalar($record) && !empty($record) && $nested->primaryKey() == $record
            ) {
                //Comparing using primary keys
                return true;
            }

            if (
                $nested == $record || $nested->getFields() == $record->getFields()
            ) {
                //Comparing as object vars
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->all();
    }

    /**
     * @return RecordInterface[]
     */
    public function __debugInfo()
    {
        return $this->all();
    }

    /**
     * Flushing references.
     */
    public function __destruct()
    {
        $this->data = [];
        $this->instances = [];
        $this->orm = null;
    }
}