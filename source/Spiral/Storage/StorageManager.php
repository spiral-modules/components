<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Storage;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\Entities\StorageObject;
use Spiral\Storage\Exceptions\StorageException;

/**
 * Default implementation of StorageInterface.
 */
class StorageManager extends Singleton implements StorageInterface, InjectorInterface
{
    /**
     * Runtime configuration editing + some logging.
     */
    use ConfigurableTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'storage';

    /**
     * @var BucketInterface[]
     */
    private $buckets = [];

    /**
     * @var ServerInterface[]
     */
    private $servers = [];

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

        //Loading buckets
        foreach ($this->config['buckets'] as $name => $bucket) {
            //Using default implementation
            $this->buckets[$name] = $this->container->construct(StorageBucket::class, [
                    'storage' => $this
                ] + $bucket
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerBucket($name, $prefix, $server, array $options = [])
    {
        if (isset($this->buckets[$name])) {
            throw new StorageException("Unable to create bucket '{$name}', name already taken.");
        }

        //Controllable injection implemented
        return $this->buckets[$name] = $this->container->construct(StorageBucket::class, [
                'storage' => $this
            ] + compact('prefix', 'server', 'options')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function bucket($bucket)
    {
        if (empty($bucket)) {
            throw new StorageException("Unable to fetch bucket, name can not be empty.");
        }

        if (isset($this->buckets[$bucket])) {
            return $this->buckets[$bucket];
        }

        throw new StorageException("Unable to fetch bucket '{$bucket}', no presets found.");
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, \ReflectionParameter $parameter)
    {
        return $this->bucket($parameter->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function locateBucket($address, &$name = null)
    {
        /**
         * @var BucketInterface $bestBucket
         */
        $bestBucket = null;
        foreach ($this->buckets as $bucket) {
            if (!empty($prefixLength = $bucket->hasAddress($address))) {
                if (empty($bestBucket) || strlen($bestBucket->getPrefix()) < $prefixLength) {
                    $bestBucket = $bucket;
                    $name = substr($address, $prefixLength);
                }
            }
        }

        return $bestBucket;
    }

    /**
     * {@inheritdoc}
     */
    public function server($server, array $options = [])
    {
        if (isset($this->servers[$server])) {
            return $this->servers[$server];
        }

        if (!empty($options)) {
            $this->config['servers'][$server] = $options;
        }

        if (!array_key_exists($server, $this->config['servers'])) {
            throw new StorageException("Undefined storage server '{$server}'.");
        }

        $config = $this->config['servers'][$server];

        return $this->servers[$server] = $this->container->construct($config['class'], $config);
    }

    /**
     * {@inheritdoc}
     */
    public function put($bucket, $name, $source = '')
    {
        $bucket = is_string($bucket) ? $this->bucket($bucket) : $bucket;

        return $bucket->put($name, $source);
    }

    /**
     * {@inheritdoc}
     */
    public function open($address)
    {
        return new StorageObject($address, $this);
    }
}
