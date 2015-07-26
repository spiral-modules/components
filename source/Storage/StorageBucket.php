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
use Psr\Http\Message\UploadedFileInterface;
use Spiral\Core\Component;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Files\Streams\StreamableInterface;

class StorageBucket extends Component implements BucketInterface
{
    /**
     * Benchmarking and logging operations.
     */
    use BenchmarkTrait, LoggerTrait;

    /**
     * This is magick constant used by Spiral Constant, it helps system to resolve controllable injections,
     * once set - Container will ask specific binding for injection.
     */
    const INJECTABLE = StorageManager::class;

    /**
     * Address prefix will be attached to all bucket objects to generate unique object address.
     * You can use domain name, or folder for prefixed which should represent public buckets, in
     * this case object address will be valid URL.
     *
     * @var string
     */
    public $prefix = '';

    /**
     * Associated server name or id. Every server represent one virtual storage which can be either
     * local, remove or cloud based. Every adapter should support basic set of low-level operations
     * (create, move, copy and etc). Adapter instance called server, one adapter can be used for
     * multiple servers.
     *
     * @var string
     */
    protected $server = '';

    /**
     * Bucket options vary based on server (adapter) type associated, for local and ftp it usually
     * folder name and file permissions, for cloud or remove storages - remote bucket name and access
     * mode.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Storage component.
     *
     * @invisible
     * @var StorageInterface
     */
    protected $storage = null;

    /**
     * FileManager component.
     *
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * Every bucket represent one "virtual" folder which can be located on local machine, another
     * server (ftp) or in cloud (amazon, rackspace). bucket provides basic unified functionality
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
    )
    {
        $this->prefix = $prefix;
        $this->server = $server;
        $this->options = $options;
        $this->storage = $storage;
        $this->files = $files;
    }

    /**
     * Get server specific bucket option.
     *
     * @param string $name
     * @param null   $default
     * @return mixed
     */
    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * Get server name or ID associated with bucket.
     *
     * @return string
     */
    public function getServerID()
    {
        return $this->server;
    }

    /**
     * Get associated storage server. Every server represent one virtual storage which can be either
     * local, remove or cloud based. Every adapter should support basic set of low-level operations
     * (create, move, copy and etc). Adapter instance called server, one adapter can be used for
     * multiple servers.
     *
     * @return ServerInterface
     */
    public function getServer()
    {
        return $this->storage->server($this->server);
    }

    /**
     * Get bucket prefix value.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Check if object with given address can be potentially located inside this bucket and return
     * prefix length.
     *
     * @param string $address Storage object address (including name and prefix).
     * @return bool|int
     */
    public function ownAddress($address)
    {
        if (strpos($address, $this->prefix) === 0)
        {
            return strlen($this->prefix);
        }

        return false;
    }

    /**
     * Build object address using object name and bucket prefix. While using URL like prefixes
     * address can appear valid URI which can be used directly at frontend.
     *
     * @param string $name
     * @return string
     */
    public function buildAddress($name)
    {
        return $this->prefix . $name;
    }

    /**
     * Check if given object (name) exists in current bucket. Method should never fail if file
     * not exists and will return bool in any condition.
     *
     * @param string $name Storage object name.
     * @return bool
     */
    public function exists($name)
    {
        $this->logger()->info(
            "Check existence of '{$this->buildAddress($name)}' at '{$this->getServerID()}'."
        );

        $this->benchmark("{$this->getServerID()}::exists", $this->buildAddress($name));
        $result = (bool)$this->getServer()->exists($this, $name);
        $this->benchmark("{$this->getServerID()}::exists", $this->buildAddress($name));

        return $result;
    }

    /**
     * Retrieve object size in bytes, should return false if object does not exists.
     *
     * @param string $name Storage object name.
     * @return int|bool
     */
    public function size($name)
    {
        $this->logger()->info(
            "Get size of '{$this->buildAddress($name)}' at '{$this->getServerID()}'."
        );

        $this->benchmark("{$this->getServerID()}::size", $this->buildAddress($name));
        $size = $this->getServer()->size($this, $name);
        $this->benchmark("{$this->getServerID()}::size", $this->buildAddress($name));

        return $size;
    }

