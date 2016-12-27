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
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Schemas\SchemaBuilder;
use Spiral\ORM\Schemas\SchemaLocator;

class ORM extends Component implements /*ORMInterface,*/ SingletonInterface
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
        //$this->schema = $this->loadSchema();
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

//        if ($locate) {
//            foreach ($this->locator->locateSchemas() as $schema) {
//                $builder->addSchema($schema);
//            }
//
//            foreach ($this->locator->locateSources() as $class => $source) {
//                $builder->addSource($class, $source);
//            }
//        }

        return $builder;
    }
}