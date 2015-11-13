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
     */
    public function store($class = null)
    {
        //Default store class
        $class = $class ?: $this->config['store'];

        if (isset($this->stores[$class])) {
            return $this->stores[$class];
        }

        $benchmark = $this->benchmark('store', $class);
        try {

            //Constructing cache instance
            $this->stores[$class] = $this->container->construct(
                $class,
                $this->config['stores'][$class]
            );
        } finally {
            $this->benchmark($benchmark);
        }

        if ($class == $this->config['store'] && !$this->stores[$class]->isAvailable()) {
            throw new CacheException(
                "Unable to use default store '{$class}', driver is unavailable."
            );
        }

        return $this->stores[$class];
    }

    /**
     * {@inheritdoc}
     *
     * @throws CacheException
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        if (!$class->isInstantiable()) {
            //Default store
            return $this->store();
        }

        if (empty($this->config['stores'][$class->getName()])) {
            throw new CacheException(
                "Unable construct cache store '{$class}', no options found."
            );
        }

        return $this->store($class->getName());
    }
}
