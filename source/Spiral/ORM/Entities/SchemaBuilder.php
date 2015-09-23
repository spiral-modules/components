<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities;

use Spiral\Core\Component;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Entities\Schemas\RecordSchema;
use Spiral\ORM\Exceptions\PassiveTableException;
use Spiral\ORM\Exceptions\RecordSchemaException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\Schemas\RelationInterface;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * Schema builder responsible for static analysis of existed ORM Records, their schemas,
 * validations, related tables, requested indexes and etc.
 */
class SchemaBuilder extends Component
{
    /**
     * Schema builder configuration includes mutators list and etc.
     */
    use ConfigurableTrait;

    /**
     * @var RecordSchema[]
     */
    private $records = [];

    /**
     * @var AbstractTable[]
     */
    private $tables = [];

    /**
     * @invisible
     * @var ORM
     */
    protected $orm = null;

    /**
     * @param ORM                $orm
     * @param array              $config
     * @param TokenizerInterface $tokenizer
     */
    public function __construct(ORM $orm, array $config, TokenizerInterface $tokenizer)
    {
        $this->config = $config;
        $this->orm = $orm;

        $this->locateRecords($tokenizer);
    }

    /**
     * @return ORM
     */
    public function getORM()
    {
        return $this->orm;
    }

    /**
     * Check if Record class known to schema builder.
     *
     * @param string $class
     * @return bool
     */
    public function hasRecord($class)
    {
        return isset($this->records[$class]);
    }

    /**
     * Instance of RecordSchema associated with given class name.
     *
     * @param string $class
     * @return RecordSchema
     * @throws SchemaException
     * @throws RecordSchemaException
     */
    public function record($class)
    {
        if ($class == RecordEntity::class || $class == Record::class) {
            //No need to remember schema for abstract Document
            return new RecordSchema($this, RecordEntity::class);
        }

        if (!isset($this->records[$class])) {
            throw new SchemaException("Unknown record class '{$class}'.");
        }

        return $this->records[$class];
    }

    /**
     * @return RecordSchema[]
     */
    public function getRecords()
    {
        return $this->records;
    }

    /**
     * Check if given table was declared by one of record or relation.
     *
     * @param string $database Table database.
     * @param string $table    Table name without prefix.
     * @return bool
     */
    public function hasTable($database, $table)
    {
        return isset($this->tables[$database . '/' . $table]);
    }

    /**
     * Request table schema. Every non empty table schema will be synchronized with it's databases
     * when executeSchema() method will be called.
     *
     * Attention, every declared table will be synced with database if their initiator allows such
     * operation.
     *
     * @param string $database Table database.
     * @param string $table    Table name without prefix.
     * @return AbstractTable
     */
    public function declareTable($database, $table)
    {
        $database = $this->resolveDatabase($database);
        if (isset($this->tables[$database . '/' . $table])) {
            return $this->tables[$database . '/' . $table];
        }

        $schema = $this->orm->dbalDatabase($database)->table($table)->schema();

        return $this->tables[$database . '/' . $table] = $schema;
    }

    /**
     * Get list of every declared table schema.
     *
     * @param bool $cascade Sort tables in order of their dependencies.
     * @return AbstractTable[]
     */
    public function getTables($cascade = true)
    {
        if (!$cascade) {
            return $this->tables;
        }

        $tables = $this->tables;
        uasort($tables, function (AbstractTable $tableA, AbstractTable $tableB) {
            if (in_array($tableA->getName(), $tableB->getDependencies())) {
                return true;
            }

            return count($tableB->getDependencies()) > count($tableA->getDependencies());
        });

        return array_reverse($tables);
    }

    /**
     * SchemaBuilder will request every located RecordSchema to declare it's relations. In addition
     * this methods will create inversed set of relations.
     *
     * @throws SchemaException
     * @throws RelationSchemaException
     * @throws RecordSchemaException
     */
    public function castRelations()
    {
        $inversedRelations = [];
        foreach ($this->records as $record) {
            if ($record->isAbstract()) {
                //Abstract records can not declare relations or tables
                continue;
            }

            $record->castRelations();

            foreach ($record->getRelations() as $relation) {
                if ($relation->isInversable()) {
                    //Relation can be automatically inversed
                    $inversedRelations[] = $relation;
                }
            }
        }

        /**
         * We have to perform inversion after every generic relation was defined. Sometimes records
         * can define inversed relation by themselves.
         *
         * @var RelationInterface $relation
         */
        foreach ($inversedRelations as $relation) {
            if ($relation->isInversable()) {
                //We have to check inversion again in case if relation name already taken
                $relation->inverseRelation();
            }
        }
    }

    /**
     * Perform schema reflection to database(s). All declared tables will created or altered. Only
     * tables linked to non abstract records and record with active schema parameter will be
     * executed.
     *
     * SchemaBuilder will not allow (SchemaException) to create or alter tables columns declared
     * by abstract or records with ACTIVE_SCHEMA constant set to false. ActiveSchema still can
     * declare foreign keys and indexes (most of relations automatically request index or foreign
     * key), but they are going to be ignored.
     *
     * Due principals of database schemas and ORM component logic no data or columns will ever be
     * removed from database. In addition column renaming will cause creation of another column.
     *
     * Use database migrations to solve more complex database questions. Or disable ACTIVE_SCHEMA
     * and live like normal people.
     *
     * @throws SchemaException
     * @throws \Spiral\Database\Exceptions\SchemaException
     * @throws \Spiral\Database\Exceptions\QueryException
     * @throws \Spiral\Database\Exceptions\DriverException
     * @throws PassiveTableException
     */
    public function synchronizeSchema()
    {
        //We must check for errors first
        $tables = $this->getTables(true);

        foreach ($tables as $table) {
            //We can only alter table columns if record allows us
            $record = $this->findRelatedRecord($table);

            if (!empty($record) && $record->isAbstract() || empty($table->getColumns())) {
                //Abstract tables might declare table schema, but we are going to ignore it
                continue;
            }

            if (!empty($record) && !$record->isActive()) {
                if (empty($table->alteredColumns())) {
                    //Some relations might declare foreign keys and indexes in passive tables,
                    //we are going to skip them all without any warning
                    continue;
                }

                throw new PassiveTableException($table, $record);
            }
        }

        //We need list of declared tables in order of
        foreach ($tables as $name => $table) {
            //We can only alter table columns if record allows us
            $record = $this->findRelatedRecord($table);

            if (!empty($record) && $record->isAbstract() || empty($table->getColumns())) {
                //Abstract tables might declare table schema, but we are going to ignore it
                continue;
            }

            /**
             * All ORM magic happens here. Check Database schemas to find more.
             */
            $table->save();
        }
    }

