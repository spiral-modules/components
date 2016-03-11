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
    /*
     * Some operations can be slow.
     */
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
     * {@inheritdoc}
     */
    public function getStore($store = null)
    {
        //Default store class
        $store = !empty($store) ? $store : $this->config->defaultStore();

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
    public function store($store = null)
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
        return $this->getStore(
            $this->config->resolveStore($class)
        );
    }
}
