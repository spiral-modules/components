<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Stores;

use Spiral\Cache\Prototypes\CacheStore;
use Spiral\Files\FilesInterface;

/**
 * Serializes data to file. Usually points to runtime directory.
 */
class FileStore extends CacheStore
{
    /**
     * @invisible
     *
     * @var FilesInterface
     */
    protected $files;

    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $extension;

    /**
     * @param FilesInterface $files
     * @param string         $directory
     * @param string         $extension
     */
    public function __construct(
        FilesInterface $files,
        string $directory,
        string $extension = 'cache'
    ) {
        $this->directory = $files->normalizePath($directory, true);
        $this->extension = $extension;

        $this->files = $files;
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
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
    public function get(string $name, int &$expiration = null)
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
    public function set(string $name, $data, $ttl = null)
    {
        return $this->files->write(
            $this->makeFilename($name),
            serialize([$this->lifetime($ttl, 0, time()), $data])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name)
    {
        $this->files->delete($this->makeFilename($name));
    }

    /**
     * {@inheritdoc}
     */
    public function inc(string $name, int $delta = 1): int
    {
        $value = $this->get($name, $expiration) + $delta;

        if (empty($expiration)) {
            $this->set($name, $value);
        } else {
            $this->set($name, $value, $expiration - time());
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function dec(string $name, int $delta = 1): int
    {
        $value = $this->get($name, $expiration) - $delta;

        if (empty($expiration)) {
            $this->set($name, $value);
        } else {
            $this->set($name, $value, $expiration - time());
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach ($this->files->getFiles($this->directory, $this->extension) as $filename) {
            $this->files->delete($filename);
        }
    }

    /**
     * Create filename using cache name.
     *
     * @param string $name
     *
     * @return string Filename.
     */
    protected function makeFilename(string $name): string
    {
        return $this->directory . md5($name) . '.' . $this->extension;
    }
}
