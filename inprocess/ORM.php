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

class ORM extends Component implements ORMInterface
{
    use LoggerTrait;

    /**
     * Memory section to store ORM schema.
     */
    const MEMORY = 'orm.schema';

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
     * @invisible
     *
     * @var DatabaseManager
     */
    protected $databases = null;

    /**
     * Cached records schema.
     *
     * @var array|null
     */
    protected $schema = null;

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
     * @param DatabaseManager      $databases
     * @param EntityCache          $cache
     * @param HippocampusInterface $memory
     * @param FactoryInterface     $factory
     */
    public function __construct(
        ORMConfig $config,
        DatabaseManager $databases,
        EntityCache $cache,
        HippocampusInterface $memory,
        FactoryInterface $factory
    ) {
        $this->config = $config;
        $this->databases = $databases;

        $this->cache = $cache;

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
    public function entityCache()
    {
        return $this->cache;
    }

    /**
     * {@inheritdoc}
     */
    public function database($database)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function schema($item, $property = null)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function record($class, array $data, $cache = true)
    {

    }

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
     * {@inheritdoc}
     */
    public function source($class)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function mapper($class)
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