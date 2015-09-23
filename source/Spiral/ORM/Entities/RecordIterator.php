<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities;

use Spiral\ORM\Exceptions\IteratorException;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * Provides iteration over set of specified records data using internal instances cache. In
 * addition, allows to decorate set of callbacks with association by their name (@see __call()).
 * Keeps record context.
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
     * Set of "methods" to be decorated.
     *
     * @var callable[]
     */
    private $callbacks = [];

    /**
     * @var string
     */
    protected $class = '';

    /**
     * Indication that entity cache must be used.
     *
     * @var bool
     */
    protected $cache = true;

    /**
     * Data to be iterated.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Constructed record instances. Cache.
     *
     * @var RecordEntity[]
     */
    protected $instances = [];

    /**
     * @invisible
     * @var ORM
     */
    protected $orm = null;

    /**
     * @param ORM        $orm
     * @param string     $class
     * @param array      $data
     * @param bool       $cache
     * @param callable[] $callbacks
     */
    public function __construct(ORM $orm, $class, array $data, $cache = true, array $callbacks = [])
    {
        $this->class = $class;
        $this->cache = $cache;

        $this->orm = $orm;
        $this->data = $data;

        //Magic functionality provided by outer parent
        $this->callbacks = $callbacks;
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
     * @return RecordEntity[]
     */
    public function all()
    {
        $result = [];

        /**
         * @var self|RecordEntity[] $iterator
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
     * @return RecordEntity
     * @see ORM::record()
     * @see Record::setContext()
     * @throws ORMException
     */
    public function current()
    {
        $data = $this->data[$this->position];
        if (isset($this->instances[$this->position])) {
            //Due record was pre-constructed we must update it's context to force values for relations
            //and pivot fields
            return $this->instances[$this->position]->setContext($data);
        }

        //Let's ask ORM to create needed record
        return $this->instances[$this->position] = $this->orm->record(
            $this->class,
            $data,
            $this->cache
        );
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->position++;
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
     * @param RecordEntity|string|int $record
     * @return true
     */
    public function has($record)
    {
        /**
         * @var self|RecordEntity[] $iterator
         */
        $iterator = clone $this;
        foreach ($iterator as $nested) {
            $found = false;
            if (is_array($record)) {
                if (array_intersect_assoc($nested->getFields(), $record) == $record) {
                    //Comparing fields intersection
                    $found = true;
                }
            } elseif (!$record instanceof RecordEntity) {

                if (!empty($record) && $nested->primaryKey() == $record) {
                    //Comparing using primary keys
                    $found = true;
                }
            } elseif ($nested == $record || $nested->getFields() == $record->getFields()) {
                //Comparing as class
                $found = true;
            }

            if ($found) {
                //They all must be iterated already
                $this->instances = $iterator->instances;

                return true;
            }
        }

        //They all must be iterated already
        $this->instances = $iterator->instances;

        return false;
    }

    /**
     * Executes decorated method providing itself as function argument.
     *
     * @param string $method
     * @param array  $arguments
     * @return mixed
     * @throws IteratorException
     */
    public function __call($method, array $arguments)
    {
        if (!isset($this->callbacks[$method])) {
            throw new IteratorException("Undefined method or callback.");
        }

        return call_user_func($this->callbacks[$method], $this);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->all();
    }

    /**
     * @return RecordEntity[]
     */
    public function __debugInfo()
    {
        return $this->all();
    }
}