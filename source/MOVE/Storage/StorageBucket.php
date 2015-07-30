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
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Files\Streams\StreamableInterface;

class StorageBucket extends Component implements BucketInterface, LoggerAwareInterface
{
    /**
     * Benchmarking and logging operations.
     */
    use BenchmarkTrait, LoggerTrait;

    /**
     * This is magick constant used by Spiral Constant, it helps system to resolve controllable injections,
     * once set - Container will ask specific binding for injection.
     */
    const INJECTOR = StorageManager::class;

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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerID()
    {
        return $this->server;
    }

    /**
     * {@inheritdoc}
     */
    public function server()
    {
        return $this->storage->server($this->server);
    }

    /**
     * {@inheritdoc}
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAddress($address)
    {
        if (strpos($address, $this->prefix) === 0)
        {
            return strlen($this->prefix);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function buildAddress($name)
    {
        return $this->prefix . $name;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        $this->logger()->info(
            "Check existence of '{$this->buildAddress($name)}' at '{$this->getServerID()}'."
        );

        $this->benchmark($this->getServerID(), "exists::{$this->buildAddress($name)}");
        $result = (bool)$this->server()->exists($this, $name);
        $this->benchmark($this->getServerID(), "exists::{$this->buildAddress($name)}");

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function size($name)
    {
        $this->logger()->info(
            "Get size of '{$this->buildAddress($name)}' at '{$this->getServerID()}'."
        );

        $this->benchmark($this->getServerID(), "size::{$this->buildAddress($name)}");
        $size = $this->server()->size($this, $name);
        $this->benchmark($this->getServerID(), "size::{$this->buildAddress($name)}");

        return $size;
    }

    /**
     * {@inheritdoc}
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

        $this->benchmark($this->getServerID(), "upload::{$this->buildAddress($name)}");
        if (!$this->server()->put($this, $name, $origin))
        {
            throw new StorageException(
                "Unable to upload content into '{$this->buildAddress($name)}' at '{$this->getServerID()}' server."
            );
        }
        $this->benchmark($this->getServerID(), "upload::{$this->buildAddress($name)}");

        return new StorageObject($this->buildAddress($name), $name, $this->storage, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function allocateFilename($name)
    {
        $this->logger()->info(
            "Get local filename of '{$this->buildAddress($name)}' at '{$this->getServerID()}' server."
        );

        $this->benchmark($this->getServerID(), "filename::{$this->buildAddress($name)}");
        if (!$filename = $this->server()->allocateFilename($this, $name))
        {
            throw new StorageException(
                "Unable to allocate local filename for '{$this->buildAddress($name)}' "
                . "at '{$this->getServerID()}' server."
            );
        }
        $this->benchmark($this->getServerID(), "filename::{$this->buildAddress($name)}");

        return $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream($name)
    {
        $this->logger()->info(
            "Get stream for '{$this->buildAddress($name)}' at '{$this->server}' server."
        );

        $this->benchmark($this->getServerID(), "stream::{$this->buildAddress($name)}");
        if (!$stream = $this->server()->allocateStream($this, $name))
        {
            throw new StorageException(
                "Unable to allocate stream for '{$this->buildAddress($name)}' at '{$this->server}' server."
            );
        }
        $this->benchmark($this->getServerID(), "stream::{$this->buildAddress($name)}");

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $this->logger()->info(
            "Delete '{$this->buildAddress($name)}' at '{$this->server}' server."
        );

        $this->benchmark($this->getServerID(), "delete::{$this->buildAddress($name)}");
        $this->server()->delete($this, $name);
        $this->benchmark($this->getServerID(), "delete::{$this->buildAddress($name)}");
    }

    /**
     * {@inheritdoc}
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

        $this->benchmark($this->getServerID(), "rename::{$this->buildAddress($oldname)}");
        if (!$this->server()->rename($this, $oldname, $newname))
        {
            throw new StorageException(
                "Unable to rename '{$this->buildAddress($oldname)}' "
                . "to '{$this->buildAddress($newname)}' at '{$this->getServerID()}' server."
            );
        }
        $this->benchmark($this->getServerID(), "rename::{$this->buildAddress($oldname)}");

        return $this->buildAddress($newname);
    }

    /**
     * {@inheritdoc}
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

            $this->benchmark($this->getServerID(), "copy::{$this->buildAddress($name)}");
            if (!$this->server()->copy($this, $destination, $name))
            {
                throw new StorageException(
                    "Unable to copy '{$this->buildAddress($name)}' "
                    . "to '{$destination->buildAddress($name)}' at '{$this->getServerID()}' server."
                );
            }
            $this->benchmark($this->getServerID(), "copy::{$this->buildAddress($name)}");
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
     * {@inheritdoc}
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

            $this->benchmark($this->getServerID(), "replace::{$this->buildAddress($name)}");
            if (!$this->server()->replace($this, $destination, $name))
            {
                throw new StorageException(
                    "Unable to move '{$this->buildAddress($name)}' "
                    . "to '{$destination->buildAddress($name)}' at '{$this->getServerID()}' server."
                );
            }
            $this->benchmark($this->getServerID(), "replace::{$this->buildAddress($name)}");
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