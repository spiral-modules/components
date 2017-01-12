<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

use Spiral\Models\EntityInterface;
use Spiral\ORM\ORMInterface;

/**
 * Instantiates array of entities. At this moment implementation is rather simple.
 *
 * @todo upgrade to \IteratorIterator?
 * @todo pivot data
 */
class RecordIterator implements \IteratorAggregate
{
    /**
     * Array of entity data to be fed into instantiators.
     *
     * @var array
     */
    private $data = [];

    /**
     * @var EntityInterface[]
     */
    private $entities = [];

    /**
     * Class to be instantiated.
     *
     * @var string
     */
    private $class;

    /**
     * Responsible for entity construction.
     *
     * @invisible
     * @var ORMInterface
     */
    private $orm;

    /**
     * @param array        $data
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(array $data, string $class, ORMInterface $orm)
    {
        $this->data = $data;
        $this->class = $class;
        $this->orm = $orm;
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        //todo: think about it
        if (empty($this->entities)) {
            foreach ($this->data as $data) {
                /*
                 * Mass entity initialization.
                 */
                $this->entities[] = $entity = $this->orm->make(
                    $this->class,
                    $data,
                    ORMInterface::STATE_LOADED,
                    true
                );

                yield ['xxx'] => $entity;
            }
        }

        return new \ArrayIterator($this->entities);
    }
}