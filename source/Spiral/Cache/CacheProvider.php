<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache;

use Spiral\Cache\Exceptions\CacheException;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\BenchmarkTrait;

/**
 * Default implementation of CacheInterface. Better fit for spiral.
 */
class CacheProvider extends Singleton implements CacheInterface, InjectorInterface
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
     * Due configuration is reverted we have to some weird things.
     *
     * @var array
     */
    private $optionsPull = [];

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
     */
    public function store($store = null, array $options = [])
    {
        $store = $store ?: $this->config['store'];
        if (isset($this->stores[$store])) {
            return $this->stores[$store];
        }

        //To be requested by storeOptions()
        $this->optionsPull[] = $options + $this->config['stores'][$store];

        $benchmark = $this->benchmark('store', $store);
        try {
            $this->stores[$store] = $this->container->construct(
                $this->config['stores'][$store]['class'],
                ['cache' => $this]
            );
        } finally {
            $this->benchmark($benchmark);
        }

        if ($store == $this->config['store'] && !$this->stores[$store]->isAvailable()) {
            throw new CacheException(
                "Unable to use default store '{$store}', driver is unavailable."
            );
        }

        return $this->stores[$store];
    }

    /**
     * Cache adapters support controllable injections, so we are giving them options from different
     * angle.
     *
     * @param string $adapter
     * @return array
     */
    public function storeOptions($adapter)
    {
        if (empty($this->optionsPull[$adapter])) {
            return $this->config['stores'][$adapter];
        }

        return array_shift($this->optionsPull);
    }

    /**
     * {@inheritdoc}
     *
     * @throws CacheException
     */
    public function createInjection(\ReflectionClass $class, $context)
    {
        if (!$class->isInstantiable()) {
            return $this->store();
        }

        $store = $this->container->construct($class->getName(), ['cache' => $this]);
        if (!$store->isAvailable()) {
            throw new CacheException(
                "Unable to use store '" . get_class($store) . "', driver is unavailable."
            );
        }

        return $store;
    }
}
