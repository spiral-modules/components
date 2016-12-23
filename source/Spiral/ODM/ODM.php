<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use MongoDB\Collection;
use Spiral\Core\Component;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\MemoryInterface;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;

/**
 * @todo move schema to external class?
 */
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

    //---

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

    //---

    public function source(string $class): DocumentSource
    {

    }

    /**
     * Get DocumentSelector for a given class. Attention, due model inheritance selector WILL be
     * associated with parent class.
     *
     * Example:
     * Admin extends User
     * $odm->selector(Admin::class)->getClass() == User::class
     *
     * @param string $class
     *
     * @return DocumentSelector
     */
    public function selector(string $class): DocumentSelector
    {
        return new DocumentSelector(
            $this->collection($class),
            $class, //todo: resolve parent class
            $this
        );
    }

    /**
     * {@inheritdoc}
     */
    public function collection(string $class): Collection
    {
        //do it
        return $this->manager->database('')->selectCollection('');
    }

    /**
     * {@inheritdoc}
     */
    public function instantiate(string $class, $fields = []): CompositableInterface
    {
        return $this->instantiator($class)->instantiate($fields);
    }

    /**
     * Get object responsible for class instantiation.
     *
     * @param string $class
     *
     * @return InstantiatorInterface
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
}