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
use Spiral\Files\Streams\StreamableInterface;
use Spiral\Storage\Exceptions\BucketException;
use Spiral\Storage\Exceptions\ObjectException;
use Spiral\Storage\Exceptions\ServerException;
use Spiral\Storage\Exceptions\StorageException;

/**
 * Abstraction level to work with local and remote files represented using storage objects and
 * buckets.
 */
interface StorageInterface
{
    /**
     * Register new bucket using it's options, server id and prefix.
     *
     * @param string $name
     * @param string $prefix
     * @param string $server
     * @param array  $options
     * @return BucketInterface
     * @throws StorageException
     */
    public function registerBucket($name, $prefix, $server, array $options = []);

    /**
     * Get bucket by it's name.
     *
     * @param string $bucket
     * @return BucketInterface
     * @throws StorageException
     */
    public function bucket($bucket);

    /**
     * Find bucket instance using object address.
     *
     * @param string $address
     * @param string $name Name stripped from address.
     * @return BucketInterface
     * @throws StorageException
     */
    public function locateBucket($address, &$name = null);

    /**
     * Get or create instance of storage server.
     *
     * @param string $server
     * @param array  $options Used to create new instance.
     * @return ServerInterface
     * @throws StorageException
     */
    public function server($server, array $options = []);

    /**
     * Put object data into specified bucket under provided name. Should support filenames, PSR7
     * streams and streamable objects. Must create empty object if source empty.
     *
     * @param string|BucketInterface                    $bucket
     * @param string                                    $name
     * @param mixed|StreamInterface|StreamableInterface $source
     * @return ObjectInterface|bool
     * @throws StorageException
     * @throws BucketException
     * @throws ServerException
     */
    public function put($bucket, $name, $source = '');

    /**
     * Create instance of storage object using it's address.
     *
     * @param string $address
     * @return ObjectInterface
     * @throws StorageException
     * @throws ObjectException
     */
    public function open($address);
}