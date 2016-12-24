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
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ODM\Schemas\SchemaBuilder;
use Spiral\ODM\Schemas\SchemaLocator;

/**
 * Provides supporting functionality for ODM classes such as selectors, instantiators and schema
 * builders.
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
     * @var SchemaLocator
     */
    protected $locator;

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
     * ODM constructor.
     *
     * @param MongoManager     $manager
     * @param SchemaLocator    $locator
     * @param MemoryInterface  $memory
     * @param FactoryInterface $factory
     */
    public function __construct(
        MongoManager $manager,
        SchemaLocator $locator,
        MemoryInterface $memory,
        FactoryInterface $factory
    ) {
        $this->manager = $manager;
        $this->locator = $locator;

        $this->memory = $memory;
        $this->factory = $factory;

        //Loading schema from memory (if any)
        $this->schema = $this->loadSchema();
    }

    /**
     * Update ODM schema with automatic indexations.
     *
     * @param bool $locate Set to true to automatically locate available documents in a project
     *                     (based on tokenizer scope).
     *
     * @return SchemaBuilder
     */
    public function schemaBuilder(bool $locate = true): SchemaBuilder
    {
        $builder = $this->factory->make(SchemaBuilder::class, ['manager' => $this->manager]);

        if ($locate) {
            foreach ($this->locator->locateSchemas() as $schema) {
                $builder->addSchema($schema);
            }
        }

        return $builder;
    }

    /**
     * Specify behaviour schema for ODM to be used.
     *
     * @param SchemaBuilder $builder
     * @param bool          $remember Set to true to remember packed schema in memory.
     */
    public function setSchema(SchemaBuilder $builder, bool $remember = false)
    {
        $this->schema = $builder->packSchema();

        if ($remember) {
            $this->memory->saveData(static::MEMORY, $this->schema);
        }
    }

    /**
     * Get property from cached schema. Attention, ODM will automatically load schema if it's empty.
     *
     * Example:
     * $odm->getSchema(User::class, ODM::D_INSTANTIATOR);
     *
     * @param string $class
     * @param int    $property See ODM constants.
     *
     * @return mixed
     *
     * @throws ODMException
     */
    public function schema(string $class, int $property)
    {
        if (empty($this->schema)) {
            //Update and remember
            $this->setSchema($this->schemaBuilder(), true);
        }

        //Check value
        if (!isset($this->schema[$class])) {
            throw new ODMException("Undefined ODM schema item '{$class}', make sure schema is updated");
        }

        if (!array_key_exists($property, $this->schema[$class])) {
            throw new ODMException("Undefined ODM schema property '{$class}'.'{$property}'");
        }

        return $this->schema[$class][$property];
    }

    //---

    public function source(string $class): DocumentSource
    {
        //todo: implement
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
            $this->schema($class, self::D_PRIMARY_CLASS),
            $this
        );
    }

    /**
     * {@inheritdoc}
     */
    public function collection(string $class): Collection
    {
        return $this->manager->database(
            $this->schema($class, self::D_DATABASE)
        )->selectCollection(
            $this->schema($class, self::D_COLLECTION)
        );
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
        $instantiator = $this->factory->make($this->schema($class, self::D_INSTANTIATOR), [
            'class'  => $class,
            'odm'    => $this,
            'schema' => $this->schema($class, self::D_SCHEMA)
        ]);

        //Constructing instantiator and storing it in cache
        return $this->instantiators[$class] = $instantiator;
    }

    /**
     * Load packed schema from memory.
     *
     * @return array
     */
    protected function loadSchema(): array
    {
        return (array)$this->memory->loadData(static::MEMORY);
    }
}