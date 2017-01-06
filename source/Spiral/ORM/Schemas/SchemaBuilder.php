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
use Spiral\Database\Helpers\SynchronizationPool;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\DoubleReferenceException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Schemas\Definitions\RelationContext;

class SchemaBuilder
{
    /**
     * @var DatabaseManager
     */
    private $manager;

    /**
     * @var RelationManager
     */
    private $relations;

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
     * @param RelationManager $relations
     */
    public function __construct(DatabaseManager $manager, RelationManager $relations)
    {
        $this->manager = $manager;
        $this->relations = $relations;
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
     *
     * @throws SchemaException
     */
    public function renderSchema(): SchemaBuilder
    {
        //Declaring tables associated with records
        $this->declareTables();

        //Defining all relations declared by our schemas
        $this->declareRelations();

        //Inverse relations (if requested)
        $this->relations->inverseRelations();

        //Rendering needed columns, FKs and indexes needed for our relations (if relation is ORM specific)

        //todo: implement this magic piece of code (do not forget to provide schema builder)
        dump($this->relations);

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
                "Unable to get tables, no tables are were found, call renderSchema() first"
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
     * Indication that tables in database require syncing before being matched with ORM models.
     *
     * @return bool
     */
    public function hasChanges(): bool
    {
        foreach ($this->getTables() as $table) {
            if ($table->getComparator()->hasChanges()) {
                return true;
            }
        }

        return false;
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
        $bus = new SynchronizationPool($this->getTables());
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
                "Unable to pack schema, no defined tables were found, call renderSchema() first"
            );
        }

        $result = [];
        foreach ($this->schemas as $class => $schema) {
            //Table which is being related to model schema
            $table = $this->requestTable($schema->getTable(), $schema->getDatabase(), false);

            $result[$class] = [
                ORMInterface::R_INSTANTIATOR => $schema->getInstantiator(),

                ORMInterface::R_ROLE_NAME => $schema->getRole(),

                //Schema includes list of fields, default values and nullable fields
                ORMInterface::R_SCHEMA    => $schema->packSchema($this, clone $table),

                ORMInterface::R_SOURCE_CLASS => $this->getSource($class),

                //Data location
                ORMInterface::R_DATABASE     => $schema->getDatabase(),
                ORMInterface::R_TABLE        => $schema->getTable(),

                //Pack model specific relations
                ORMInterface::R_RELATIONS    => $this->relations->packRelations($schema->getClass())
            ];
        }

        return $result;
    }

    /**
     * Walk thought schemas and define structure of associated tables.
     *
     * @throws SchemaException
     * @throws DBALException
     */
    protected function declareTables()
    {
        foreach ($this->schemas as $schema) {
            /**
             * Attention, this method will request table schema from DBAL and will empty it's state
             * so schema can define it from ground zero!
             */
            $table = $this->requestTable(
                $schema->getTable(),
                $schema->getDatabase(),
                true,
                true
            );

            //Render table schema
            $table = $schema->renderTable($table);

            //Working with indexes
            foreach ($schema->getIndexes() as $index) {
                $table->index($index->getColumns())->unique($index->isUnique());
                $table->index($index->getColumns())->setName($index->getName());
            }

            //And put it back :)
            $this->pushTable($table, $schema->getDatabase());
        }
    }

    /**
     * Walk thought all record schemas, fetch and declare needed relations using relation manager.
     *
     * @throws SchemaException
     * @throws DefinitionException
     */
    protected function declareRelations()
    {
        foreach ($this->schemas as $schema) {
            foreach ($schema->getRelations() as $name => $relation) {

                //Source context defines where relation comes from
                $sourceContext = RelationContext::createContent(
                    $schema,
                    $this->requestTable($schema->getTable(), $schema->getDatabase())
                );

                //Target context might only exist if relation points to another record in ORM,
                //in some cases it might point outside of ORM scope
                $targetContext = null;

                if ($this->hasSchema($relation->getTarget())) {
                    $target = $this->getSchema($relation->getTarget());

                    $targetContext = RelationContext::createContent(
                        $target,
                        $this->requestTable($target->getTable(), $target->getDatabase())
                    );
                }

                $this->relations->registerRelation(
                    $relation->withContext($sourceContext, $targetContext)
                );
            }
        }
    }

    /**
     * Request table schema by name/database combination.
     *
     * @param string      $table
     * @param string|null $database
     * @param bool        $resetState When set to true current table state will be reset in order
     *                                to allow model to redefine it's schema.
     * @param bool        $unique     Set to true (default), to throw an exception when table
     *                                already referenced by another model.
     *
     * @return AbstractTable          Unlinked.
     *
     * @throws DoubleReferenceException When two records refers to same table and unique option
     *                                  set.
     */
    protected function requestTable(
        string $table,
        string $database = null,
        bool $unique = false,
        bool $resetState = false
    ): AbstractTable {
        if (isset($this->tables[$database . '.' . $table])) {
            $schema = $this->tables[$database . '.' . $table];

            if ($unique) {
                throw new DoubleReferenceException(
                    "Table '{$table}' of '{$database} 'been requested by multiple models"
                );
            }
        } else {
            //Requesting thought DatabaseManager
            $schema = $this->manager->database($database)->table($table)->getSchema();
            $this->tables[$database . '.' . $table] = $schema;
        }

        $schema = clone $schema;

        if ($resetState) {
            //Emptying our current state (initial not affected)
            $schema->setState(null);
        }

        return $schema;
    }

    /**
     * Update table state.
     *
     * @param AbstractTable $table
     * @param string|null   $database
     */
    private function pushTable(AbstractTable $table, string $database = null)
    {
        $this->tables[$database . '.' . $table->getName()] = $table;
    }
}