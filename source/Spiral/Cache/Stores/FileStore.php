<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache\Stores;

use Spiral\Cache\CacheProvider;
use Spiral\Cache\CacheStore;
use Spiral\Files\FilesInterface;

/**
 * Serializes data to file. Usually points to runtime directory.
 */
class FileStore extends CacheStore
{
    /**
     * {@inheritdoc}
     */
    const STORE = 'file';

    /**
     * {@inheritdoc}
     */
    protected $options = [
        'directory' => null,
        'extension' => 'cache'
    ];

    /**
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * {@inheritdoc}
     *
     * @param FilesInterface $files
     */
    public function __construct(CacheProvider $cache, FilesInterface $files)
    {
        parent::__construct($cache);
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
        return $this->files->exists($this->makeFilename($name));
    }

    /**
     * {@inheritdoc}
     *
     * @param int $expiration Current expiration time value in seconds (reference).
     */
    public function get($name, &$expiration = null)
    {
        if (!$this->files->exists($filename = $this->makeFilename($name)))
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
    public function increment($name, $delta = 1)
    {
        $value = $this->get($name, $expiration) + $delta;
        $this->set($name, $value, $expiration - time());

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($name, $delta = 1)
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
        $files = $this->files->getFiles($this->options['directory'], $this->options['extension']);
        foreach ($files as $filename)
        {
            $this->files->delete($filename);
        }

        return count($files);
    }

    /**
     * Create filename using cache name.
     *
     * @param string $name
     * @return string Filename.
     */
    protected function makeFilename($name)
    {
        return $this->options['directory'] . '/' . md5($name) . '.' . $this->options['extension'];
    }
}