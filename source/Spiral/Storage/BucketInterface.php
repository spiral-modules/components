<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Storage;

use Psr\Http\Message\StreamInterface;
use Spiral\Files\FilesInterface;
use Spiral\Files\Streams\StreamableInterface;
use Spiral\Storage\Exceptions\BucketException;
use Spiral\Storage\Exceptions\ServerException;
use Spiral\Storage\Exceptions\StorageException;

/**
 * Abstraction level between remote storage and local filesystem. Provides set of generic file
 * operations.
 */
interface BucketInterface
{
    /**
     * @param string           $name
     * @param string           $prefix  Bucket prefix.
     * @param array            $options Server related options.
     * @param ServerInterface  $server
     * @param StorageInterface $storage
     * @param FilesInterface   $files
     */
    public function __construct(
        $name,
        $prefix,
        array $options,
        ServerInterface $server,
        StorageInterface $storage,
        FilesInterface $files
    );

    /**
     * Bucker name.
     *
     * @return string
     */
    public function getName();

    /**
     * Associated storage server instance.
     *
     * @return ServerInterface
     * @throws StorageException
     */
    public function server();

    /**
     * Get server specific bucket option or return default value.
     *
     * @param string $name
     * @param null   $default
     * @return mixed
     */
    public function getOption($name, $default = null);

    /**
     * Get bucket prefix.
     *
     * @return string
     */
    public function getPrefix();

    /**
     * Check if address be found in bucket namespace defined by bucket prefix.
     *
     * @param string $address
     * @return bool|int Should return matched address length.
     */
    public function hasAddress($address);

    /**
     * Build object address using object name and bucket prefix. While using URL like prefixes
     * address can appear valid URI which can be used directly at frontend.
     *
     * @param string $name
     * @return string
     */
    public function buildAddress($name);

    /**
     * Check if given name points to valid and existed location in bucket server.
     *
     * @param string $name
     * @return bool
     * @throws ServerException
     * @throws BucketException
     */
    public function exists($name);

    /**
     * Get object size or return false if object not found.
     *
     * @param string $name
     * @return int|bool
     * @throws ServerException
     * @throws BucketException
     */
    public function size($name);

    /**
     * Put given content under given name in associated bucket server. Must replace already existed
     * object.
     *
     * @param string                                     $name
     * @param string|StreamInterface|StreamableInterface $source
     * @return ObjectInterface
     * @throws ServerException
     * @throws BucketException
     */
    public function put($name, $source);

    /**
     * Must return filename which is valid in associated FilesInterface instance. Must trow an
     * exception if object does not exists. Filename can be temporary and should not be used
     * between sessions.
     *
     * @param string $name
     * @return string
     * @throws ServerException
     * @throws BucketException
     */
    public function allocateFilename($name);

    /**
     * Return PSR7 stream associated with bucket object content or trow and exception.
     *
     * @param string $name Storage object name.
     * @return StreamInterface
     * @throws ServerException
     * @throws BucketException
     */
    public function allocateStream($name);

    /**
     * Delete bucket object if it exists.
     *
     * @param string $name Storage object name.
     * @throws ServerException
     * @throws BucketException
     */
    public function delete($name);

    /**
     * Rename storage object without changing it's bucket. Must return new address on success.
     *
     * @param string $oldname
     * @param string $newname
     * @return string|bool
     * @throws StorageException
     * @throws ServerException
     * @throws BucketException
     */
    public function rename($oldname, $newname);

    /**
     * Copy storage object to another bucket. Method must return address which points to
     * new storage object.
     *
     * @param BucketInterface $destination
     * @param string          $name
     * @return string
     * @throws ServerException
     * @throws BucketException
     */
    public function copy(BucketInterface $destination, $name);

    /**
     * Move storage object data to another bucket. Method must return new object address on success.
     *
     * @todo Add ability to specify new name, not only destination.
     * @param BucketInterface $destination
     * @param string          $name
     * @return string
     * @throws ServerException
     * @throws BucketException
     */
    public function replace(BucketInterface $destination, $name);
}