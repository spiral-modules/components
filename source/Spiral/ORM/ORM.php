<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright �2009-2015
 */
namespace Spiral\ORM;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Database;
use Spiral\Models\DataEntity;
use Spiral\Models\IdentifiedInterface;
use Spiral\Models\SchematicEntity;
use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Entities\Schemas\RecordSchema;
use Spiral\ORM\Entities\Selector;
use Spiral\ORM\Entities\Source;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * ORM component used to manage state of cached Record's schema, record creation and schema
 * analysis.
 *
 * @todo Think about using views for complex queries? Using views for entities? ViewRecord?
 * @todo ability to merge multiple tables into one entity - like SearchEntity? Partial entities?
 */
class ORM extends Singleton
{
    /**
     * Schema building and cache management configuration.
     */
    use ConfigurableTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'orm';

    /**
     * Memory section to store ORM schema.
     */
    const SCHEMA_SECTION = 'ormSchema';

    /**
     * Normalized record constants.
     */
    const M_ROLE_NAME   = 0;
    const M_TABLE       = 1;
    const M_DB          = 2;
    const M_COLUMNS     = 3;
    const M_HIDDEN      = SchematicEntity::SH_HIDDEN;
    const M_SECURED     = SchematicEntity::SH_SECURED;
    const M_FILLABLE    = SchematicEntity::SH_FILLABLE;
    const M_MUTATORS    = SchematicEntity::SH_MUTATORS;
    const M_VALIDATES   = SchematicEntity::SH_VALIDATES;
    const M_NULLABLE    = 9;
    const M_RELATIONS   = 10;
    const M_PRIMARY_KEY = 11;

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
     * In cases when ORM cache is enabled every constructed instance will be stored here, cache used
     * mainly to ensure the same instance of object, even if was accessed from different spots.
     * Cache usage increases memory consumption and does not decreases amount of queries being made.
     *
     * @var RecordEntity[]
     */
    private $entityCache = [];

    /**
     * Cached records schema.
     *
     * @var array|null
     */
    protected $schema = null;

    /**
     * @var DatabaseManager
     */
    protected $databases = null;

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     * @param HippocampusInterface  $memory
     * @param DatabaseManager       $databases
     */
    public function __construct(
        ConfiguratorInterface $configurator,
        ContainerInterface $container,
        HippocampusInterface $memory,
        DatabaseManager $databases
    ) {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->schema = (array)$memory->loadData(static::SCHEMA_SECTION);

        $this->databases = $databases;

        $this->memory = $memory;
        $this->container = $container;
    }

    /**
     * Get database by it's name from DatabaseProvider associated with ORM component.
     *
     * @param string $database
     * @return Database
     */
    public function dbalDatabase($database)
    {
        return $this->databases->database($database);
    }

    /**
     * Get cached schema for specified record by it's name.
     *
     * @param string $record
     * @return array
     * @throws ORMException
     */
    public function getSchema($record)
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
        $schema = $this->getSchema($class);

        if (!$this->config['entityCache']['enabled'] || !$cache) {
            //Entity cache is disabled, we can create record right now
            return new $class($data, !empty($data), $this, $schema);
        }

        //We have to find unique object criteria (will work for objects with primary key only)
        $criteria = null;
        if (!empty($schema[self::M_PRIMARY_KEY]) && !empty($data[$schema[self::M_PRIMARY_KEY]])) {
            $criteria = $class . '.' . $data[$schema[self::M_PRIMARY_KEY]];
        }

        if (isset($this->entityCache[$criteria])) {
            //Retrieving record from the cache and updates it's context (relations and pivot data)
            return $this->entityCache[$criteria]->setContext($data);
        }

