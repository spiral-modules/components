<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Storage\Servers;

use Psr\Http\Message\StreamInterface;
use Spiral\Files\Streams\StreamWrapper;
use Spiral\Storage\BucketInterface;
use Spiral\Files\FilesInterface;
use Spiral\Storage\StorageException;
use Spiral\Storage\StorageServer;

class SftpServer extends StorageServer
{
    /**
     * Authorization methods.
     */
    const NONE     = 'none';
    const PASSWORD = 'password';
    const PUB_KEY  = 'pubkey';

    /**
     * Server configuration, connection options, auth keys and certificates.
     *
     * @var array
     */
    protected $options = [
        'host'       => '',
        'methods'    => [],
        'port'       => 22,
        'home'       => '/',

        //Authorization method and username
        'authMethod' => 'password',
        'username'   => '',

        //Used with "password" authorization
        'password'   => '',

        //User with "pubkey" authorization
        'publicKey'  => '',
        'privateKey' => '',
        'secret'     => null
    ];

    /**
     * SFTP connection resource.
     *
     * @var resource
     */
    protected $sftp = null;

    /**
     * Every server represent one virtual storage which can be either local, remote or cloud based.
     * Every server should support basic set of low-level operations (create, move, copy and etc).
     *
     * @param FilesInterface $files   File component.
     * @param array          $options Storage connection options.
     */
    public function __construct(FilesInterface $files, array $options)
    {
        parent::__construct($files, $options);

        if (!extension_loaded('ssh2'))
        {
            throw new StorageException(
                "Unable to initialize sftp storage server, extension 'ssh2' not found."
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
        return file_exists($this->getUri($bucket, $name));
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
        if (!$this->exists($bucket, $name))
        {
            return false;
        }

        return filesize($this->getUri($bucket, $name));
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
        if ($origin instanceof StreamInterface)
        {
            $expectedSize = $origin->getSize();
            $source = StreamWrapper::getResource($origin);
        }
        else
        {
            $expectedSize = filesize($origin);
            $source = fopen($origin, 'r');
        }

        //Make sure target directory exists
        $this->ensureLocation($bucket, $name);

        //Remote file
        $destination = fopen($this->getUri($bucket, $name), 'w');

        //We can check size here
        $size = stream_copy_to_stream($source, $destination);

        fclose($source);
        fclose($destination);

        return $expectedSize == $size && $this->refreshPermissions($bucket, $name);
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
        return \GuzzleHttp\Psr7\stream_for(fopen($this->getUri($bucket, $name), 'rb'));
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
            ssh2_sftp_unlink($this->sftp, $this->getPath($bucket, $name));
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
        if (file_exists($this->getUri($bucket, $newname)))
        {
            //We have to clean location before renaming
            $this->delete($bucket, $newname);
        }

        if (!ssh2_sftp_rename($this->sftp, $this->getPath($bucket, $oldname), $location))
        {
            return false;
        }

        return $this->refreshPermissions($bucket, $newname);
    }


    /**
     * Ensure that SSH connection is up and can be used for file operations.
     *
     * @return bool
     * @throws StorageException
     */
    protected function connect()
    {
        $session = ssh2_connect(
            $this->options['host'],
            $this->options['port'],
            $this->options['methods']
        );

        if (empty($session))
        {
            throw new StorageException(
                "Unable to connect to remote SSH server '{$this->options['host']}'."
            );
        }

        //Authorization
        switch ($this->options['authMethod'])
        {
            case self::NONE:
                ssh2_auth_none($session, $this->options['username']);
                break;

            case self::PASSWORD;
                ssh2_auth_password($session, $this->options['username'], $this->options['password']);
                break;

            case self::PUB_KEY:
                ssh2_auth_pubkey_file(
                    $session,
                    $this->options['username'],
                    $this->options['publicKey'],
                    $this->options['privateKey'],
                    $this->options['secret']
                );
                break;
        }

        $this->sftp = ssh2_sftp($session);

        return true;
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
            $this->options['home'] . '/' . $bucket->getOption('folder') . '/' . $name
        );
    }

    /**
     * Get ssh2 specific uri which can be used in default php functions. Assigned to ssh2.sftp
     * stream wrapper.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return string
     */
    protected function getUri(BucketInterface $bucket, $name)
    {
        return 'ssh2.sftp://' . $this->sftp . $this->getPath($bucket, $name);
    }

    /**
     * Ensure that target object directory exists and has right permissions.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Relative object name.
     * @return string
     * @throws StorageException
     */
    protected function ensureLocation(BucketInterface $bucket, $name)
    {
        $directory = dirname($this->getPath($bucket, $name));

        $mode = $bucket->getOption('mode', FilesInterface::RUNTIME);
        if (file_exists('ssh2.sftp://' . $this->sftp . $directory))
        {
            if (function_exists('ssh2_sftp_chmod'))
            {
                ssh2_sftp_chmod($this->sftp, $directory, $mode | 0111);
            }

            return $this->getPath($bucket, $name);
        }

        $directories = explode('/', substr($directory, strlen($this->options['home'])));

        $location = $this->options['home'];
        foreach ($directories as $directory)
        {
            if (!$directory)
            {
                continue;
            }

            $location .= '/' . $directory;

            if (!file_exists('ssh2.sftp://' . $this->sftp . $location))
            {
                if (!ssh2_sftp_mkdir($this->sftp, $location))
                {
                    throw new StorageException(
                        "Unable to create directory {$location} using sftp connection."
                    );
                }

                if (function_exists('ssh2_sftp_chmod'))
                {
                    ssh2_sftp_chmod($this->sftp, $directory, $mode | 0111);
                }
            }
        }

        return $this->getPath($bucket, $name);
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
        if (!function_exists('ssh2_sftp_chmod'))
        {
            return true;
        }

        return ssh2_sftp_chmod(
            $this->sftp,
            $this->getPath($bucket, $name),
            $bucket->getOption('mode', FilesInterface::RUNTIME)
        );
    }
}