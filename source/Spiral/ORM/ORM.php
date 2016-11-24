<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM;

use Spiral\Core\Component;
use Spiral\Core\FactoryInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Database\DatabaseManager;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\ORM\Configs\ORMConfig;
use Spiral\ORM\Entities\EntityCache;
use Spiral\ORM\Entities\Loader;
use Spiral\ORM\Entities\RecordMapper;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\Exceptions\ORMException;

class ORM extends Component implements ORMInterface
{
    use LoggerTrait;

    /**
     * Memory section to store ORM schema.
     */
    const MEMORY = 'orm.schema';

    /**
     * Record mappers.
     *
     * @var RecordMapper[]
     */
    private $mappers = [];

    /**
     * @var EntityCache
     */
    private $cache = null;

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
     *
     * @var DatabaseManager
     */
    protected $dbal = null;

    /**
     * @invisible
     *
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @invisible
     *
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @param ORMConfig            $config
     * @param DatabaseManager      $dbal
     * @param EntityCache          $cache
     * @param HippocampusInterface $memory
     * @param FactoryInterface     $factory
     */
    public function __construct(
        ORMConfig $config,
        DatabaseManager $dbal,
        EntityCache $cache,
        HippocampusInterface $memory,
        FactoryInterface $factory
    ) {
        $this->config = $config;
        $this->cache = $cache;

        $this->dbal = $dbal;

        //ORM schema (cached)
        $this->memory = $memory;
        $this->schema = (array)$memory->loadData(static::MEMORY);

        $this->factory = $factory;
    }

    /**
     * @param EntityCache $cache
     * @return $this
     */
    public function setCache(EntityCache $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * @return EntityCache
     */
    public function cache()
    {
        return $this->cache;
    }

    /**
     * {@inheritdoc}
     */
    public function database($database)
    {
        return $this->dbal->database($database);
    }

    /**
     * {@inheritdoc}
     */
    public function schema($item, $property = null)
    {
        if (!isset($this->schema[$item])) {
            $this->updateSchema();
        }

        if (!isset($this->schema[$item])) {
            throw new ORMException("Undefined ORM schema item '{$item}'");
        }

        if (!empty($property)) {
            if (!array_key_exists($property, $this->schema[$item])) {
                throw new ORMException("Undefined schema property '{$property}' of '{$item}'");
            }

            return $this->schema[$item][$property];
        }

        return $this->schema[$item];
    }

    /**
     * {@inheritdoc}
     */
    public function record($class, array $data, $cache = true)
    {
        $schema = $this->schema($class);

        if (!$this->cache->isEnabled() || !$cache) {

            //Entity cache is disabled, we can create record right now
            return new $class($data, !empty($data), $this, $schema);
        }

        //We have to find unique object criteria (will work for objects with primary key only)
        $primaryKey = null;

        if (
            !empty($schema[self::M_PRIMARY_KEY]) && !empty($data[$schema[self::M_PRIMARY_KEY]])
        ) {
            $primaryKey = $data[$schema[self::M_PRIMARY_KEY]];
        }

        if ($this->cache->has($class, $primaryKey)) {
            /**
             * @var RecordInterface $entity
             */
            return $this->cache->get($class, $primaryKey);
        }

        return $this->cache->remember(new $class($data, !empty($data), $this, $schema));
    }

    /**
     * Set custom instance of source for a given ORM model.
     *
     * @param string       $class
     * @param RecordSource $source
     */
    public function setSource($class, RecordSource $source)
    {
        $this->mappers[$class] = $source;
    }

    /**
     * {@inheritdoc}
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
     * Set custom instance of mapper for a given ORM model.
     *
     * @param string       $class
     * @param RecordMapper $mapper
     */
    public function setMapper($class, RecordMapper $mapper)
    {
        $this->mappers[$class] = $mapper;
    }

    /**
     * {@inheritdoc}
     */
    public function mapper($class)
    {
        if (isset($this->mappers[$class])) {
            return $this->mappers[$class];
        }

        $mapper = $this->factory->make(RecordMapper::class, [
            'orm'   => $this,
            'class' => $class
        ]);

        return $this->mappers[$class] = $mapper;
    }





    //-------------

    /**
     * {@inheritdoc}
     */
    public function relation(
        $type,
        RecordInterface $parent,
        $definition,
        $data = null,
        $loaded = false
    ) {

    }

    /**
     * {@inheritdoc}
     */
    public function selector($class, Loader $loader = null)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function loader($type, $container, array $definition, Loader $parent = null)
    {

    }

    /**
     * When ORM is cloned we are automatically cloning it's cache as well to create
     * new isolated area. Basically we have cache enabled per selection.
     *
     * @see RecordSelector::getIterator()
     */
    public function __clone()
    {
        $this->cache = clone $this->cache;

        if (!$this->cache->isEnabled()) {
            $this->logger()->warning("ORM are cloned with disabled state");
        }
    }
}