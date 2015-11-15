<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Cache;

use Spiral\Cache\Config\CacheConfig;
use Spiral\Cache\Exceptions\CacheException;
use Spiral\Core\Component;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Debug\Traits\BenchmarkTrait;

/**
 * Default implementation of CacheInterface. Better fit for spiral.
 */
class CacheManager extends Component implements SingletonInterface, CacheInterface, InjectorInterface
{
    /**
     * Some operations can be slow.
     */
    use BenchmarkTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Already constructed cache adapters.
     *
     * @var CacheStore[]
     */
    private $stores = false;

    /**
     * @var CacheConfig
     */
    protected $config = null;

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param CacheConfig        $config
     * @param ContainerInterface $container
     */
    public function __construct(CacheConfig $config, ContainerInterface $container)
    {
        $this->config = $config;
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
                $this->config->storeOptions($class)
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

        if (!$this->config->hasStore($class->getName())) {
            throw new CacheException(
                "Unable construct cache store '{$class}', no options found."
            );
        }

        return $this->store($class->getName());
    }
}
