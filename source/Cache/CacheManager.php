<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache;

use Spiral\Core;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Core\Singleton;
use Spiral\Core\Container\InjectorInterface;

class CacheManager extends Singleton implements CacheInterface, InjectorInterface
{
    /**
     * Some operations should be recorded.
     */
    use Core\Traits\ConfigurableTrait, BenchmarkTrait;

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
     * @var Core\ContainerInterface
     */
    protected $container = null;

    /**
     * Constructing CacheManager and selecting default adapter.
     *
     * @param Core\ConfiguratorInterface $configurator
     * @param Core\ContainerInterface    $container
     */
    public function __construct(
        Core\ConfiguratorInterface $configurator,
        Core\ContainerInterface $container
    )
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
}
