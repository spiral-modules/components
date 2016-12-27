<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Core\Component;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\MemoryInterface;
use Spiral\Database\DatabaseManager;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Schemas\SchemaBuilder;
use Spiral\ORM\Schemas\SchemaLocator;

class ORM extends Component implements ORMInterface, SingletonInterface
{
    /**
     * Memory section to store ORM schema.
     */
    const MEMORY = 'orm.schema';

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
     * @param DatabaseManager  $manager
     * @param SchemaLocator    $locator
     * @param MemoryInterface  $memory
     * @param FactoryInterface $factory
     */
    public function __construct(
        DatabaseManager $manager,
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
     * Specify behaviour schema for ORM to be used.
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
            throw new ORMException("Undefined ORM schema item '{$class}', make sure schema is updated");
        }

        if (!array_key_exists($property, $this->schema[$class])) {
            throw new ORMException("Undefined ORM schema property '{$class}'.'{$property}'");
        }

        return $this->schema[$class][$property];
    }

    //other methods

    /**
     * {@inheritdoc}
     */
    public function instantiate(
        string $class,
        $fields = [],
        bool $filter = true,
        bool $cache = false
    ) {
        //todo: cache
        return $this->instantiator($class)->instantiate($fields, $filter);
    }

    //todo: __clone

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
        $instantiator = $this->factory->make(
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

}