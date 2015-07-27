<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Core\Singleton;
use Spiral\Core\Container\InjectorInterface;

class CacheManager extends Singleton implements CacheInterface, InjectorInterface
{
    /**
     * Some operations should be recorded.
     */
    use ConfigurableTrait, BenchmarkTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Already constructed cache adapters.
     *
     * @var CacheStore[]
     */
    protected $stores = false;

    /**
     * Associated container instance. Used to create cache stores.
     *
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Constructing CacheManager and selecting default adapter.
     *
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig($this);
        $this->container = $container;
    }

    /**
     * Adapter specified options.
     *
     * @param string $adapter
     * @return mixed
     */
    public function storeOptions($adapter)
    {
        return $this->config['stores'][$adapter];
    }

    /**
     * Will return specified or default cache adapter. This function will load cache adapter if it
     * was not initiated, or fetch it from memory.
     *
     * @param string $store   Keep null, empty or not specified to get default cache adapter.
     * @param array  $options Custom store options to set or replace.
     * @return StoreInterface
     * @throws CacheException
     */
    public function store($store = null, array $options = [])
    {
        $store = $store ?: $this->config['store'];

        if (isset($this->stores[$store]))
        {
            return $this->stores[$store];
        }

        if (!empty($options))
        {
            $this->config['stores'][$store] = $options;
        }

        $this->benchmark('store', $store);
        $this->stores[$store] = $this->container->get($this->config['stores'][$store]['class'], [
            'cache' => $this
        ]);
        $this->benchmark('store', $store);

        if ($store == $this->config['store'] && !$this->stores[$store]->isAvailable())
        {
            throw new CacheException(
                "Unable to use default store '{$store}', driver is unavailable."
            );
        }

        return $this->stores[$store];
    }

    /**
     * Injector will receive requested class or interface reflection and reflection linked
     * to parameter in constructor or method.
     *
     * This method can return pre-defined instance or create new one based on requested class. Parameter
     * reflection can be used for dynamic class constructing, for example it can define database name
     * or config section to be used to construct requested instance.
     *
     * @param \ReflectionClass     $class
     * @param \ReflectionParameter $parameter
     * @return mixed
     */
    public function createInjection(\ReflectionClass $class, \ReflectionParameter $parameter)
    {
        if (!$class->isInstantiable())
        {
            return $this->store();
        }

        return $this->container->get($class->getName(), ['cache' => $this]);
    }
}