        return $this->registerEntity(new $class($data, !empty($data), $this, $schema));
    }

    /**
     * Get ORM Selector for given record.
     *
     * @param string $class
     * @return Selector
     * @throws ORMException
     */
    public function ormSelector($class)
    {
        return new Selector($this, $class);
    }

    /**
     * Get instance of ORM source associated with given model class.
     *
     * @param string $class
     * @return Source
     */
    public function recordSource($class)
    {

    }

    /**
     * Create record relation instance by given relation type, parent and definition (options).
     *
     * @param int          $type
     * @param RecordEntity $parent
     * @param array        $definition Relation definition.
     * @param array        $data
     * @param bool         $loaded
     * @return RelationInterface
     * @throws ORMException
     */
    public function relation(
        $type,
        RecordEntity $parent,
        $definition,
        $data = null,
        $loaded = false
    ) {
        if (!isset($this->config['relations'][$type]['class'])) {
            throw new ORMException("Undefined relation type '{$type}'.");
        }

        $class = $this->config['relations'][$type]['class'];

        return new $class($this, $parent, $definition, $data, $loaded);
    }

    /**
     * Get instance of relation/selection loader based on relation type and definition.
     *
     * @param int             $type       Relation type.
     * @param string          $container  Container related to parent loader.
     * @param array           $definition Relation definition.
     * @param LoaderInterface $parent     Parent loader (if presented).
     * @return LoaderInterface
     * @throws ORMException
     */
    public function loader($type, $container, array $definition, LoaderInterface $parent = null)
    {
        if (!isset($this->config['relations'][$type]['loader'])) {
            throw new ORMException("Undefined relation loader '{$type}'.");
        }

        $class = $this->config['relations'][$type]['loader'];

        return new $class($this, $container, $definition, $parent);
    }

    /**
     * Update ORM records schema, synchronize declared and database schemas and return instance of
     * SchemaBuilder.
     *
     * @param SchemaBuilder $builder User specified schema builder.
     * @return SchemaBuilder
     */
    public function updateSchema(SchemaBuilder $builder = null)
    {
        $builder = !empty($builder) ? $builder : $this->schemaBuilder();

        //Casting relations between records
        $builder->castRelations();

        //Create all required tables and columns
        $builder->synchronizeSchema();

        //Saving
        $this->memory->saveData(
            static::SCHEMA_SECTION,
            $this->schema = $builder->normalizeSchema()
        );

        //Let's reinitialize records
        DataEntity::resetInitiated();

        return $builder;
    }

    /**
     * Get instance of ORM SchemaBuilder.
     *
     * @param TokenizerInterface $tokenizer
     * @return SchemaBuilder
     */
    public function schemaBuilder(TokenizerInterface $tokenizer = null)
    {
        return $this->container->construct(SchemaBuilder::class, [
            'config'    => $this->config,
            'orm'       => $this,
            'tokenizer' => $tokenizer
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
        if (!isset($this->config['relations'][$type]['schema'])) {
            throw new ORMException("Undefined relation schema '{$type}'.");
        }

        return $this->container->construct(
            $this->config['relations'][$type]['schema'],
            compact('builder', 'record', 'name', 'definition')
        );
    }

    /**
     * Enable or disable entity cache. Disabling cache will not flush it's values.
     *
     * @see $entityCache
     * @param bool $enabled
     * @param int  $maxSize
     * @return $this
     */
    public function entityCache($enabled, $maxSize = null)
    {
        $this->config['entityCache']['enabled'] = (bool)$enabled;
        if (!empty($maxSize)) {
            $this->config['entityCache']['maxSize'] = $maxSize;
        }

        return $this;
    }

    /**
     * Flush content of entity cache.
     */
    public function flushCache()
    {
        $this->entityCache = [];
    }

    /**
     * Add Record to entity cache (only if cache enabled). Primary key is required for caching.
     *
     * @param IdentifiedInterface $entity
     * @param bool                $ignoreLimit Cache overflow will be ignored.
     * @return RecordEntity
     */
    public function registerEntity(IdentifiedInterface $entity, $ignoreLimit = true)
    {
        if (empty($entity->primaryKey()) || !$this->config['entityCache']['enabled']) {
            return $entity;
        }

        if (!$ignoreLimit && count($this->entityCache) > $this->config['entityCache']['maxSize']) {
            //We are full
            return $entity;
        }

        return $this->entityCache[get_class($entity) . '.' . $entity->primaryKey()] = $entity;
    }

    /**
     * Remove Record record from entity cache. Primary key is required for caching.
     *
     * @param IdentifiedInterface $entity
     */
    public function unregisterEntity(IdentifiedInterface $entity)
    {
        if (empty($entity->primaryKey())) {
            return;
        }

        unset($this->entityCache[get_class($entity) . '.' . $entity->primaryKey()]);
    }

    /**
     * Check if desired entity was already cached.
     *
     * @param string $class
     * @param mixed  $primaryKey
     * @return bool
     */
    public function hasEntity($class, $primaryKey)
    {
        return isset($this->entityCache[$class . '.' . $primaryKey]);
    }

    /**
     * Fetch entity from cache.
     *
     * @param string $class
     * @param mixed  $primaryKey
     * @return null|IdentifiedInterface
     */
    public function getEntity($class, $primaryKey)
    {
        if (empty($this->entityCache[$class . '.' . $primaryKey])) {
            return null;
        }

        return $this->entityCache[$class . '.' . $primaryKey];
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->entityCache = [];
    }
}