<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Psr\Log\LoggerInterface;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Exceptions\DBALException;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Helpers\SynchronizationBus;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\ORMInterface;

class SchemaBuilder
{
    /**
     * @var DatabaseManager
     */
    private $manager;

    /**
     * @var AbstractTable[]
     */
    private $tables = [];

    /**
     * @var SchemaInterface[]
     */
    private $schemas = [];

    /**
     * Class names of sources associated with specific class.
     *
     * @var array
     */
    private $sources = [];

    /**
     * @param DatabaseManager $manager
     */
    public function __construct(DatabaseManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Add new model schema into pool.
     *
     * @param SchemaInterface $schema
     *
     * @return self|$this
     */
    public function addSchema(SchemaInterface $schema): SchemaBuilder
    {
        $this->schemas[$schema->getClass()] = $schema;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function hasSchema(string $class): bool
    {
        return isset($this->schemas[$class]);
    }

    /**
     * @param string $class
     *
     * @return SchemaInterface
     *
     * @throws SchemaException
     */
    public function getSchema(string $class): SchemaInterface
    {
        if (!$this->hasSchema($class)) {
            throw new SchemaException("Unable to find schema for class '{$class}'");
        }

        return $this->schemas[$class];
    }

    /**
     * All available document schemas.
     *
     * @return SchemaInterface[]
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Associate source class with entity class. Source will be automatically associated with given
     * class and all classes from the same collection which extends it.
     *
     * @param string $class
     * @param string $source
     *
     * @return SchemaBuilder
     *
     * @throws SchemaException
     */
    public function addSource(string $class, string $source): SchemaBuilder
    {
        if (!$this->hasSchema($class)) {
            throw new SchemaException("Unable to add source to '{$class}', class is unknown to ORM");
        }

        $this->sources[$class] = $source;

        return $this;
    }

    /**
     * Check if given entity has associated source.
     *
     * @param string $class
     *
     * @return bool
     */
    public function hasSource(string $class): bool
    {
        return array_key_exists($class, $this->sources);
    }

    /**
     * Get source associated with specific class, if any.
     *
     * @param string $class
     *
     * @return string|null
     */
    public function getSource(string $class)
    {
        if (!$this->hasSource($class)) {
            return null;
        }

        return $this->sources[$class];
    }

    /**
     * Process all added schemas and relations in order to created needed tables, indexes and etc.
     * Attention, this method will return new instance of SchemaBuilder without affecting original
     * object. You MUST call this method before calling packSchema() method.
     *
     * Attention, this methods DOES NOT write anything into database, use pushSchema() to push
     * changes into database using automatic diff generation. You can also access list of
     * generated/changed tables via getTables() to create your own migrations.
     *
     * @see packSchema()
     * @see pushSchema()
     * @see getTables()
     *
     * @return SchemaBuilder
     */
    public function renderSchema(): SchemaBuilder
    {
        //bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla!

        //START YOUR WORK HERE!

        return $this;
    }

    /**
     * Get all defined tables, make sure to call renderSchema() first. Attention, all given tables
     * will be returned in detached state.
     *
     * @return AbstractTable[]
     *
     * @throws SchemaException
     */
    public function getTables(): array
    {
        if (empty($this->tables) && !empty($this->schemas)) {
            throw new SchemaException(
                "Unable to get tables, no defined tables were found, call defineTables() first"
            );
        }

        $result = [];
        foreach ($this->tables as $table) {
            //Detaching
            $result[] = clone $table;
        }

        return $result;
    }

    /**
     * Save every change made to generated tables. Method utilizes default DBAL diff mechanism,
     * use getTables() method in order to generate your own migrations.
     *
     * @param LoggerInterface|null $logger
     *
     * @throws SchemaException
     * @throws DBALException
     * @throws QueryException
     * @throws DriverException
     */
    public function pushSchema(LoggerInterface $logger = null)
    {
        $bus = new SynchronizationBus($this->getTables());
        $bus->run($logger);
    }

    /**
     * Pack declared schemas in a normalized form, make sure to call renderSchema() first.
     *
     * @return array
     *
     * @throws SchemaException
     */
    public function packSchema(): array
    {
        if (empty($this->tables) && !empty($this->schemas)) {
            throw new SchemaException(
                "Unable to pack schema, no defined tables were found, call defineTables() first"
            );
        }

        $result = [];
        foreach ($this->schemas as $class => $schema) {
            $result[$class][] = [
                ORMInterface::R_INSTANTIATOR => $schema->getInstantiator(),
                ORMInterface::R_SCHEMA       => $schema->packSchema($this, null),
                ORMInterface::R_SOURCE_CLASS => $this->getSource($class),
                ORMInterface::R_DATABASE     => $schema->getDatabase(),
                ORMInterface::R_TABLE        => $schema->getTable(),
                ORMInterface::R_RELATIONS    => [/*external manager*/]
                //relations???
            ];
        }

        return $result;
    }
}