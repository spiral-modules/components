<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Storage;

use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\Streams\StreamableInterface;
use Spiral\Core\Singleton;

class StorageManager extends Singleton implements
    StorageInterface,
    InjectorInterface,
    LoggerAwareInterface
{
    /**
     * Runtime configuration editing + some logging.
     */
    use ConfigurableTrait, LoggerTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Container instance.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * List of initiated storage buckets, every bucket represent one "virtual" folder which
     * can be located on local machine, another server (ftp) or in cloud (amazon, rackspace). Bucket
     * provides basic unified functionality to manage files inside, all low level operations perform
     * by servers (adapters), this technique allows you to create application and code which does not
     * require to specify storage requirements at time of
     * development.
     *
     * @var BucketInterface[]
     */
    protected $buckets = [];

    /**
     * Every server represent one virtual storage which can be either local, remove or cloud based.
     * Every adapter should support basic set of low-level operations (create, move, copy and etc).
     * Adapter instance called server, one adapter can be used for multiple servers.
     *
     * @var ServerInterface[]
     */
    protected $servers = [];

    /**
     * Initiate storage component to load all buckets and servers. Storage component commonly used
     * to manage files using "virtual folders" (bucket) while such "folder" can represent local,
     * remove or cloud file storage. This allows to write more universal scripts, support multiple
     * environments with different bucket settings and simplify application testing.
     *
     * Storage component is of component which almost did not changed for last 4 years but must be
     * updated later to follow latest specs.
     *
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $bucket
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $bucket)
    {
        $this->config = $configurator->getConfig($this);
        $this->container = $bucket;

        //Loading buckets
        foreach ($this->config['buckets'] as $name => $bucket)
        {
            //Controllable injection implemented
            $this->buckets[$name] = $this->container->get(StorageBucket::class, $bucket + [
                    'storage' => $this
                ]);
        }
    }

    /**
     * Create new real-time storage bucket with specified prefix, server and options. Bucket
     * prefix will be automatically attached to every object name inside that bucket to create
     * object address which has to be unique over every other bucket.
     *
     * @param string $name    Bucket name used to create or replace objects.
     * @param string $prefix  Prefix will be attached to object name to create unique address.
     * @param string $server  Server name.
     * @param array  $options Additional adapter specific options.
     * @return BucketInterface
     * @throws StorageException
     */
    public function registerBucket($name, $prefix, $server, array $options = [])
    {
        if (isset($this->buckets[$name]))
        {
            throw new StorageException("Unable to create bucket '{$name}', name already taken.");
        }

        $this->logger()->info(
            "New bucket '{name}' for server '{server}' registered using '{prefix}' prefix.",
            compact('name', 'prefix', 'server', 'options')
        );

        //Controllable injection implemented
        return $this->buckets[$name] = $this->container->get(StorageBucket::class, [
                'storage' => $this
            ] + compact('prefix', 'server', 'options')
        );
    }

    /**
     * Get storage bucket by it's name. Bucket should exist at that moment.
     *
     * @param string $bucket Bucket name or id.
     * @return BucketInterface
     * @throws StorageException
     */
    public function bucket($bucket)
    {
        if (empty($bucket))
        {
            throw new \InvalidArgumentException("Unable to fetch bucket, name can not be empty.");
        }

        if (isset($this->buckets[$bucket]))
        {
            return $this->buckets[$bucket];
        }

        throw new StorageException("Unable to fetch bucket '{$bucket}', no presets found.");
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
        return $this->bucket($parameter->getName());
    }

    /**
     * Resolve bucket instance using object address, bucket will be detected by reading it's
     * own prefix from object address. Bucket with longest detected prefix will be used to represent
     * such object. Make sure you don't have prefix collisions.
     *
     * @param string $address Object address with prefix and name.
     * @param string $name    Object name fetched from address.
     * @return BucketInterface
     */
    public function locateBucket($address, &$name = null)
    {
        /**
         * @var BucketInterface $bestBucket
         */
        $bestBucket = null;

        foreach ($this->buckets as $bucket)
        {
            if ($prefixLength = $bucket->ownAddress($address))
            {
                if (empty($bestBucket) || strlen($bestBucket->getPrefix()) < $prefixLength)
                {
                    $bestBucket = $bucket;
                    $name = substr($address, $prefixLength);
                }
            }
        }

        return $bestBucket;
    }

    /**
     * Create and retrieve server instance described in storage config.
     *
     * @param string $server  Server name or id.
     * @param array  $options Server options, required only it not defined in config.
     * @return ServerInterface
     * @throws StorageException
     */
    public function server($server, array $options = [])
    {
        if (isset($this->servers[$server]))
        {
            return $this->servers[$server];
        }

        if (!empty($options))
        {
            $this->config['servers'][$server] = $options;
        }

        if (!array_key_exists($server, $this->config['servers']))
        {
            throw new StorageException("Undefined storage server '{$server}'.");
        }

        $config = $this->config['servers'][$server];

        return $this->servers[$server] = $this->container->get($config['class'], $config);
    }

    /**
     * Create new storage object (or update existed) with specified bucket, object can be created
     * as empty, using local filename, via Stream or using UploadedFile.
     *
     * While object creation original filename, name (no extension) or extension can be embedded to
     * new object name using string interpolation ({name}.{ext}}
     *
     * Example (using Facades):
     * Storage::create('cloud', $id . '-{name}.{ext}', $filename);
     * Storage::create('cloud', $id . '-upload-{filename}', $filename);
     *
     * @param string|BucketInterface                     $bucket    Bucket name, id or instance.
     * @param string                                     $name      Object name should be used in
     *                                                              bucket.
     * @param string|StreamInterface|StreamableInterface $origin    Local filename or Stream.
     * @return ObjectInterface|bool
     */
    public function put($bucket, $name, $origin = '')
    {
        $bucket = is_string($bucket) ? $this->bucket($bucket) : $bucket;

        if (!empty($origin) && is_string($origin))
        {
            $extension = strtolower(pathinfo($origin, PATHINFO_EXTENSION));
            $name = \Spiral\interpolate($name, [
                'ext'       => $extension,
                'name'      => substr(basename($origin), 0, -1 * (strlen($extension) + 1)),
                'filename'  => basename($origin),
                'extension' => $extension
            ]);
        }

        return $bucket->put($name, $origin);
    }

    /**
     * Create StorageObject based on provided address, object name and bucket will be detected
     * automatically using prefix encoded in address.
     *
     * @param string $address Object address with name and bucket prefix.
     * @return ObjectInterface
     */
    public function open($address)
    {
        return new StorageObject($address, '', $this);
    }
}