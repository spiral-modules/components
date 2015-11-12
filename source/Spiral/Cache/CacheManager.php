<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Cache;

use Spiral\Cache\Exceptions\CacheException;
use Spiral\Core\Component;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\BenchmarkTrait;

/**
 * Default implementation of CacheInterface. Better fit for spiral.
 */
class CacheManager extends Component implements
    SingletonInterface,
    CacheInterface,
    InjectorInterface
{
    /**
     * Some operations can be slow.
     */
    use ConfigurableTrait, BenchmarkTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'cache';

    /**
     * Already constructed cache adapters.
     *
     * @var CacheStore[]
     */
    private $stores = false;

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $class (Store class to be used).
     */
    public function store($store = null, $class = null)
    {
        //Default store id
        $store = $store ?: $this->config['store'];

        $storeID = $store . ':' . $class;
        if (isset($this->stores[$storeID])) {
            return $this->stores[$storeID];
        }

        $benchmark = $this->benchmark('store', $storeID);
        try {
            $storeInstance = $this->stores[$storeID] = $this->container->construct(
                !empty($class) ? $class : $this->config['stores'][$store]['class'],
                $this->config['stores'][$store]
            );
        } finally {
            $this->benchmark($benchmark);
        }

        if ($store == $this->config['store'] && !$storeInstance->isAvailable()) {
            throw new CacheException(
                "Unable to use default store '{$store}', driver is unavailable."
            );
        }

        return $storeInstance;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CacheException
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        if (!$class->isInstantiable() || empty($class->getConstant('STORE'))) {
            //Default store
            return $this->store();
        }

        if (empty($this->config['stores'][$class->getConstant('STORE')])) {
            throw new CacheException(
                "Unable construct cache store '{$class}', no options found."
            );
        }

        //Store class tells us default options set
        $store = $this->store($class->getConstant('STORE'), $class->getName());

        if (!$store->isAvailable()) {
            throw new CacheException(
                "Unable to use store '" . get_class($store) . "', driver is unavailable."
            );
        }

        return $store;
    }
}