    /**
     * Resolve real database name using it's alias.
     *
     * @see DatabaseProvider
     * @param string|null $alias
     * @return string
     */
    public function resolveDatabase($alias)
    {
        return $this->orm->dbalDatabase($alias)->getName();
    }

    /**
     * Get all mutators associated with field type.
     *
     * @param string $type Field type.
     * @return array
     */
    public function getMutators($type)
    {
        return isset($this->config['mutators'][$type]) ? $this->config['mutators'][$type] : [];
    }

    /**
     * Get mutator alias if presented. Aliases used to simplify schema (accessors) definition.
     *
     * @param string $alias
     * @return string|array
     */
    public function mutatorAlias($alias)
    {
        if (!is_string($alias) || !isset($this->config['mutatorAliases'][$alias])) {
            return $alias;
        }

        return $this->config['mutatorAliases'][$alias];
    }

    /**
     * Normalize record schema in lighter structure to be saved in ORM component memory.
     *
     * @return array
     * @throws SchemaException
     */
    public function normalizeSchema()
    {
        $result = [];
        foreach ($this->records as $record) {
            if ($record->isAbstract()) {
                continue;
            }

            $schema = [
                ORM::M_ROLE_NAME   => $record->getRole(),
                ORM::M_TABLE       => $record->getTable(),
                ORM::M_DB          => $record->getDatabase(),
                ORM::M_PRIMARY_KEY => $record->getPrimaryKey(),
                ORM::M_COLUMNS     => $record->getDefaults(),
                ORM::M_HIDDEN      => $record->getHidden(),
                ORM::M_SECURED     => $record->getSecured(),
                ORM::M_FILLABLE    => $record->getFillable(),
                ORM::M_NULLABLE    => $record->getNullable(),
                ORM::M_MUTATORS    => $record->getMutators(),
                ORM::M_VALIDATES   => $record->getValidates(),
                ORM::M_RELATIONS   => $this->packRelations($record)
            ];

            ksort($schema);
            $result[$record->getName()] = $schema;
        }

        return $result;
    }

    /**
     * Create appropriate instance of RelationSchema based on it's definition provided by ORM Record
     * or manually. Due internal format first definition key will be stated as definition type and
     * key value as record/entity definition relates too.
     *
     * @param RecordSchema $record
     * @param string       $name
     * @param array        $definition
     * @return RelationInterface
     * @throws SchemaException
     */
    public function relationSchema(RecordSchema $record, $name, array $definition)
    {
        if (empty($definition)) {
            throw new SchemaException("Relation definition can not be empty.");
        }

        reset($definition);
        //Relation type must be provided as first in definition
        $type = key($definition);

        //We are letting ORM to resolve relation schema using container
        $relation = $this->orm->relationSchema($type, $this, $record, $name, $definition);

        if ($relation->hasEquivalent()) {
            //Some relations may declare equivalent relation to be used instead, used for Morphed
            //relations
            return $relation->createEquivalent();
        }

        return $relation;
    }

    /**
     * Locate every available Record class.
     *
     * @param TokenizerInterface $tokenizer
     * @throws SchemaException
     */
    protected function locateRecords(TokenizerInterface $tokenizer)
    {
        //Table names associated with records
        $sources = [];
        foreach ($tokenizer->getClasses(RecordEntity::class) as $class => $definition) {
            if ($class == RecordEntity::class) {
                continue;
            }

            $this->records[$class] = $record = new RecordSchema($this, $class);

            if (!$record->isAbstract()) {
                //See comment near exception
                continue;
            }

            //Record associated tableID (includes resolved database name)
            $sourceID = $record->getSourceID();
            if (isset($sources[$sourceID])) {
                //We are not allowing multiple records talk to same database, unless they one of them
                //is abstract
                throw new SchemaException(
                    "Record '{$record}' associated with "
                    . "same source table '{$sourceID}' as '{$sources[$sourceID]}'."
                );
            }

            $sources[$sourceID] = $record;
        }
    }

    /**
     * Find record related to given table. This operation is required to catch if some
     * relation/schema declared values in passive (no altering) table. Might return if no records
     * find (pivot or user specified tables).
     *
     * @param AbstractTable $table
     * @return RecordSchema|null
     */
    private function findRelatedRecord(AbstractTable $table)
    {
        foreach ($this->getRecords() as $record) {
            if ($record->tableSchema() === $table) {
                return $record;
            }
        }

        //No associated record were found
        return null;
    }

    /**
     * Normalize and pack every declared record relation schema.
     *
     * @param RecordSchema $record
     * @return array
     */
    private function packRelations(RecordSchema $record)
    {
        $result = [];
        foreach ($record->getRelations() as $name => $relation) {
            $result[$name] = $relation->normalizeSchema();
        }

        return $result;
    }
}