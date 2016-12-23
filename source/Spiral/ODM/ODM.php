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
use Spiral\ODM\Entities\DocumentInstantiator;

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

    /**
     * {@inheritdoc}
     */
    public function instantiator(string $class): InstantiatorInterface
    {
        if (isset($this->instantiators[$class])) {
            return $this->instantiators[$class];
        }

        if (!isset($this->schema[$class])) {

        }

        if ($this->schema[$class][self::D_INSTANTIATOR] == DocumentInstantiator::class) {
            //Minor performance optimization
            //bla bla bla bla bla!!!!
        }

        //Constructing instantiator and storing it in cache
        return $this->instantiators[$class] = $this->factory->make(
            $this->schema[$class][self::D_INSTANTIATOR],
            [
                'class'  => $class,
                'odm'    => $this,
                'schema' => $this->schema[$class][self::D_SCHEMA]
            ]
        );
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

    }
}