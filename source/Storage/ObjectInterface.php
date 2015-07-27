<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Storage;

use Spiral\Files\Streams\StreamableInterface;

interface ObjectInterface extends StreamableInterface
{
    /**
     * Storage objects used to represent one single file located at remote, local or cloud server,
     * such object provides basic set of API required to manager it location or retrieve file content.
     *
     * @param string           $address Full object address.
     * @param string           $name    Relative object name.
     * @param StorageInterface $storage Storage component.
     * @param BucketInterface  $bucket  Associated storage object.
     * @throws StorageException
     */
    public function __construct(
        $address,
        $name = '',
        StorageInterface $storage,
        BucketInterface $bucket = null
    );

    /**
     * Object name is relative name inside one specific container, can include filename and directory
     * name.
     *
     * @return string
     */
    public function getName();

    /**
     * Full object address. Address used to identify associated container using container prefix,
     * address can be either meaningless string or be valid URL, in this case object address can be
     * used as to detect container, as to show on web page.
     *
     * @return string
     */
    public function getAddress();

    /**
     * Associated storage container. Every container represent one "virtual" folder which can be
     * located on local machine, another server (ftp) or in cloud (amazon, rackspace). Container
     * provides basic unified functionality to manage files inside, all low level operations perform
     * by servers (adapters), this technique allows you to create application and code which does not
     * require to specify storage requirements at time of development.
     *
     * @return BucketInterface
     */
    public function getBucket();

    /**
     * Check if object exists in associated container. Method should never fail if file not exists
     * and will return bool in any condition.
     *
     * @return bool
     */
    public function exists();

    /**
     * Retrieve object size in bytes, should return false if object does not exists.
     *
     * @return int|bool
     */
    public function getSize();

    /**
     * Allocate local filename for remote storage object, if container represent remote location,
     * adapter should download file to temporary file and return it's filename. File is in readonly
     * mode, and in some cases will be erased on shutdown.
     *
     * @return string
     * @throws StorageException
     */
    public function localFilename();

    /**
     * Delete storage object from associated container. Method should not fail if object does not
     * exists.
     */
    public function delete();

    /**
     * Rename storage object without changing it's container. This operation does not require
     * object recreation or download and can be performed on remote server.
     *
     * @param string $newname New storage object name.
     * @return self
     * @throws StorageException
     */
    public function rename($newname);

    /**
     * Copy object to another internal (under same server) container, this operation may not
     * require file download and can be performed remotely.
     *
     * Method will return new instance of StorageObject associated with copied data.
     *
     * @param BucketInterface|string $destination Destination container (under same server).
     * @return self
     * @throws StorageException
     */
    public function copy($destination);

    /**
     * Replace object to another internal (under same server) container, this operation may not
     * require file download and can be performed remotely.
     *
     * @param BucketInterface|string $destination Destination container (under same server).
     * @return self
     * @throws StorageException
     */
    public function replace($destination);

    /**
     * Serialize storage object to string (full object address).
     *
     * @return string
     */
    public function __toString();
}