    /**
     * Upload storage object using given filename or stream. Method can return false in case of failed
     * upload or thrown custom exception if needed.
     *
     * @param string                                     $name   Given storage object name.
     * @param string|StreamInterface|StreamableInterface $origin Local filename or stream to use for
     *                                                           creation.
     * @return ObjectInterface
     */
    public function put($name, $origin)
    {
        $this->logger()->info(
            "Update to '{$this->buildAddress($name)}' at '{$this->getServerID()}' server."
        );

        if ($origin instanceof UploadedFileInterface || $origin instanceof StreamableInterface)
        {
            //Known simplification for UploadedFile
            $origin = $origin->getStream();
        }

        if (is_resource($origin))
        {
            $origin = \GuzzleHttp\Psr7\stream_for($origin);
        }

        $this->benchmark("{$this->getServerID()}::upload", $this->buildAddress($name));

        if (!$this->getServer()->put($this, $name, $origin))
        {
            throw new StorageException(
                "Unable to upload content into '{$this->buildAddress($name)}' at '{$this->getServerID()}' server."
            );
        }

        $this->benchmark("{$this->getServerID()}::upload", $this->buildAddress($name));

        return new StorageObject($this->buildAddress($name), $name, $this->storage, $this);
    }

    /**
     * Allocate local filename for remote storage object, if bucket represent remote location,
     * adapter should download file to temporary file and return it's filename. File is in readonly
     * mode, and in some cases will be erased on shutdown.
     *
     * @param string $name Storage object name.
     * @return string
     * @throws StorageException
     */
    public function allocateFilename($name)
    {
        $this->logger()->info(
            "Get local filename of '{$this->buildAddress($name)}' at '{$this->getServerID()}' server."
        );

        $this->benchmark("{$this->getServerID()}::filename", $this->buildAddress($name));
        if (!$filename = $this->getServer()->allocateFilename($this, $name))
        {
            throw new StorageException(
                "Unable to allocate local filename for '{$this->buildAddress($name)}' "
                . "at '{$this->getServerID()}' server."
            );
        }
        $this->benchmark("{$this->getServerID()}::filename", $this->buildAddress($name));

        return $filename;
    }

    /**
     * Get temporary read-only stream used to represent remote content. This method is very similar
     * to localFilename, however in some cases it may store data content in memory.
     *
     * @param string $name Storage object name.
     * @return StreamInterface
     * @throws StorageException
     */
    public function allocateStream($name)
    {
        $this->logger()->info(
            "Get stream for '{$this->buildAddress($name)}' at '{$this->server}' server."
        );

        $this->benchmark("{$this->getServerID()}::stream", $this->buildAddress($name));
        if (!$stream = $this->getServer()->allocateStream($this, $name))
        {
            throw new StorageException(
                "Unable to allocate stream for '{$this->buildAddress($name)}' at '{$this->server}' server."
            );
        }
        $this->benchmark("{$this->getServerID()}::stream", $this->buildAddress($name));

        return $stream;
    }

    /**
     * Delete storage object from specified bucket. Method should not fail if object does not
     * exists.
     *
     * @param string $name Storage object name.
     */
    public function delete($name)
    {
        $this->logger()->info(
            "Delete '{$this->buildAddress($name)}' at '{$this->server}' server."
        );

        $this->benchmark("{$this->getServerID()}::delete", $this->buildAddress($name));
        $this->getServer()->delete($this, $name);
        $this->benchmark("{$this->getServerID()}::delete", $this->buildAddress($name));
    }

