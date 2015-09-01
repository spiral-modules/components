<?php
/**
 * Spiral Framework, SpiralScout LLC.
 *
 * @package   spiralFramework
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2011
 */
namespace Spiral\Storage\Servers;

use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exceptions\ServerException;
use Spiral\Storage\StorageServer;

/**
 * Provides abstraction level to work with data located in local filesystem.
 */
class LocalServer extends StorageServer
{
    /**
     * {@inheritdoc}
     */
    public function exists(BucketInterface $bucket, $name)
    {
        return $this->files->exists($this->getPath($bucket, $name));
    }

    /**
     * {@inheritdoc}
     */
    public function size(BucketInterface $bucket, $name)
    {
        return $this->files->exists($this->getPath($bucket, $name))
            ? $this->files->size($this->getPath($bucket, $name))
            : false;
    }

    /**
     * {@inheritdoc}
     */
    public function put(BucketInterface $bucket, $name, $source)
    {
        return $this->internalCopy(
            $bucket,
            $this->castFilename($source),
            $this->getPath($bucket, $name)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function allocateFilename(BucketInterface $bucket, $name)
    {
        if (!$this->exists($bucket, $name)) {
            throw new ServerException(
                "Unable to create local filename for '{$name}', object does not exists."
            );
        }

        return $this->getPath($bucket, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream(BucketInterface $bucket, $name)
    {
        if (!$this->exists($bucket, $name)) {
            throw new ServerException(
                "Unable to create stream for '{$name}', object does not exists."
            );
        }

        //Getting readonly stream
        return \GuzzleHttp\Psr7\stream_for(fopen($this->allocateFilename($bucket, $name), 'rb'));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(BucketInterface $bucket, $name)
    {
        $this->files->delete($this->getPath($bucket, $name));
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * @param BucketInterface $bucket
     * @param string          $filename
     * @param string          $destination
     * @return bool
     * @throws ServerException
     */
    protected function internalMove(BucketInterface $bucket, $filename, $destination)
    {
        if (!$this->files->exists($filename)) {
            throw new ServerException("Unable to move '{$filename}', object does not exists.");
        }

        $mode = $bucket->getOption('mode', FilesInterface::RUNTIME);
        $this->files->ensureLocation(dirname($destination), $mode);

        if (!$this->files->move($filename, $destination)) {
            throw new ServerException("Unable to move '{$filename}' to '{$destination}'.");
        }

        return $this->files->setPermissions($destination, $mode);
    }

    /**
     * Copy helper, ensure target directory existence, file permissions and etc.
     *
     * @param BucketInterface $bucket
     * @param string          $filename
     * @param string          $destination
     * @return bool
     * @throws ServerException
     */
    protected function internalCopy(BucketInterface $bucket, $filename, $destination)
    {
        if (!$this->files->exists($filename)) {
            throw new ServerException("Unable to copy '{$filename}', object does not exists.");
        }

        $mode = $bucket->getOption('mode', FilesInterface::RUNTIME);
        $this->files->ensureLocation(dirname($destination), $mode);

        if (!$this->files->copy($filename, $destination)) {
            throw new ServerException("Unable to copy '{$filename}' to '{$destination}'.");
        }

        return $this->files->setPermissions($destination, $mode);
    }

    /**
     * Get full file location on server including homedir.
     *
     * @param BucketInterface $bucket
     * @param string          $name
     * @return string
     */
    protected function getPath(BucketInterface $bucket, $name)
    {
        if (empty($this->options['home'])) {
            return $this->files->normalizePath($bucket->getOption('directory') . $name);
        }

        return $this->files->normalizePath(
            $this->options['home'] . '/' . $bucket->getOption('directory') . $name
        );
    }
}