<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Storage;

use GuzzleHttp\Exception\ServerException;
use Spiral\Files\Streams\StreamableInterface;
use Spiral\Storage\Exceptions\BucketException;
use Spiral\Storage\Exceptions\ObjectException;

/**
 * Representation of a single storage object.
 */
interface ObjectInterface extends StreamableInterface
{
    /**
     * @param string           $address Full object address.
     * @param StorageInterface $storage Storage component.
     * @throws ObjectException
     */
    public function __construct($address, StorageInterface $storage);

    /**
     * Get object name inside parent bucket.
     *
     * @return string
     */
    public function getName();

    /**
     * Get full object address.
     *
     * @return string
     */
    public function getAddress();

    /**
     * Get associated bucket instance.
     *
     * @return BucketInterface
     */
    public function getBucket();

    /**
     * Check if object exists.
     *
     * @return bool
     * @throws ServerException
     * @throws BucketException
     * @throws ObjectException
     */
    public function exists();

    /**
     * Get object size or return false of object does not exists.
     *
     * @return int|bool
     * @throws ServerException
     * @throws BucketException
     * @throws ObjectException
     */
    public function getSize();

    /**
     * Must return filename which is valid in associated FilesInterface instance. Must trow an
     * exception if object does not exists. Filename can be temporary and should not be used
     * between sessions.
     *
     * @return string
     * @throws ServerException
     * @throws BucketException
     * @throws ObjectException
     */
    public function localFilename();

    /**
     * Delete object from associated bucket.
     *
     * @throws ServerException
     * @throws BucketException
     * @throws ObjectException
     */
    public function delete();

    /**
     * Rename storage object without changing it's bucket.
     *
     * @param string $newname
     * @return self
     * @throws ServerException
     * @throws BucketException
     * @throws ObjectException
     */
    public function rename($newname);

    /**
     * Copy storage object to another bucket. Method must return ObjectInterface which points to
     * new storage object.
     *
     * @param BucketInterface|string $destination
     * @return self
     * @throws ServerException
     * @throws BucketException
     * @throws ObjectException
     */
    public function copy($destination);

    /**
     * Move storage object data to another bucket.
     *
     * @param BucketInterface|string $destination
     * @return self
     * @throws ServerException
     * @throws BucketException
     * @throws ObjectException
     */
    public function replace($destination);

    /**
     * Must be serialized into object address.
     *
     * @return string
     */
    public function __toString();
}