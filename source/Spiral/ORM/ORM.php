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
     * Memory section to store ORM schema.
     */
    const SCHEMA_SECTION = 'ormSchema';

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
    const M_NULLABLE    = 7;
    const M_MUTATORS    = 8;
    const M_VALIDATES   = 9;
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
     * Pivot table data location in Model fields. Pivot data only provided when model is loaded
     * using many-to-many relation.
     */
    const PIVOT_DATA = '@pivot';

    /**
     * In cases when ORM cache is enabled every constructed instance will be stored here, cache used
     * mainly to ensure the same instance of object, even if was accessed from different spots.
     * Cache usage increases memory consumption and does not decreases amount of queries being made.
     *
     * @var Model[]
     */
    private $entityCache = [];

    /**
     * Cached models schema.
     *
     * @var array|null
     */
    protected $schema = null;

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
        $this->schema = $memory->loadData(static::SCHEMA_SECTION);

        $this->dbal = $dbal;

        $this->memory = $memory;
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
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
            throw new ORMException("Undefined ORM schema item, unknown model '{$model}'.");
        }

        return $this->schema[$model];
    }

    /**
     * Construct instance of Model or receive it from cache (if enabled). Only models with declared
     * primary key can be cached.
     *
     * @param string $class Model class name.
     * @param array  $data
     * @param bool   $cache Add model to entity cache if enabled.
     * @return Model
     */
    public function model($class, array $data = [], $cache = true)
    {
        $schema = $this->getSchema($class);

        if (!$this->config['entityCache']['enabled'] || !$cache) {
            //Entity cache is disabled, we can create model right now
            return new $class($data, !empty($data), $this, $schema);
        }

        //We have to find unique object criteria (will work for objects with primary key only)
        $criteria = null;
        if (!empty($schema[self::M_PRIMARY_KEY]) && !empty($data[$schema[self::M_PRIMARY_KEY]])) {
            $criteria = $class . '.' . $data[$schema[self::M_PRIMARY_KEY]];
        }

        if (isset($this->entityCache[$criteria])) {
            //Retrieving model from the cache and updates it's context (relations and pivot data)
            return $this->entityCache[$criteria]->setContext($data);
        }

        return $this->registerEntity(new $class($data, !empty($data), $this, $schema));
    }

    /**
     * Create model relation instance by given relation type, parent and definition (options).
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
        if (!isset($this->config['relations'][$type]['schema'])) {
            throw new ORMException("Undefined relation loader '{$type}'.");
        }

        $class = $this->config['relations'][$type]['loader'];

        return new $class($this, $container, $definition, $parent);
    }

    /**
     * Update ORM models schema, synchronize declared and database schemas and return instance of
     * SchemaBuilder.
     *
     * @param SchemaBuilder $builder User specified schema builder.
     * @return SchemaBuilder
     */
    public function updateSchema(SchemaBuilder $builder = null)
    {
        $builder = !empty($builder) ? $builder : $this->schemaBuilder();

        //Casting relations between models
        $builder->castRelations();

        //Create all required tables and columns
        $builder->synchronizeSchema();

        //Saving
        $this->memory->saveData(
            static::SCHEMA_SECTION,
            $this->schema = $builder->normalizeSchema()
        );

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
     * Create instance of relation schema based on relation type and given definition (declared in
     * model). Resolve using container to support any possible relation type. You can create your
     * own relations, loaders and schemas by altering ORM config.
     *
     * @param mixed         $type
     * @param SchemaBuilder $builder
     * @param ModelSchema   $model
     * @param string        $name
     * @param array         $definition
     * @return RelationSchemaInterface
     */
    public function relationSchema(
        $type,
        SchemaBuilder $builder,
        ModelSchema $model,
        $name,
        array $definition
    ) {
        if (!isset($this->config['relations'][$type]['schema'])) {
            throw new ORMException("Undefined relation schema '{$type}'.");
        }

        return $this->container->get(
            $this->config['relations'][$type]['schema'],
            compact('builder', 'model', 'name', 'definition')
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
     * Add Model to entity cache (only if cache enabled). Primary key is required for caching.
     *
     * @param Model $model
     * @param bool  $ignoreLimit Cache overflow will be ignored.
     * @return Model
     */
    public function registerEntity(Model $model, $ignoreLimit = true)
    {
        if (empty($model->primaryKey()) || !$this->config['entityCache']['enabled']) {
            return $model;
        }

        if (!$ignoreLimit && count($this->entityCache) > $this->config['entityCache']['maxSize']) {
            //We are full
            return $model;
        }

        return $this->entityCache[get_class($model) . '.' . $model->primaryKey()] = $model;
    }

    /**
     * Remove Model model from entity cache. Primary key is required for caching.
     *
     * @param Model $model
     */
    public function removeEntity(Model $model)
    {
        if (empty($model->primaryKey())) {
            return;
        }

        unset($this->entityCache[get_class($model) . '.' . $model->primaryKey()]);
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->entityCache = [];
    }
}