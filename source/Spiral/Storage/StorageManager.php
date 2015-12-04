<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Storage;

use Spiral\Core\Component;
use Spiral\Core\FactoryInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Storage\Configs\StorageConfig;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\Entities\StorageObject;
use Spiral\Storage\Exceptions\StorageException;

/**
 * Default implementation of StorageInterface.
 */
class StorageManager extends Component implements StorageInterface, InjectorInterface
{
    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * @var BucketInterface[]
     */
    private $buckets = [];

    /**
     * @var ServerInterface[]
     */
    private $servers = [];

    /**
     * @var StorageConfig
     */
    protected $config = null;

    /**
     * @invisible
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @param StorageConfig    $config
     * @param FactoryInterface $factory
     */
    public function __construct(StorageConfig $config, FactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;

        //Loading buckets
        foreach ($this->config->getBuckets() as $name => $bucket) {
            //Using default implementation
            $this->buckets[$name] = $this->factory->make(
                StorageBucket::class,
                ['storage' => $this] + $bucket
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

        return $this->buckets[$name] = $this->factory->make(
            StorageBucket::class,
            ['storage' => $this] + compact('prefix', 'server', 'options')
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
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        if (empty($context)) {
            throw new StorageException("Storage bucket can be requested without specified context.");
        }

        return $this->bucket($context);
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
    public function server($server)
    {
        if (isset($this->servers[$server])) {
            return $this->servers[$server];
        }

        if (!$this->config->hasServer($server)) {
            throw new StorageException("Undefined storage server '{$server}'.");
        }

        $config = $this->config->serverOptions($server);

        return $this->servers[$server] = $this->factory->make($config['class'], $config);
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
