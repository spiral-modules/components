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
use Spiral\Storage\Exceptions\ServerException;
use Spiral\Storage\StorageException;
use Spiral\Storage\StorageServer;

/**
 * Provides abstraction level to work with data located at remove FTP server.
 */
class FtpServer extends StorageServer
{
    /**
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
     * FTP Connection.
     *
     * @var resource
     */
    protected $connection = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(FilesInterface $files, array $options)
    {
        parent::__construct($files, $options);

        if (!extension_loaded('ftp'))
        {
            throw new ServerException(
                "Unable to initialize ftp storage server, extension 'ftp' not found."
            );
        }

        $this->connect();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(BucketInterface $bucket, $name)
    {
        return ftp_size($this->connection, $this->getPath($bucket, $name)) != -1;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function put(BucketInterface $bucket, $name, $source)
    {
        $location = $this->ensureLocation($bucket, $name);
        if (!ftp_put($this->connection, $location, $this->castFilename($source), FTP_BINARY))
        {
            throw new ServerException("Unable to put '{$name}' to FTP server.");
        }

        return $this->refreshPermissions($bucket, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function allocateFilename(BucketInterface $bucket, $name)
    {
        if (!$this->exists($bucket, $name))
        {
            throw new ServerException(
                "Unable to create local filename for '{$name}', object does not exists."
            );
        }

        //File should be removed after processing
        $tempFilename = $this->files->tempFilename($this->files->extension($name));

        if (!ftp_get($this->connection, $tempFilename, $this->getPath($bucket, $name), FTP_BINARY))
        {
            throw new ServerException("Unable to create local filename for '{$name}'.");
        }

        return $tempFilename;
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream(BucketInterface $bucket, $name)
    {
        if (!$filename = $this->allocateFilename($bucket, $name))
        {
            throw new ServerException("Unable to create stream for '{$name}', object does not exists.");
        }

        return \GuzzleHttp\Psr7\stream_for(fopen($filename, 'rb'));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(BucketInterface $bucket, $name)
    {
        if ($this->exists($bucket, $name))
        {
            ftp_delete($this->connection, $this->getPath($bucket, $name));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename(BucketInterface $bucket, $oldname, $newname)
    {
        if (!$this->exists($bucket, $oldname))
        {
            throw new ServerException("Unable to rename '{$oldname}', object does not exists.");
        }

        $location = $this->ensureLocation($bucket, $newname);
        if (!ftp_rename($this->connection, $this->getPath($bucket, $oldname), $location))
        {
            throw new ServerException("Unable to rename '{$oldname}' to '{$newname}'.");
        }

        return $this->refreshPermissions($bucket, $newname);
    }

    /**
     * {@inheritdoc}
     */
    public function replace(BucketInterface $bucket, BucketInterface $destination, $name)
    {
        if (!$this->exists($bucket, $name))
        {
            throw new ServerException("Unable to replace '{$name}', object does not exists.");
        }

        $location = $this->ensureLocation($bucket, $name);
        if (!ftp_rename($this->connection, $this->getPath($bucket, $name), $location))
        {
            throw new ServerException("Unable to replace '{$name}'.");
        }

        return $this->refreshPermissions($bucket, $name);
    }

    /**
     * Ensure FTP connection.
     *
     * @throws ServerException
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
            throw new ServerException(
                "Unable to connect to remote FTP server '{$this->options['host']}'."
            );
        }

        if (!ftp_login($this->connection, $this->options['login'], $this->options['password']))
        {
            throw new ServerException(
                "Unable to connect to remote FTP server '{$this->options['host']}'."
            );
        }

        if (!ftp_pasv($this->connection, $this->options['passive']))
        {
            throw new ServerException(
                "Unable to set passive mode at remote FTP server '{$this->options['host']}'."
            );
        }

        return true;
    }

    /**
     * Ensure that target directory exists and has right permissions.
     *
     * @param BucketInterface $bucket
     * @param string          $name
     * @return string
     * @throws ServerException
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
     * @param BucketInterface $bucket
     * @param string          $name
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
     * @param BucketInterface $bucket
     * @param string          $name
     * @return bool
     */
    protected function refreshPermissions(BucketInterface $bucket, $name)
    {
        $mode = $bucket->getOption('mode', FilesInterface::RUNTIME);

        return ftp_chmod($this->connection, $mode, $this->getPath($bucket, $name)) !== false;
    }

    /**
     * Drop FTP connection.
     */
    public function __destruct()
    {
        if (!empty($this->connection))
        {
            ftp_close($this->connection);
        }
    }
}