    /**
     * Rename storage object without changing it's bucket. This operation does not require
     * object recreation or download and can be performed on remote server.
     *
     * @param string $oldname Storage object name.
     * @param string $newname New storage object name.
     * @return bool
     * @throws StorageException
     */
    public function rename($oldname, $newname)
    {
        if ($oldname == $newname)
        {
            return true;
        }

        $this->logger()->info(
            "Rename '{$this->buildAddress($oldname)}' to '{$this->buildAddress($newname)}' "
            . "at '{$this->server}' server."
        );

        $this->benchmark("{$this->getServerID()}::rename", $this->buildAddress($oldname));
        if (!$this->getServer()->rename($this, $oldname, $newname))
        {
            throw new StorageException(
                "Unable to rename '{$this->buildAddress($oldname)}' "
                . "to '{$this->buildAddress($newname)}' at '{$this->getServerID()}' server."
            );
        }
        $this->benchmark("{$this->getServerID()}::rename", $this->buildAddress($oldname));

        return $this->buildAddress($newname);
    }

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
    public function copy(BucketInterface $destination, $name)
    {
        if ($destination == $this)
        {
            return new StorageObject($this->buildAddress($name), $name, $this->storage, $this);
        }

        //Internal copying
        if ($this->getServerID() == $destination->getServerID())
        {
            $this->logger()->info(
                "Internal copy of '{$this->buildAddress($name)}' "
                . "to '{$destination->buildAddress($name)}' at '{$this->server}' server."
            );

            $this->benchmark("{$this->getServerID()}::copy", $this->buildAddress($name));
            if (!$this->getServer()->copy($this, $destination, $name))
            {
                throw new StorageException(
                    "Unable to copy '{$this->buildAddress($name)}' "
                    . "to '{$destination->buildAddress($name)}' at '{$this->getServerID()}' server."
                );
            }
            $this->benchmark("{$this->getServerID()}::copy", $this->buildAddress($name));
        }
        else
        {
            $this->logger()->info(
                "External copy of '{$this->getServerID()}'.'{$this->buildAddress($name)}' "
                . "to '{$destination->getServerID()}'.'{$destination->buildAddress($name)}'."
            );

            $stream = $this->allocateStream($name);

            //Now we will try to copy object using current server/memory as a buffer.
            if (empty($stream) || !$destination->put($name, $stream))
            {
                throw new StorageException(
                    "Unable to copy '{$this->getServerID()}'.'{$this->buildAddress($name)}' "
                    . "to '{$destination->buildAddress($name)}' at '{$this->getServerID()}' server."
                );
            }
        }

        return new StorageObject($destination->buildAddress($name), $name, $this->storage, $destination);
    }

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
    public function replace(BucketInterface $destination, $name)
    {
        if ($destination == $this)
        {
            return $this->buildAddress($name);
        }

        //Internal copying
        if ($this->getServerID() == $destination->getServerID())
        {
            $this->logger()->info(
                "Internal move '{$this->buildAddress($name)}' "
                . "to '{$destination->buildAddress($name)}' at '{$this->getServerID()}' server."
            );

            $this->benchmark("{$this->getServerID()}::replace", $this->buildAddress($name));
            if (!$this->getServer()->replace($this, $destination, $name))
            {
                throw new StorageException(
                    "Unable to move '{$this->buildAddress($name)}' "
                    . "to '{$destination->buildAddress($name)}' at '{$this->getServerID()}' server."
                );
            }
            $this->benchmark("{$this->getServerID()}::replace", $this->buildAddress($name));
        }
        else
        {
            $this->logger()->info(
                "External move '{$this->getServerID()}'.'{$this->buildAddress($name)}'"
                . " to '{$destination->getServerID()}'.'{$destination->buildAddress($name)}'."
            );

            $stream = $this->allocateStream($name);

            //Now we will try to replace object using current server/memory as a buffer.
            if (empty($stream) || !$destination->put($name, $stream))
            {
                throw new StorageException(
                    "Unable to replace '{$this->getServerID()}'.'{$this->buildAddress($name)}' "
                    . "to '{$destination->buildAddress($name)}' at '{$this->getServerID()}' server."
                );
            }

            $stream->detach() && $this->delete($name);
        }

        return $destination->buildAddress($name);
    }
}