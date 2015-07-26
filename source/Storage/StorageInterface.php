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

interface StorageInterface
{
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
    public function registerBucket($name, $prefix, $server, array $options = []);

    /**
     * Get storage bucket by it's name. Bucket should exist at that moment.
     *
     * @param string $bucket Bucket name or id.
     * @return BucketInterface
     * @throws StorageException
     */
    public function bucket($bucket);

    /**
     * Resolve bucket instance using object address, bucket will be detected by reading it's
     * own prefix from object address. Bucket with longest detected prefix will be used to represent
     * such object. Make sure you don't have prefix collisions.
     *
     * @param string $address Object address with prefix and name.
     * @param string $name    Object name fetched from address.
     * @return BucketInterface
     */
    public function locateBucket($address, &$name = null);

    /**
     * Create and retrieve server instance described in storage config.
     *
     * @param string $server  Server name or id.
     * @param array  $options Server options, required only it not defined in config.
     * @return ServerInterface
     * @throws StorageException
     */
    public function server($server, array $options = []);

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
    public function put($bucket, $name, $origin = '');

    /**
     * Create StorageObject based on provided address, object name and bucket will be detected
     * automatically using prefix encoded in address.
     *
     * @param string $address Object address with name and bucket prefix.
     * @return ObjectInterface
     */
    public function open($address);
}