<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Storage;

use Spiral\Files\Streams\StreamableInterface;
use Spiral\Storage\Exceptions\BucketException;
use Spiral\Storage\Exceptions\ObjectException;
use Spiral\Storage\Exceptions\ServerException;

/**
 * Representation of a single storage object. Technically this is only helper interface, does not
 * contain any important logic rather than dedicating operations to container.
 */
interface ObjectInterface extends StreamableInterface
{
    /**
     * Get object name inside parent bucket.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get full object address.
     *
     * @return string
     */
    public function getAddress(): string;

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
     * @throws ObjectException
     */
    public function exists(): bool;

    /**
     * Get object size or return false of object does not exists.
     *
     * @return int|bool
     * @throws ObjectException
     */
    public function getSize();

    /**
     * Must return filename which is valid in associated FilesInterface instance. Must trow an
     * exception if object does not exists. Filename can be temporary and should not be used
     * between sessions. You must never write anything to this file.
     *
     * @return string
     * @throws ObjectException
     */
    public function localFilename(): string;

    /**
     * Delete object from associated bucket.
     *
     * @throws ObjectException
     */
    public function delete();

    /**
     * Rename storage object without changing it's bucket.
     *
     * @param string $newName
     *
     * @return self
     * @throws ObjectException
     */
    public function rename(string $newName): ObjectInterface;

    /**
     * Copy storage object to another bucket. Method must return ObjectInterface which points to
     * new storage object.
     *
     * @param BucketInterface|string $destination
     *
     * @return self
     * @throws ObjectException
     */
    public function copy($destination): ObjectInterface;

    /**
     * Move storage object data to another bucket.
     *
     * @param BucketInterface|string $destination
     *
     * @return self
     * @throws ObjectException
     */
    public function replace($destination): ObjectInterface;

    /**
     * Must be serialized into object address.
     *
     * @return string
     */
    public function __toString(): string;
}