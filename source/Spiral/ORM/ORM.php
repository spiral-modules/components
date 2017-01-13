<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Interop\Container\ContainerInterface;
use Spiral\Core\Component;
use Spiral\Core\Container;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\MemoryInterface;
use Spiral\Core\NullMemory;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Table;
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Schemas\LocatorInterface;
use Spiral\ORM\Schemas\NullLocator;
use Spiral\ORM\Schemas\SchemaBuilder;

class ORM extends Component implements ORMInterface, SingletonInterface
{
    /**
     * Memory section to store ORM schema.
     */
    const MEMORY = 'orm.schema';

    /**
     * @invisible
     * @var EntityCache|null
     */
    private $cache = null;

    /**
     * @var LocatorInterface
     */
    private $locator;

    /**
     * Already created instantiators.
     *
     * @invisible
     * @var InstantiatorInterface[]
     */
    private $instantiators = [];

    /**
     * ORM schema.
     *
     * @invisible
     * @var array
     */
    private $schema = [];

    /**
     * @var DatabaseManager
     */
    protected $manager;

    /**
     * @var RelationsConfig
     */
    protected $config;

    /**
     * @invisible
     * @var MemoryInterface
     */
    protected $memory;

    /**
     * Container defines working scope for all Documents and DocumentEntities.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param DatabaseManager         $manager
     * @param RelationsConfig         $config
     * @param LocatorInterface|null   $locator
     * @param EntityCache|null        $cache
     * @param MemoryInterface|null    $memory
     * @param ContainerInterface|null $container
     */
    public function __construct(
        DatabaseManager $manager,
        RelationsConfig $config,

        LocatorInterface $locator = null,
        EntityCache $cache = null,
        MemoryInterface $memory = null,
        ContainerInterface $container = null
    ) {
        $this->manager = $manager;
        $this->config = $config;

        //If null is passed = no caching is expected
        $this->cache = $cache;

        $this->locator = $locator ?? new NullLocator();
        $this->memory = $memory ?? new NullMemory();
        $this->container = $container ?? new Container();

        //Loading schema from memory (if any)
        $this->schema = $this->loadSchema();
    }

    /**
     * Create version of ORM with different initial cache or disabled cache.
     *
     * @param EntityCache|null $cache
     *
     * @return ORM
     */
    public function withCache(EntityCache $cache = null): ORM
    {
        $orm = clone $this;
        $orm->cache = $cache;

        return $orm;
    }

    /**
     * Check if ORM has associated entity cache.
     *
     * @return bool
     */
    public function hasCache(): bool
    {
        return !empty($this->cache);
    }

    /**
     * Create instance of ORM SchemaBuilder.
     *
     * @param bool $locate Set to true to automatically locate available records and record sources
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
        $builder = $this->getFactory()->make(SchemaBuilder::class, ['manager' => $this->manager]);

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
     * Specify behaviour schema for ORM to be used. Attention, you have to call renderSchema()
     * prior to passing builder into this method.
     *
     * @param SchemaBuilder $builder
     * @param bool          $remember Set to true to remember packed schema in memory.
     */
    public function buildSchema(SchemaBuilder $builder, bool $remember = false)
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
            $this->buildSchema($this->schemaBuilder()->renderSchema(), true);
        }

        //Check value
        if (!isset($this->schema[$class])) {
            throw new ORMException("Undefined ORM schema item '{$class}', make sure schema is updated");
        }

        if (!array_key_exists($property, $this->schema[$class])) {
            throw new ORMException("Undefined ORM schema property '{$class}'.'{$property}'");
        }

        return $this->schema[$class][$property];
    }

    /**
     * {@inheritdoc}
     */
    public function selector(string $class): RecordSelector
    {
        //ORM is cloned in order to isolate cache scope.
        return new RecordSelector($class, clone $this);
    }

    /**
     * {@inheritdoc}
     */
    public function table(string $class): Table
    {
        return $this->manager->database(
            $this->define($class, self::R_DATABASE)
        )->table(
            $this->define($class, self::R_TABLE)
        );
    }

    public function hasTransaction(): bool
    {

    }

    public function getTransaction(): TransactionInterface
    {

    }

    public function beginTransaction(): TransactionInterface
    {

    }

    /**
     * {@inheritdoc}
     */
    public function make(
        string $class,
        $fields = [],
        int $state = self::STATE_NEW,
        bool $cache = true
    ): RecordInterface {
        $instantiator = $this->instantiator($class);

        if ($state == self::STATE_NEW) {
            //No caching for entities created with user input
            $cache = false;
        }

        if (!$cache || !$this->hasCache()) {
            return $instantiator->make($fields, $state);
        }

        //Looking for an entity in a cache
        $identity = $instantiator->identify($fields);

        if (is_null($identity)) {
            //Unable to cache non identified instance
            return $instantiator->make($fields, $state);
        }

        if ($this->cache->has($class, $identity)) {
            return $this->cache->get($class, $identity);
        }

        //Storing entity in a cache right after creating it
        return $this->cache->remember(
            $class,
            $identity,
            $instantiator->make($fields, $state)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function makeLoader(string $class, string $relation): LoaderInterface
    {
        $schema = $this->define($class, self::R_RELATIONS);

        if (!isset($schema[$relation])) {
            throw new ORMException("Undefined relation '{$class}'.'{$relation}'");
        }

        $schema = $schema[$relation];

        if (!$this->config->hasRelation($schema[self::R_TYPE])) {
            throw new ORMException("Undefined relation type '{$schema[self::R_TYPE]}'");
        }

        //Generating relation
        return $this->getFactory()->make(
            $this->config->relationClass($schema[self::R_TYPE], RelationsConfig::LOADER_CLASS),
            [
                'class'    => $schema[self::R_CLASS],
                'relation' => $relation,
                'schema'   => $schema[self::R_SCHEMA],
                'orm'      => $this
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function makeRelation(string $class, string $relation): RelationInterface
    {
        $schema = $this->define($class, self::R_RELATIONS);

        if (!isset($schema[$relation])) {
            throw new ORMException("Undefined relation '{$class}'.'{$relation}'");
        }

        $schema = $schema[$relation];

        if (!$this->config->hasRelation($schema[self::R_TYPE], RelationsConfig::ACCESS_CLASS)) {
            throw new ORMException("Undefined relation type '{$schema[self::R_TYPE]}'");
        }

        //Generating relation
        return $this->getFactory()->make(
            $this->config->relationClass($schema[self::R_TYPE], RelationsConfig::ACCESS_CLASS),
            [
                'class'  => $schema[self::R_CLASS],
                'schema' => $schema[self::R_SCHEMA],
                'orm'    => $this
            ]
        );
    }

    /**
     * When ORM is cloned we are automatically cloning it's cache as well to create
     * new isolated area. Basically we have cache enabled per selection.
     *
     * @see RecordSelector::getIterator()
     */
    public function __clone()
    {
        //Each ORM clone must have isolated entity cache
        $this->cache = clone $this->cache;
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
        $instantiator = $this->getFactory()->make(
            $this->define($class, self::R_INSTANTIATOR),
            [
                'class'  => $class,
                'orm'    => $this,
                'schema' => $this->define($class, self::R_SCHEMA)
            ]
        );

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
     * Get ODM specific factory.
     *
     * @return FactoryInterface
     */
    protected function getFactory(): FactoryInterface
    {
        if ($this->container instanceof FactoryInterface) {
            return $this->container;
        }

        return $this->container->get(FactoryInterface::class);
    }
}
