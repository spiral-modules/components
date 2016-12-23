<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use Spiral\Core\Component;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\MemoryInterface;

class ODM extends Component implements ODMInterface, SingletonInterface
{
    /**
     * Memory section to store ODM schema.
     */
    const MEMORY = 'odm.schema';

    /**
     * Already created instantiators.
     *
     * @invisible
     * @var InstantiatorInterface[]
     */
    private $instantiators = [];

    /**
     * Set of classes responsible for document save operations.
     *
     * @invisible
     *
     * @var MapperInterface[]
     */
    private $mappers = [];

    /**
     * ODM schema.
     *
     * @invisible
     * @var array
     */
    private $schema = [];

    /**
     * @var MongoManager
     */
    protected $manager;

    /**
     * @invisible
     * @var MemoryInterface
     */
    protected $memory;

    /**
     * @var FactoryInterface
     */
    protected $factory;

    /**
     * @param MongoManager     $manager
     * @param MemoryInterface  $memory
     * @param FactoryInterface $factory
     */
    public function __construct(
        MongoManager $manager,
        MemoryInterface $memory,
        FactoryInterface $factory
    ) {
        $this->manager = $manager;
        $this->memory = $memory;
        $this->factory = $factory;

        //Loading schema from memory (if any)
        $this->schema = (array)$memory->loadData(static::MEMORY);
    }

    public function setSchema($s)
    {
        $this->schema = $s;
    }

    protected function loadSchema()
    {
    }

    protected function updateSchema()
    {

    }

    /**
     * Instantiate document/model instance based on a given class name and fieldset. Some ODM
     * documents might return instances of their child if fields point to child model schema.
     *
     * @param string $class
     * @param array  $fields
     *
     * @return CompositableInterface
     */
    public function instantiate(string $class, $fields = []): CompositableInterface
    {
        return $this->instantiator($class)->instantiate($fields);
    }

    public function selector(string $class)
    {
        //Selector!
        return new DocumentSelector();
    }

    public function mapper(string $class)
    {
        return new DocumentMapper();
    }

    /**
     * {@inheritdoc}
     */
    protected function instantiator(string $class): InstantiatorInterface
    {
        if (isset($this->instantiators[$class])) {
            return $this->instantiators[$class];
        }

        //Potential optimization
        $instantiator = $this->factory->make($this->schema[$class][self::D_INSTANTIATOR], [
            'class'  => $class,
            'odm'    => $this,
            'schema' => $this->schema[$class][self::D_SCHEMA]
        ]);

        //Constructing instantiator and storing it in cache
        return $this->instantiators[$class] = $instantiator;
    }

    /**
     * Get property from cached schema.
     *
     * @param string $class
     * @param int    $property See ODM constants.
     *
     * @return mixed
     */
    protected function schema(string $class, int $property)
    {
        return [];
    }
}