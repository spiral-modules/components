<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright �2009-2015
 */
namespace Spiral\ORM;

use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Database;
use Spiral\Models\DataEntity;
use Spiral\Models\SchematicEntity;
use Spiral\ORM\Configs\ORMConfig;
use Spiral\ORM\Entities\Loader;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Entities\Schemas\RecordSchema;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\Tokenizer\ClassLocatorInterface;

/**
 * ORM component used to manage state of cached Record's schema, record creation and schema
 * analysis.
 *
 * @todo Think about using views for complex queries? Using views for entities? ViewRecord?
 * @todo ability to merge multiple tables into one entity - like SearchEntity? Partial entities?
 */
class ORM extends EntityCache implements SingletonInterface
{
    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Memory section to store ORM schema.
     */
    const MEMORY = 'orm.schema';

    /**
     * Normalized record constants.
     */
    const M_ROLE_NAME   = 0;
    const M_SOURCE      = 1;
    const M_TABLE       = 2;
    const M_DB          = 3;
    const M_HIDDEN      = SchematicEntity::SH_HIDDEN;
    const M_SECURED     = SchematicEntity::SH_SECURED;
    const M_FILLABLE    = SchematicEntity::SH_FILLABLE;
    const M_MUTATORS    = SchematicEntity::SH_MUTATORS;
    const M_VALIDATES   = SchematicEntity::SH_VALIDATES;
    const M_COLUMNS     = 9;
    const M_NULLABLE    = 10;
    const M_RELATIONS   = 11;
    const M_PRIMARY_KEY = 12;

    /**
     * Normalized relation options.
     */
    const R_TYPE       = 0;
    const R_TABLE      = 1;
    const R_DEFINITION = 2;
    const R_DATABASE   = 3;

    /**
     * Pivot table data location in Record fields. Pivot data only provided when record is loaded
     * using many-to-many relation.
     */
    const PIVOT_DATA = '@pivot';

    /**
     * @var ORMConfig
     */
    protected $config = null;

    /**
     * Cached records schema.
     *
     * @var array|null
     */
    protected $schema = null;

    /**
     * @invisible
     * @var DatabaseManager
     */
    protected $databases = null;

    /**
     * @invisible
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @param     ORMConfig        $config
     * @param HippocampusInterface $memory
     * @param DatabaseManager      $databases
     * @param FactoryInterface     $factory
     */
    public function __construct(
        ORMConfig $config,
        HippocampusInterface $memory,
        DatabaseManager $databases,
        FactoryInterface $factory
    ) {
        $this->config = $config;
        $this->memory = $memory;

        //Loading scheme from memory
        $this->schema = (array)$memory->loadData(static::MEMORY);

        $this->databases = $databases;
        $this->factory = $factory;
    }

    /**
     * Get database by it's name from DatabaseProvider associated with ORM component.
     *
     * @param string $database
     * @return Database
     */
    public function database($database)
    {
        return $this->databases->database($database);
    }

    /**
     * Construct instance of Record or receive it from cache (if enabled). Only records with
     * declared primary key can be cached.
     *
     * @param string $class Record class name.
     * @param array  $data
     * @param bool   $cache Add record to entity cache if enabled.
     * @return RecordInterface
     */
    public function record($class, array $data = [], $cache = true)
    {
        $schema = $this->schema($class);

        if (!$this->cacheEnabled() || !$cache) {
            //Entity cache is disabled, we can create record right now
            return new $class($data, !empty($data), $this, $schema);
        }

        //We have to find unique object criteria (will work for objects with primary key only)
        $primaryKey = null;

        if (
            !empty($schema[self::M_PRIMARY_KEY])
            && !empty($data[$schema[self::M_PRIMARY_KEY]])
        ) {
            $primaryKey = $data[$schema[self::M_PRIMARY_KEY]];
        }

        if ($this->hasEntity($class, $primaryKey)) {
            /**
             * @var RecordInterface $entity
             */
            $entity = $this->getEntity($class, $primaryKey);

            //Retrieving record from the cache and updates it's context (relations and pivot data)
            return $entity->setContext($data);
        }

        return $this->rememberEntity(
            new $class($data, !empty($data), $this, $schema)
        );
    }

