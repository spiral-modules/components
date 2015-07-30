<?php
/**
 * Spiral Framework, SpiralScout LLC.
 *
 * @package   spiralFramework
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2011
 */
namespace Spiral\Components\Storage\Servers;

use Psr\Http\Message\StreamInterface;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\StorageServer;

class LocalServer extends StorageServer
{
    /**
     * Check if given object (name) exists in specified container. Method should never fail if file
     * not exists and will return bool in any condition.
     *
     * @param BucketInterface $bucket Bucket instance associated with specific server.
     * @param string          $name   Storage object name.
     * @return bool
     */
    public function exists(BucketInterface $bucket, $name)
    {
        return $this->files->exists($this->getPath($bucket, $name));
    }

    /**
     * Retrieve object size in bytes, should return false if object does not exists.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return int|bool
     */
    public function size(BucketInterface $bucket, $name)
    {
        return $this->files->exists($this->getPath($bucket, $name))
            ? $this->files->size($this->getPath($bucket, $name))
            : false;
    }

    /**
     * Upload storage object using given filename or stream. Method can return false in case of failed
     * upload or thrown custom exception if needed.
     *
     * @param BucketInterface        $bucket Bucket instance.
     * @param string                 $name   Given storage object name.
     * @param string|StreamInterface $origin Local filename or stream to use for creation.
     * @return bool
     */
    public function put(BucketInterface $bucket, $name, $origin)
    {
        return $this->internalCopy(
            $bucket,
            $this->castFilename($origin),
            $this->getPath($bucket, $name)
        );
    }

    /**
     * Allocate local filename for remote storage object, if container represent remote location,
     * adapter should download file to temporary file and return it's filename. File is in readonly
     * mode, and in some cases will be erased on shutdown.
     *
     * Method should return false or thrown an exception if local filename can not be allocated.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return string|bool
     */
    public function allocateFilename(BucketInterface $bucket, $name)
    {
        return $this->files->exists($this->getPath($bucket, $name))
            ? $this->getPath($bucket, $name)
            : false;
    }

    /**
     * Get temporary read-only stream used to represent remote content. This method is very similar
     * to localFilename, however in some cases it may store data content in memory.
     *
     * Method should return false or thrown an exception if stream can not be allocated.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return StreamInterface|false
     */
    public function allocateStream(BucketInterface $bucket, $name)
    {
        if (!$this->exists($bucket, $name))
        {
            return false;
        }

        //Getting readonly stream
        return \GuzzleHttp\Psr7\stream_for(fopen($this->allocateFilename($bucket, $name), 'rb'));
    }

    /**
     * Delete storage object from specified container. Method should not fail if object does not
     * exists.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     */
    public function delete(BucketInterface $bucket, $name)
    {
        $this->files->delete($this->getPath($bucket, $name));
    }

    /**
     * Rename storage object without changing it's container. This operation does not require
     * object recreation or download and can be performed on remote server.
     *
     * Method should return false or thrown an exception if object can not be renamed.
     *
     * @param BucketInterface $bucket  Bucket instance.
     * @param string          $oldname Storage object name.
     * @param string          $newname New storage object name.
     * @return bool
     */
    public function rename(BucketInterface $bucket, $oldname, $newname)
    {
        return $this->internalMove(
            $bucket,
            $this->getPath($bucket, $oldname),
            $this->getPath($bucket, $newname)
        );
    }

    /**
     * Copy object to another internal (under same server) container, this operation may not
     * require file download and can be performed remotely.
     *
     * Method should return false or thrown an exception if object can not be copied.
     *
     * @param BucketInterface $bucket      Bucket instance.
     * @param BucketInterface $destination Destination bucket (under same server).
     * @param string          $name        Storage object name.
     * @return bool
     */
    public function copy(BucketInterface $bucket, BucketInterface $destination, $name)
    {
        return $this->internalCopy(
            $destination,
            $this->getPath($bucket, $name),
            $this->getPath($destination, $name)
        );
    }

    /**
     * Replace object to another internal (under same server) container, this operation may not
     * require file download and can be performed remotely.
     *
     * Method should return false or thrown an exception if object can not be replaced.
     *
     * @param BucketInterface $bucket      Bucket instance.
     * @param BucketInterface $destination Destination bucket (under same server).
     * @param string          $name        Storage object name.
     * @return bool
     */
    public function replace(BucketInterface $bucket, BucketInterface $destination, $name)
    {
        return $this->internalMove(
            $destination,
            $this->getPath($bucket, $name),
            $this->getPath($destination, $name)
        );
    }

    /**
     * Move helper, ensure target directory existence, file permissions and etc.
     *
     * @param BucketInterface $bucket      Bucket container.
     * @param string          $filename    Original filename.
     * @param string          $destination Destination filename.
     * @return bool
     */
    protected function internalMove(BucketInterface $bucket, $filename, $destination)
    {
        if (!$this->files->exists($filename))
        {
            return false;
        }

        $mode = $bucket->getOption('mode', FilesInterface::RUNTIME);
        $this->files->ensureLocation(dirname($destination), $mode);

        if (!$this->files->move($filename, $destination))
        {
            return false;
        }

        return $this->files->setPermissions($destination, $mode);
    }

    /**
     * Copy helper, ensure target directory existence, file permissions and etc.
     *
     * @param BucketInterface $bucket      Bucket container.
     * @param string          $filename    Original filename.
     * @param string          $destination Destination filename.
     * @return bool
     */
    protected function internalCopy(BucketInterface $bucket, $filename, $destination)
    {
        if (!$this->files->exists($filename))
        {
            return false;
        }

        $mode = $bucket->getOption('mode', FilesInterface::RUNTIME);
        $this->files->ensureLocation(dirname($destination), $mode);

        if (!$this->files->copy($filename, $destination))
        {
            return false;
        }

        return $this->files->setPermissions($destination, $mode);
    }

    /**
     * Get full file location on server including homedir.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return string
     */
    protected function getPath(BucketInterface $bucket, $name)
    {
        return $this->files->normalizePath(
            $this->options['home'] . '/' . $bucket->getOption('folder') . $name
        );
    }
}