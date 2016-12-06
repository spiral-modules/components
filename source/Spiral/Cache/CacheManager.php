<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache;

use Spiral\Cache\Configs\CacheConfig;
use Spiral\Cache\Exceptions\CacheException;
use Spiral\Core\Component;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Debug\Traits\BenchmarkTrait;

/**
 * Default implementation of CacheInterface. Better fit for spiral.
 */
class CacheManager extends Component implements SingletonInterface, CacheInterface, InjectorInterface
{
    use BenchmarkTrait;

    /**
     * Already constructed cache adapters.
     *
     * @var StoreInterface[]
     */
    private $stores = false;

    /**
     * @var CacheConfig
     */
    protected $config = null;

    /**
     * @invisible
     *
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @param CacheConfig      $config
     * @param FactoryInterface $factory
     */
    public function __construct(CacheConfig $config, FactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * Set custom implementation of store.
     *
     * @param string         $name
     * @param StoreInterface $store
     */
    public function setStore(string $name, StoreInterface $store)
    {
        if (!empty($this->stores[$name])) {
            throw new CacheException("Store '{$name}' is already created");
        }

        if (!$store->isAvailable()) {
            throw new CacheException("Unable to mount unavailable store '" . get_class($store) . "'");
        }

        $this->stores[$name] = $store;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore(string $store = null): StoreInterface
    {
        //Default store class
        $store = !empty($store) ? $store : $this->config->defaultStore();

        //We are allowing reference aliases for store names
        $store = $this->config->resolveAlias($store);

        if (isset($this->stores[$store])) {
            return $this->stores[$store];
        }

        $benchmark = $this->benchmark('store', $store);
        try {
            //Constructing cache instance
            $this->stores[$store] = $this->factory->make(
                $this->config->storeClass($store),
                $this->config->storeOptions($store)
            );
        } finally {
            $this->benchmark($benchmark);
        }

        if ($store == $this->config->defaultStore() && !$this->stores[$store]->isAvailable()) {
            throw new CacheException(
                "Unable to use default store '{$store}', driver is unavailable"
            );
        }

        return $this->stores[$store];
    }

    /**
     * Alias for getStore.
     *
     * @param string|null $store
     * @return StoreInterface
     */
    public function store(string $store = null): StoreInterface
    {
        return $this->getStore($store);
    }

    /**
     * {@inheritdoc}
     *
     * @throws CacheException
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        if ($class->isAbstract()) {
            //Default store
            return $this->store();
        }

        return $this->getStore(
            $this->config->resolveStore($class)
        );
    }
}
