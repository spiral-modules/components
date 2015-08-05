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
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\Database;
use Spiral\Database\DatabaseProvider;
use Spiral\Events\Traits\EventsTrait;
use Spiral\ORM\Schemas\ModelSchema;
use Spiral\ORM\Schemas\RelationSchemaInterface;
use Spiral\ORM\Selector\LoaderInterface;
use Spiral\Core\Singleton;

class ORM extends Singleton
{
    /**
     * Required traits.
     */
    use ConfigurableTrait, EventsTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'orm';

    /**
     * Normalized entity constants.
     */
    const E_ROLE_NAME   = 0;
    const E_TABLE       = 1;
    const E_DB          = 2;
    const E_COLUMNS     = 3;
    const E_HIDDEN      = 4;
    const E_SECURED     = 5;
    const E_FILLABLE    = 6;
    const E_MUTATORS    = 7;
    const E_VALIDATES   = 8;
    const E_RELATIONS   = 9;
    const E_PRIMARY_KEY = 10;

    /**
     * Normalized relation options.
     */
    const R_TYPE       = 0;
    const R_TABLE      = 1;
    const R_DEFINITION = 2;

    /**
     * Pivot table location in ActiveRecord data.
     */
    const PIVOT_DATA = '@pivot';

    /**
     * Container instance.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Core component.
     *
     * @var HippocampusInterface
     */
    protected $runtime = null;

    /**
     * DatabaseManager.
     *
     * @var DatabaseProvider
     */
    protected $dbal = null;

    /**
     * Loaded entities schema. Schema contains full description about model behaviours, relations,
     * columns and etc.
     *
     * @var array|null
     */
    protected $schema = null;

    /**
     * In cases when ORM cache is enabled every constructed instance will be stored here, cache used
     * mainly to ensure the same instance of object, even if it's accessed from different spots.
     *
     * Cache will increase memory consumption.
     *
     * @var Model[]
     */
    protected $entityCache = [];

    /**
     * ORM component instance.
     *
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     * @param HippocampusInterface  $runtime
     * @param DatabaseProvider       $dbal
     */
    public function __construct(
        ConfiguratorInterface $configurator,
        ContainerInterface $container,
        HippocampusInterface $runtime,
        DatabaseProvider $dbal
    )
    {
        $this->config = $configurator->getConfig(static::CONFIG);

        $this->runtime = $runtime;
        $this->container = $container;
        $this->dbal = $dbal;
    }

