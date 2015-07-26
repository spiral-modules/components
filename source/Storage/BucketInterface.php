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
use Spiral\Files\FilesInterface;
use Spiral\Files\Streams\StreamableInterface;

interface BucketInterface
{
    /**
     * Every bucket represent one "virtual" folder which can be located on local machine, another
     * server (ftp) or in cloud (amazon, rackspace). Bucket provides basic unified functionality
     * to manage files inside, all low level operations perform by servers (adapters), this technique
     * allows you to create application and code which does not require to specify storage requirements
     * at time of development.
     *
     * @param string           $server  Responsible server id or name.
     * @param string           $prefix  Addresses prefix.
     * @param array            $options Server related options.
     * @param StorageInterface $storage StorageManager component.
     * @param FilesInterface   $files   FileManager component.
     */
    public function __construct(
        $server,
        $prefix,
        array $options,
        StorageInterface $storage,
        FilesInterface $files
    );

    /**
     * Get server name or ID associated with bucket.
     *
     * @return string
     */
    public function getServerID();

    /**
     * Get server specific bucket option.
     *
     * @param string $name
     * @param null   $default
     * @return mixed
     */
    public function getOption($name, $default = null);

    /**
     * Get associated storage server. Every server represent one virtual storage which can be either
     * local, remove or cloud based. Every adapter should support basic set of low-level operations
     * (create, move, copy and etc). Adapter instance called server, one adapter can be used for
     * multiple servers.
     *
     * @return ServerInterface
     */
    public function getServer();

    /**
     * Get bucket prefix value.
     *
     * @return string
     */
    public function getPrefix();

    /**
     * Check if object with given address can be potentially located inside this bucket and return
     * prefix length.
     *
     * @param string $address Storage object address (including name and prefix).
     * @return bool|int
     */
    public function ownAddress($address);

    /**
     * Build object address using object name and bucket prefix. While using URL like prefixes
     * address can appear valid URI which can be used directly at frontend.
     *
     * @param string $name
     * @return string
     */
    public function buildAddress($name);

    /**
     * Check if given object (name) exists in current bucket. Method should never fail if file
     * not exists and will return bool in any condition.
     *
     * @param string $name Storage object name.
     * @return bool
     */
    public function exists($name);

    /**
     * Retrieve object size in bytes, should return false if object does not exists.
     *
     * @param string $name Storage object name.
     * @return int|bool
     */
    public function size($name);

    /**
     * Upload storage object using given filename or stream. Method can return false in case of failed
     * upload or thrown custom exception if needed.
     *
     * @param string                                     $name   Given storage object name.
     * @param string|StreamInterface|StreamableInterface $origin Local filename or stream to use for
     *                                                           creation.
     * @return ObjectInterface
     */
    public function put($name, $origin);

    /**
     * Allocate local filename for remote storage object, if bucket represent remote location,
     * adapter should download file to temporary file and return it's filename. File is in readonly
     * mode, and in some cases will be erased on shutdown.
     *
     * @param string $name Storage object name.
     * @return string
     * @throws StorageException
     */
    public function allocateFilename($name);

    /**
     * Get temporary read-only stream used to represent remote content. This method is very similar
     * to localFilename, however in some cases it may store data content in memory.
     *
     * @param string $name Storage object name.
     * @return StreamInterface
     * @throws StorageException
     */
    public function allocateStream($name);

    /**
     * Delete storage object from specified bucket. Method should not fail if object does not
     * exists.
     *
     * @param string $name Storage object name.
     */
    public function delete($name);

    /**
     * Rename storage object without changing it's bucket. This operation does not require
     * object recreation or download and can be performed on remote server.
     *
     * @param string $oldname Storage object name.
     * @param string $newname New storage object name.
     * @return bool
     * @throws StorageException
     */
    public function rename($oldname, $newname);

    /**
     * Copy object to another internal (under same server) bucket, this operation may not
     * require file download and can be performed remotely.
     *
     * Method will return new instance of StorageObject associated with copied data.
     *
     * @param BucketInterface $destination Destination bucket (under same server).
     * @param string          $name        Storage object name.
     * @return ObjectInterface
     * @throws StorageException
     */
    public function copy(BucketInterface $destination, $name);

    /**
     * Replace object to another internal (under same server) bucket, this operation may not
     * require file download and can be performed remotely.
     *
     * Method will return replaced storage object address.
     *
     * @param BucketInterface $destination Destination bucket (under same server).
     * @param string          $name        Storage object name.
     * @return string
     * @throws StorageException
     */
    public function replace(BucketInterface $destination, $name);
}