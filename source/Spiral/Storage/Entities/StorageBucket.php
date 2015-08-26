<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Storage\Entities;

use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Files\Streams\StreamableInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\StorageInterface;
use Spiral\Storage\StorageManager;

/**
 * Default implementation of storage bucket.
 */
class StorageBucket extends Component implements
    BucketInterface,
    LoggerAwareInterface,
    InjectableInterface
{
    /**
     * Most of storage operations are pretty slow, we might record and explain all of them.
     */
    use BenchmarkTrait, LoggerTrait;

    /**
     * This is magick constant used by Spiral Constant, it helps system to resolve controllable
     * injections, once set - Container will ask specific binding for injection.
     */
    const INJECTOR = StorageManager::class;

    /**
     * @var string
     */
    private $prefix = '';

    /**
     * @var string
     */
    private $server = '';

    /**
     * @var array
     */
    private $options = [];

    /**
     * @invisible
     * @var StorageInterface
     */
    protected $storage = null;

    /**
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
    ) {
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
        if (strpos($address, $this->prefix) === 0) {
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
    public function put($name, $source)
    {
        $this->logger()->info(
            "Put '{$this->buildAddress($name)}' at '{$this->getServerID()}' server."
        );

        if ($source instanceof UploadedFileInterface || $source instanceof StreamableInterface) {
            //Known simplification for UploadedFile
            $source = $source->getStream();
        }

        if (is_resource($source)) {
            $source = \GuzzleHttp\Psr7\stream_for($source);
        }

        $this->benchmark($this->getServerID(), "put::{$this->buildAddress($name)}");
        $this->server()->put($this, $name, $source);
        $this->benchmark($this->getServerID(), "put::{$this->buildAddress($name)}");

        return $this->storage->open($this->buildAddress($name));
    }

    /**
     * {@inheritdoc}
     */
    public function allocateFilename($name)
    {
        $this->logger()->info(
            "Allocate filename of '{$this->buildAddress($name)}' at '{$this->getServerID()}' server."
        );

        $benchmark = $this->benchmark(
            $this->getServerID(), "filename::{$this->buildAddress($name)}"
        );

        $filename = $this->server()->allocateFilename($this, $name);
        $this->benchmark($benchmark);

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

        $benchmark = $this->benchmark(
            $this->getServerID(), "stream::{$this->buildAddress($name)}"
        );
        $stream = $this->server()->allocateStream($this, $name);
        $this->benchmark($benchmark);

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

        $benchmark = $this->benchmark(
            $this->getServerID(), "delete::{$this->buildAddress($name)}"
        );
        $this->server()->delete($this, $name);
        $this->benchmark($benchmark);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($oldname, $newname)
    {
        if ($oldname == $newname) {
            return true;
        }

        $this->logger()->info(
            "Rename '{$this->buildAddress($oldname)}' to '{$this->buildAddress($newname)}' "
            . "at '{$this->server}' server."
        );

        $benchmark = $this->benchmark(
            $this->getServerID(), "rename::{$this->buildAddress($oldname)}"
        );
        $this->server()->rename($this, $oldname, $newname);
        $this->benchmark($benchmark);

        return $this->buildAddress($newname);
    }

    /**
     * {@inheritdoc}
     */
    public function copy(BucketInterface $destination, $name)
    {
        if ($destination == $this) {
            return $this->storage->open($this->buildAddress($name));
        }

        //Internal copying
        if ($this->getServerID() == $destination->getServerID()) {
            $this->logger()->info(
                "Internal copy of '{$this->buildAddress($name)}' "
                . "to '{$destination->buildAddress($name)}' at '{$this->server}' server."
            );

            $benchmark = $this->benchmark(
                $this->getServerID(), "copy::{$this->buildAddress($name)}"
            );
            $this->server()->copy($this, $destination, $name);
            $this->benchmark($benchmark);
        } else {
            $this->logger()->info(
                "External copy of '{$this->getServerID()}'.'{$this->buildAddress($name)}' "
                . "to '{$destination->getServerID()}'.'{$destination->buildAddress($name)}'."
            );

            $destination->put($name, $this->allocateStream($name));
        }

        return $this->storage->open($destination->buildAddress($name));
    }

    /**
     * {@inheritdoc}
     */
    public function replace(BucketInterface $destination, $name)
    {
        if ($destination == $this) {
            return $this->buildAddress($name);
        }

        //Internal copying
        if ($this->getServerID() == $destination->getServerID()) {
            $this->logger()->info(
                "Internal move '{$this->buildAddress($name)}' "
                . "to '{$destination->buildAddress($name)}' at '{$this->getServerID()}' server."
            );

            $benchmark = $this->benchmark(
                $this->getServerID(), "replace::{$this->buildAddress($name)}"
            );
            $this->server()->replace($this, $destination, $name);
            $this->benchmark($benchmark);
        } else {
            $this->logger()->info(
                "External move '{$this->getServerID()}'.'{$this->buildAddress($name)}'"
                . " to '{$destination->getServerID()}'.'{$destination->buildAddress($name)}'."
            );

            $stream = $this->allocateStream($name);
            $destination->put($name, $stream);
            $stream->detach() && $this->delete($name);
        }

        return $destination->buildAddress($name);
    }
}