    /**
     * Get ORM source for given class.
     *
     * @param string $class
     * @return RecordSource
     * @throws ORMException
     */
    public function source($class)
    {
        $schema = $this->schema($class);
        if (empty($source = $schema[self::M_SOURCE])) {
            //Default source
            $source = RecordSource::class;
        }

        return new $source($class, $this);
    }

    /**
     * Get ORM selector for given class.
     *
     * @param string          $class
     * @param LoaderInterface $loader
     * @return RecordSelector
     */
    public function selector($class, LoaderInterface $loader = null)
    {
        return new RecordSelector($class, $this, $loader);
    }

    /**
     * Get cached schema for specified record by it's name.
     *
     * @param string $record
     * @return array
     * @throws ORMException
     */
    public function schema($record)
    {
        if (!isset($this->schema[$record])) {
            $this->updateSchema();
        }

        if (!isset($this->schema[$record])) {
            throw new ORMException("Undefined ORM schema item, unknown record '{$record}'.");
        }

        return $this->schema[$record];
    }

    /**
     * Create record relation instance by given relation type, parent and definition (options).
     *
     * @param int             $type
     * @param RecordInterface $parent
     * @param array           $definition Relation definition.
     * @param array           $data
     * @param bool            $loaded
     * @return RelationInterface
     * @throws ORMException
     */
    public function relation(
        $type,
        RecordInterface $parent,
        $definition,
        $data = null,
        $loaded = false
    ) {
        if (!$this->config->hasRelation($type, 'class')) {
            throw new ORMException("Undefined relation type '{$type}'.");
        }

        $class = $this->config->relationClass($type, 'class');

        //For performance reasons class constructed without container
        return new $class($this, $parent, $definition, $data, $loaded);
    }

    /**
     * Get instance of relation/selection loader based on relation type and definition.
     *
     * @param int    $type       Relation type.
     * @param string $container  Container related to parent loader.
     * @param array  $definition Relation definition.
     * @param Loader $parent     Parent loader (if presented).
     * @return LoaderInterface
     * @throws ORMException
     */
    public function loader($type, $container, array $definition, Loader $parent = null)
    {
        if (!$this->config->hasRelation($type, 'loader')) {
            throw new ORMException("Undefined relation loader '{$type}'.");
        }

        $class = $this->config->relationClass($type, 'loader');

        //For performance reasons class constructed without container
        return new $class($this, $container, $definition, $parent);
    }

    /**
     * Update ORM records schema, synchronize declared and database schemas and return instance of
     * SchemaBuilder.
     *
     * @param SchemaBuilder $builder    User specified schema builder.
     * @param bool          $syncronize Create all required tables and columns
     * @return SchemaBuilder
     */
    public function updateSchema(SchemaBuilder $builder = null, $syncronize = true)
    {
        if (empty($builder)) {
            $builder = $this->schemaBuilder();
        }

        //Create all required tables and columns
        if ($syncronize) {
            $builder->synchronizeSchema();
        }

        //Getting normalized (cached) version of schema
        $this->schema = $builder->normalizeSchema();

        //Saving
        $this->memory->saveData(static::MEMORY, $this->schema);

        //Let's reinitialize records
        DataEntity::resetInitiated();

        return $builder;
    }

    /**
     * Get instance of ORM SchemaBuilder.
     *
     * @param ClassLocatorInterface $locator
     * @return SchemaBuilder
     */
    public function schemaBuilder(ClassLocatorInterface $locator = null)
    {
        return $this->factory->make(SchemaBuilder::class, [
            'config'  => $this->config,
            'orm'     => $this,
            'locator' => $locator
        ]);
    }

    /**
     * Create instance of relation schema based on relation type and given definition (declared in
     * record). Resolve using container to support any possible relation type. You can create your
     * own relations, loaders and schemas by altering ORM config.
     *
     * @param mixed         $type
     * @param SchemaBuilder $builder
     * @param RecordSchema  $record
     * @param string        $name
     * @param array         $definition
     * @return Schemas\RelationInterface
     */
    public function relationSchema(
        $type,
        SchemaBuilder $builder,
        RecordSchema $record,
        $name,
        array $definition
    ) {
        if (!$this->config->hasRelation($type, 'schema')) {
            throw new ORMException("Undefined relation schema '{$type}'.");
        }

        //Getting needed relation schema builder
        return $this->factory->make(
            $this->config->relationClass($type, 'schema'),
            compact('builder', 'record', 'name', 'definition')
        );
    }
}