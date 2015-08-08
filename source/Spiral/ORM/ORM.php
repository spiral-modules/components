<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\DatabaseProvider;
use Spiral\Database\Entities\Database;
use Spiral\Models\DataEntity;
use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Entities\Schemas\ModelSchema;
use Spiral\ORM\Exceptions\ORMException;

/**
 * ORM component used to manage state of cached Model's schema, model creation and schema analysis.
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
     * Normalized model constants.
     */
    const M_ROLE_NAME   = 0;
    const M_TABLE       = 1;
    const M_DB          = 2;
    const M_COLUMNS     = 3;
    const M_HIDDEN      = 4;
    const M_SECURED     = 5;
    const M_FILLABLE    = 6;
    const M_MUTATORS    = 7;
    const M_VALIDATES   = 8;
    const M_RELATIONS   = 9;
    const M_PRIMARY_KEY = 10;

    /**
     * Normalized relation options.
     */
    const R_TYPE       = 0;
    const R_TABLE      = 1;
    const R_DEFINITION = 2;

    /**
     * Pivot table data location in Model fields.
     */
    const PIVOT_DATA = '@pivot';

    /**
     * Cached models schema.
     *
     * @var array|null
     */
    protected $schema = null;

    /**
     * In cases when ORM cache is enabled every constructed instance will be stored here, cache used mainly to ensure
     * the same instance of object, even if was accessed from different spots.
     * Cache usage increases memory consumption.
     *
     * @var Model[]
     */
    protected $entityCache = [];

    /**
     * @var DatabaseProvider
     */
    protected $dbal = null;

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
     * @param DatabaseProvider      $dbal
     */
    public function __construct(
        ConfiguratorInterface $configurator,
        ContainerInterface $container,
        HippocampusInterface $memory,
        DatabaseProvider $dbal
    ) {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->schema = $memory->loadData('ormSchema');

        $this->dbal = $dbal;

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
        return $this->dbal->db($database);
    }

    /**
     * Get cached schema for specified model by it's name.
     *
     * @param string $model
     * @return mixed
     * @throws ORMException
     */
    public function getSchema($model)
    {
        if (!isset($this->schema[$model])) {
            $this->updateSchema();
        }

        if (!isset($this->schema[$model])) {
            throw new ORMException("Undefined ORM schema item '{$model}'.");
        }

        return $this->schema[$model];
    }

    /**
     * Construct instance of Model or receive it from cache (if enabled). Only models with declared primary key can be
     * cached.
     *
     * @param string $class
     * @param array  $data
     * @param bool   $cache Add model to entity cache if enabled.
     * @return Model
     */
    public function model($class, array $data = [], $cache = true)
    {
        if (!$this->config['entityCache']['enabled'] || !$cache) {
            //Entity cache is disabled
            return new $class($data, !empty($data), $this);
        }

        $schema = $this->getSchema($class);

        //We have to find unique object criteria (will work for objects with primary key only)
        $criteria = null;
        if (!empty($schema[self::M_PRIMARY_KEY]) && !empty($data[$schema[self::M_PRIMARY_KEY]])) {
            $criteria = $class . '.' . $data[$schema[self::M_PRIMARY_KEY]];
        }

        if (empty($criteria) || count($this->entityCache) > $this->config['entityCache']['maxSize']) {
            //Entity cache is full or model does not have unique criteria
            return new $class($data, !empty($data), $this, $schema);
        }

        if (isset($this->entityCache[$criteria])) {
            //Retrieving reconfigured model from the cache
            return $this->entityCache[$criteria]->setContext($data);
        }

        return $this->entityCache[$criteria] = new $class($data, !empty($data), $this, $schema);
    }

    /**
     * Create model relation instance by given relation type, parent and definition.
     *
     * @param int   $type
     * @param Model $parent
     * @param array $definition Relation definition.
     * @param array $data
     * @param bool  $loaded
     * @return RelationInterface
     * @throws ORMException
     */
    public function relation($type, Model $parent, $definition, $data = null, $loaded = false)
    {
        if (!isset($this->config['relations'][$type]['class'])) {
            throw new ORMException("Undefined relation type '{$type}'.");
        }

        $class = $this->config['relations'][$type]['class'];

        return new $class($this, $parent, $definition, $data, $loaded);
    }

    /**
     * Get instance of relation/selection Loader associated based on relation type and definition.
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
        if (!isset($this->config['relations'][$type]['schema'])) {
            throw new ORMException("Undefined relation loader '{$type}'.");
        }

        $class = $this->config['relations'][$type]['loader'];

        return new $class($this, $container, $definition, $parent);
    }

    /**
     * Update ORM models schema and return instance of SchemaBuilder.
     *
     * @param SchemaBuilder $builder User specified schema builder.
     * @return SchemaBuilder
     */
    public function updateSchema(SchemaBuilder $builder = null)
    {
        $builder = !empty($builder) ? $builder : $this->schemaBuilder();

        //Create all required tables and columns
        $builder->executeSchema();

        //Saving
        $this->memory->saveData('ormSchema', $this->schema = $builder->normalizeSchema());

        //Let's reinitialize models
        DataEntity::resetInitiated();

        return $builder;
    }

    /**
     * Get instance of ORM SchemaBuilder.
     *
     * @return SchemaBuilder
     */
    public function schemaBuilder()
    {
        return $this->container->get(SchemaBuilder::class, [
            'config' => $this->config,
            'orm'    => $this,
        ]);
    }

    /**
     * Create instance of relation schema based on relation type and given definition. Resolved using container.
     *
     * @param mixed         $type
     * @param SchemaBuilder $schemaBuilder
     * @param ModelSchema   $model
     * @param string        $name
     * @param array         $definition
     * @return RelationSchemaInterface
     */
    public function relationSchema(
        $type,
        SchemaBuilder $schemaBuilder,
        ModelSchema $model,
        $name,
        array $definition
    ) {
        if (!isset($this->config['relations'][$type]['schema'])) {
            throw new ORMException("Undefined relation schema '{$type}'.");
        }

        $class = $this->config['relations'][$type]['schema'];

        return new $class($schemaBuilder, $model, $name, $definition);
    }

    /**
     * Enable or disable entity cache. Disabling cache will not flush it's values.
     *
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
     * Add Model to entity cache (cache limit will be ignored). Primary key is required for caching.
     *
     * @param Model $record
     * @return Model
     */
    public function registerEntity(Model $record)
    {
        if (empty($record->primaryKey()) || !$this->config['entityCache']['enabled']) {
            return $record;
        }

        return $this->entityCache[get_class($record) . '.' . $record->primaryKey()] = $record;
    }

    /**
     * Remove Model model from entity cache. Primary key is required for caching.
     *
     * @param Model $record
     */
    public function removeEntity(Model $record)
    {
        if (empty($record->primaryKey())) {
            return;
        }

        unset($this->entityCache[get_class($record) . '.' . $record->primaryKey()]);
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->entityCache = [];
    }
}