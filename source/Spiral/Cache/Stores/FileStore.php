<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Cache\Stores;

use Spiral\Cache\CacheStore;
use Spiral\Files\FilesInterface;

/**
 * Serializes data to file. Usually points to runtime directory.
 */
class FileStore extends CacheStore
{
    /**
     * @var string
     */
    private $directory = '';

    /**
     * @var string
     */
    private $extension = 'cache';

    /**
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @param FilesInterface $files
     * @param string         $directory
     * @param string         $extension
     */
    public function __construct(FilesInterface $files, $directory, $extension = 'cache')
    {
        $this->directory = $files->normalizePath($directory);
        $this->extension = $extension;

        $this->files = $files;
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        if (!$this->files->exists($filename = $this->makeFilename($name))) {
            return false;
        }

        $cacheData = unserialize($this->files->read($filename));
        if (!empty($cacheData[0]) && $cacheData[0] < time()) {
            $this->delete($name);

            //Expired
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param int $expiration Current expiration time value in seconds (reference).
     */
    public function get($name, &$expiration = null)
    {
        if (!$this->files->exists($filename = $this->makeFilename($name))) {
            return null;
        }

        $cacheData = unserialize($this->files->read($filename));
        if (!empty($cacheData[0]) && $cacheData[0] < time()) {
            $this->delete($name);

            return null;
        }

        return $cacheData[1];
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $data, $lifetime)
    {
        return $this->files->write(
            $this->makeFilename($name),
            serialize([time() + $lifetime, $data])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function forever($name, $data)
    {
        return $this->files->write(
            $this->makeFilename($name),
            serialize([0, $data])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $this->files->delete($this->makeFilename($name));
    }

    /**
     * {@inheritdoc}
     */
    public function inc($name, $delta = 1)
    {
        $value = $this->get($name, $expiration) + $delta;

        $this->set($name, $value, $expiration - time());

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function dec($name, $delta = 1)
    {
        $value = $this->get($name, $expiration) - $delta;

        $this->set($name, $value, $expiration - time());

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        foreach ($this->files->getFiles($this->directory, $this->extension) as $filename) {
            $this->files->delete($filename);
        }
    }

    /**
     * Create filename using cache name.
     *
     * @param string $name
     * @return string Filename.
     */
    protected function makeFilename($name)
    {
        return $this->directory . '/' . md5($name) . '.' . $this->extension;
    }
}