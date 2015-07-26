<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache\Stores;

use Spiral\Cache\CacheManager;
use Spiral\Cache\CacheStore;
use Spiral\Files\FilesInterface;

class FileStore extends CacheStore
{
    /**
     * Internal store name.
     */
    const STORE = 'file';

    /**
     * Default store options.
     *
     * @var array
     */
    protected $options = [
        'directory' => null,
        'extension' => 'cache'
    ];

    /**
     * File component.
     *
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * Create a new cache store instance. Every instance should represent a single cache method.
     * Multiple stores can exist at the same time and be used in different parts of the application.
     *
     * Logic of receiving configuration is reverted for controllable injections in spiral application.
     *
     * @param CacheManager   $cache CacheFacade component.
     * @param FilesInterface $files
     */
    public function __construct(CacheManager $cache, FilesInterface $files = null)
    {
        parent::__construct($cache);
        $this->files = $files;
    }

    /**
     * Check if store works properly. Should make sure the store drives are there, the files are
     * writable and etc.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return true;
    }

    /**
     * Get cache filename by the provided value name.
     *
     * @param string $name
     * @return string
     */
    protected function makeFilename($name)
    {
        return $this->options['directory'] . '/' . md5($name) . '.' . $this->options['extension'];
    }

    /**
     * Check if value is presented in cache.
     *
     * @param string $name Stored value name.
     * @return bool
     */
    public function has($name)
    {
        return $this->files->exists($this->makeFilename($name));
    }

    /**
     * Get value stored in cache.
     *
     * @param string $name       Stored value name.
     * @param int    $expiration Current expiration time.
     * @return mixed
     */
    public function get($name, &$expiration = null)
    {
        $filename = $this->makeFilename($name);
        if (!$this->files->exists($filename))
        {
            return null;
        }

        $cacheData = unserialize($this->files->read($filename));
        if (!empty($cacheData[0]) && $cacheData[0] < time())
        {
            $this->delete($name);

            return null;
        }

        return $cacheData[1];
    }

    /**
     * Set data in cache. Should automatically create a record if one wasn't created before or
     * replace an existing record.
     *
     * @param string $name     Stored value name.
     * @param mixed  $data     Data in string or binary format.
     * @param int    $lifetime Duration in seconds until the value expires.
     * @return mixed
     */
    public function set($name, $data, $lifetime)
    {
        return $this->files->write(
            $this->makeFilename($name),
            serialize([time() + $lifetime, $data])
        );
    }

    /**
     * Store value in cache with infinite lifetime. Value should expire only when the cache is
     * flushed.
     *
     * @param string $name Stored value name.
     * @param mixed  $data Data in string or binary format.
     * @return mixed
     */
    public function forever($name, $data)
    {
        return $this->files->write(
            $this->makeFilename($name),
            serialize([0, $data])
        );
    }

    /**
     * Delete data from cache.
     *
     * @param string $name Stored value name.
     */
    public function delete($name)
    {
        $this->files->delete($this->makeFilename($name));
    }

    /**
     * Increment numeric value stored in cache.
     *
     * @param string $name  Stored value name.
     * @param int    $delta How much to increment by. 1 by default.
     * @return mixed
     */
    public function increment($name, $delta = 1)
    {
        $value = $this->get($name, $expiration) + $delta;
        $this->set($name, $value, $expiration - time());

        return $value;
    }

    /**
     * Decrement numeric value stored in cache.
     *
     * @param string $name  Stored value name.
     * @param int    $delta How much to decrement by. 1 by default.
     * @return mixed
     */
    public function decrement($name, $delta = 1)
    {
        $value = $this->get($name, $expiration) - $delta;
        $this->set($name, $value, $expiration - time());

        return $value;
    }

    /**
     * Flush all values stored in cache.
     *
     * @return mixed
     */
    public function flush()
    {
        $files = $this->files->getFiles($this->options['directory'], $this->options['extension']);
        foreach ($files as $filename)
        {
            $this->files->delete($filename);
        }

        return count($files);
    }
}