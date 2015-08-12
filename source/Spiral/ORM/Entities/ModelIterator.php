<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\ORM\Exceptions\ORMException;

/**
 *
 */
class ModelIterator implements \Iterator, \Countable, \JsonSerializable
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
     * Constructed model instances.
     *
     * @var Model[]
     */
    protected $instances = [];

    /**
     * @invisible
     * @var ORM
     */
    protected $orm = null;

    /**
     * @param ORM    $orm
     * @param string $class
     * @param array  $data
     * @param bool   $cache
     */
    public function __construct(ORM $orm, $class, array $data, $cache = true)
    {
        $this->class = $class;
        $this->cache = $cache;

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
     * Get all Models as array.
     *
     * @return Model[]
     */
    public function all()
    {
        $result = [];
        foreach ($this as $item) {
            $result[] = $item;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see ORM::model()
     * @see Model::setContext()
     * @throws ORMException
     */
    public function current()
    {
        $data = $this->data[$this->position];
        if (isset($this->instances[$this->position])) {
            //Due model was pre-constructed we must update it's context to force values for relations
            //and pivot fields
            return $this->instances[$this->position]->setContext($data);
        }

        //Let's ask ORM to create needed model
        return $this->instances[$this->position] = $this->orm->model(
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
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->all();
    }

    /**
     * @return Model[]
     */
    public function __debugInfo()
    {
        return $this->all();
    }
}