    /**
     * Enable or disable entity cache.
     *
     * @param bool $enabled
     * @param int  $maxSize
     * @return $this
     */
    public function entityCache($enabled, $maxSize = null)
    {
        $this->config['entityCache']['enabled'] = (bool)$enabled;
        if (!empty($maxSize))
        {
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
     * Add ActiveRecord to entity cache (cache limit will be ignored).
     *
     * @param Model $record
     * @return Model
     */
    public function registerEntity(Model $record)
    {
        if (empty($record->primaryKey()) || !$this->config['entityCache']['enabled'])
        {
            return $record;
        }

        return $this->entityCache[get_class($record) . '.' . $record->primaryKey()] = $record;
    }

    /**
     * Remove ActiveRecord model from entity cache.
     *
     * @param Model $record
     */
    public function removeEntity(Model $record)
    {
        if (empty($record->primaryKey()) || !$this->config['entityCache']['enabled'])
        {
            return;
        }

        unset($this->entityCache[get_class($record) . '.' . $record->primaryKey()]);
    }

    /**
     * Get database by it's name from DBAL associated with ORM component.
     *
     * @param string $database
     * @return Database
     */
    public function getDatabase($database)
    {
        return $this->dbal->db($database);
    }

    /**
     * Get schema for specified document class or collection.
     *
     * @param string $item   Document class or collection name (including database).
     * @param bool   $update Automatically update schema if requested schema is missing.
     * @return mixed
     */
    public function getSchema($item, $update = true)
    {
        if ($this->schema === null)
        {
            $this->schema = $this->runtime->loadData('ormSchema');
        }

        if (!isset($this->schema[$item]) && $update)
        {
            $this->updateSchema();
        }

        return $this->schema[$item];
    }

    /**
     * Construct instance of ActiveRecord or receive it from cache (if enabled).
     *
     * @param string $class
     * @param array  $data
     * @param bool   $cache
     * @return Model
     */
    public function construct($class, array $data = [], $cache = true)
    {
        if (!$this->config['entityCache']['enabled'] || !$cache)
        {
            //Entity cache is disabled
            return new $class($data, !empty($data), $this);
        }

        //We have to find object criteria (will work for objects with primary key only)
        $criteria = null;
        if (
            !empty($this->schema[$class][self::E_PRIMARY_KEY])
            && !empty($data[$this->schema[$class][self::E_PRIMARY_KEY]])
        )
        {
            $criteria = $class . '.' . $data[$this->schema[$class][self::E_PRIMARY_KEY]];
        }

        if (isset($this->entityCache[$criteria]))
        {
            //Retrieving reconfigured model from the cache
            return $this->entityCache[$criteria]->setContext($data);
        }

        if (count($this->entityCache) > $this->config['entityCache']['maxSize'])
        {
            return new $class($data, !empty($data), $this);
        }

        return $this->entityCache[$criteria] = new $class($data, !empty($data), $this);
    }

    /**
     * Instance of ActiveRecord relation accessor.
     *
     * @param int          $type
     * @param Model $parent
     * @param array        $definition
     * @param array        $data
     * @param bool         $loaded
     * @return RelationInterface
     * @throws ORMException
     */
    public function relation($type, Model $parent, $definition, $data = null, $loaded = false)
    {
        if (!isset($this->config['relations'][$type]['class']))
        {
            throw new ORMException("Undefined relation type '{$type}'.");
        }

        $class = $this->config['relations'][$type]['class'];

        return new $class($this, $parent, $definition, $data, $loaded);
    }

    /**
     * Instance of relation schema with specified type.
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
    )
    {
        if (!isset($this->config['relations'][$type]['schema']))
        {
            throw new ORMException("Undefined relation schema '{$type}'.");
        }

        $class = $this->config['relations'][$type]['schema'];

        return new $class($schemaBuilder, $model, $name, $definition);
    }

    /**
     * Get instance of Loader associated with relation type and relation definition.
     *
     * @param int             $type       Relation type.
     * @param string          $container  Container related to parent loader.
     * @param array           $definition Relation definition.
     * @param LoaderInterface $parent     Parent loader (if presented).
     * @return LoaderInterface
     * @throws ORMException
     */
    public function relationLoader($type, $container, array $definition, LoaderInterface $parent = null)
    {
        if (!isset($this->config['relations'][$type]['schema']))
        {
            throw new ORMException("Undefined relation loader '{$type}'.");
        }

        $class = $this->config['relations'][$type]['loader'];

        return new $class($this, $container, $definition, $parent);
    }

    /**
     * Get ORM schema reader. Schema will detect all declared entities, their tables, columns,
     * relationships and etc.
     *
     * @return SchemaBuilder
     */
    public function schemaBuilder()
    {
        return $this->container->get(SchemaBuilder::class, [
            'config' => $this->config,
            'orm'    => $this
        ]);
    }

    /**
     * Refresh ORM schema state, will reindex all found active records. This is slow method using
     * Tokenizer, refreshSchema() should not be called by user request.
     *
     * @return SchemaBuilder
     */
    public function updateSchema()
    {
        $builder = $this->schemaBuilder();

        //Building database!
        $builder->executeSchema();

        $this->schema = $this->fire('schema', $builder->normalizeSchema());

        //We have to flush schema cache after schema update, just in case
        Model::resetInitiated();

        //Saving
        $this->runtime->saveData('ormSchema', $this->schema);

        return $builder;
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->entityCache = [];
    }
}