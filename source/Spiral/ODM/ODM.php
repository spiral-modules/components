<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use MongoDB\BSON\ObjectID;
use MongoDB\Collection;
use Spiral\Core\Component;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\MemoryInterface;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ODM\Exceptions\SchemaException;
use Spiral\ODM\Schemas\SchemaBuilder;
use Spiral\ODM\Schemas\SchemaLocator;

/**
 * Provides supporting functionality for ODM classes such as selectors, instantiators and schema
 * builders.
 *
 * @todo add ODM strict mode which must thrown an exception in AbstractArray and DocumentCompositor
 * @todo when multiple atomic operations applied to a field instead of forcing $set command.
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
     * @param bool $locate Set to true to automatically locate available documents and document
     *                     sources in a project files (based on tokenizer scope).
     *
     * @return SchemaBuilder
     *
     * @throws SchemaException
     */
    public function schemaBuilder(bool $locate = true): SchemaBuilder
    {
        /**
         * @var SchemaBuilder $builder
         */
        $builder = $this->factory->make(SchemaBuilder::class, ['manager' => $this->manager]);

        if ($locate) {
            foreach ($this->locator->locateSchemas() as $schema) {
                $builder->addSchema($schema);
            }

            foreach ($this->locator->locateSources() as $class => $source) {
                $builder->addSource($class, $source);
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
     * {@inheritdoc}
     */
    public function define(string $class, int $property)
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

    /**
     * Get source (selection repository) for specific entity class.
     *
     * @param string $class
     *
     * @return DocumentSource
     */
    public function source(string $class): DocumentSource
    {
        $source = $this->define($class, self::D_SOURCE_CLASS);

        if (empty($source)) {
            //Let's use default source
            $source = DocumentSource::class;
        }

        $handles = $source::DOCUMENT;
        if (empty($handles)) {
            //All sources are linked to primary class (i.e. Admin source => User class), unless specified
            //in source directly
            $handles = $this->define($class, self::D_PRIMARY_CLASS);
        }

        return $this->factory->make($source, [
            'class' => $handles,
            'odm'   => $this
        ]);
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
            $this->define($class, self::D_PRIMARY_CLASS),
            $this
        );
    }

    /**
     * {@inheritdoc}
     */
    public function collection(string $class): Collection
    {
        return $this->manager->database(
            $this->define($class, self::D_DATABASE)
        )->selectCollection(
            $this->define($class, self::D_COLLECTION)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function instantiate(
        string $class,
        $fields = [],
        bool $filter = true
    ): CompositableInterface {
        return $this->instantiator($class)->instantiate($fields, $filter);
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
        $instantiator = $this->factory->make($this->define($class, self::D_INSTANTIATOR), [
            'class'  => $class,
            'odm'    => $this,
            'schema' => $this->define($class, self::D_SCHEMA)
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

    /**
     * Create valid MongoId (ObjectID now) object based on string or id provided from client side.
     *
     * @param mixed $mongoID String or MongoId object.
     *
     * @return ObjectID|null
     */
    public static function mongoID($mongoID)
    {
        if (empty($mongoID)) {
            return null;
        }

        if (!is_object($mongoID)) {
            //Old versions of mongo api does not throws exception on invalid mongo id (1.2.1)
            if (!is_string($mongoID) || !preg_match('/[0-9a-f]{24}/', $mongoID)) {
                return null;
            }

            try {
                $mongoID = new ObjectID($mongoID);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $mongoID;
    }
}