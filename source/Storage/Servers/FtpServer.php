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
use Spiral\Storage\StorageException;
use Spiral\Storage\StorageServer;

class FtpServer extends StorageServer
{
    /**
     * Server configuration, connection options, auth keys and certificates.
     *
     * @var array
     */
    protected $options = [
        'host'     => '',
        'port'     => 21,
        'timeout'  => 60,
        'login'    => '',
        'password' => '',
        'home'     => '/',
        'passive'  => true
    ];

    /**
     * FTP connection resource.
     *
     * @var resource
     */
    protected $connection = null;

    /**
     * Every server represent one virtual storage which can be either local, remote or cloud based.
     * Every server should support basic set of low-level operations (create, move, copy and etc).
     *
     * @param FilesInterface $files   File component.
     * @param array          $options Storage connection options.
     * @throws StorageException
     */
    public function __construct(FilesInterface $files, array $options)
    {
        parent::__construct($files, $options);

        if (!extension_loaded('ftp'))
        {
            throw new StorageException(
                "Unable to initialize ftp storage server, extension 'ftp' not found."
            );
        }

        $this->connect();
    }

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
        return ftp_size($this->connection, $this->getPath($bucket, $name)) != -1;
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
        if (($size = ftp_size($this->connection, $this->getPath($bucket, $name))) != -1)
        {
            return $size;
        }

        return false;
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
        $location = $this->ensureLocation($bucket, $name);
        if (!ftp_put($this->connection, $location, $this->castFilename($origin), FTP_BINARY))
        {
            return false;
        }

        return $this->refreshPermissions($bucket, $name);
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
        if (!$this->exists($bucket, $name))
        {
            return false;
        }

        //File should be removed after processing
        $tempFilename = $this->files->tempFilename($this->files->extension($name));

        return ftp_get($this->connection, $tempFilename, $this->getPath($bucket, $name), FTP_BINARY)
            ? $tempFilename
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
        if (!$filename = $this->allocateFilename($bucket, $name))
        {
            return false;
        }

        return \GuzzleHttp\Psr7\stream_for(fopen($filename, 'rb'));
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
        if ($this->exists($bucket, $name))
        {
            ftp_delete($this->connection, $this->getPath($bucket, $name));
        }
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
        if (!$this->exists($bucket, $oldname))
        {
            return false;
        }

        $location = $this->ensureLocation($bucket, $newname);
        if (!ftp_rename($this->connection, $this->getPath($bucket, $oldname), $location))
        {
            return false;
        }

        return $this->refreshPermissions($bucket, $newname);
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
        if (!$this->exists($bucket, $name))
        {
            return false;
        }

        $location = $this->ensureLocation($bucket, $name);
        if (!ftp_rename($this->connection, $this->getPath($bucket, $name), $location))
        {
            return false;
        }

        return $this->refreshPermissions($bucket, $name);
    }

    /**
     * Open FTP connection.
     *
     * @return bool
     * @throws StorageException
     */
    protected function connect()
    {
        $this->connection = ftp_connect(
            $this->options['host'],
            $this->options['port'],
            $this->options['timeout']
        );

        if (empty($this->connection))
        {
            throw new StorageException(
                "Unable to connect to remote FTP server '{$this->options['host']}'."
            );
        }

        if (!ftp_login($this->connection, $this->options['login'], $this->options['password']))
        {
            throw new StorageException(
                "Unable to connect to remote FTP server '{$this->options['host']}'."
            );
        }

        if (!ftp_pasv($this->connection, $this->options['passive']))
        {
            throw new StorageException(
                "Unable to set passive mode at remote FTP server '{$this->options['host']}'."
            );
        }

        return true;
    }

    /**
     * Ensure that target object directory exists and has right permissions.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return bool|string
     */
    protected function ensureLocation(BucketInterface $bucket, $name)
    {
        $directory = dirname($this->getPath($bucket, $name));
        $mode = $bucket->getOption('mode', FilesInterface::RUNTIME);

        try
        {
            if (ftp_chdir($this->connection, $directory))
            {
                ftp_chmod($this->connection, $mode | 0111, $directory);

                return $this->getPath($bucket, $name);
            }
        }
        catch (\Exception $exception)
        {
            //Directory has to be created
        }

        ftp_chdir($this->connection, $this->options['home']);

        $directories = explode('/', substr($directory, strlen($this->options['home'])));
        foreach ($directories as $directory)
        {
            if (!$directory)
            {
                continue;
            }

            try
            {
                ftp_chdir($this->connection, $directory);
            }
            catch (\Exception $exception)
            {
                ftp_mkdir($this->connection, $directory);
                ftp_chmod($this->connection, $mode | 0111, $directory);
                ftp_chdir($this->connection, $directory);
            }
        }

        return $this->getPath($bucket, $name);
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

    /**
     * Refresh file permissions accordingly to container options.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return bool
     */
    protected function refreshPermissions(BucketInterface $bucket, $name)
    {
        $mode = $bucket->getOption('mode', FilesInterface::RUNTIME);

        return ftp_chmod($this->connection, $mode, $this->getPath($bucket, $name)) !== false;
    }

    /**
     * Destructing. FTP connection will be closed.
     */
    public function __destruct()
    {
        if (!empty($this->connection))
        {
            ftp_close($this->connection);
        }
    }
}