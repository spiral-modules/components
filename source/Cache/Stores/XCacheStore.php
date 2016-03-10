<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Stores;

use Spiral\Cache\CacheStore;

/**
 * Talks to xcache functions.
 */
class XCacheStore extends CacheStore
{
    /**
     * @var string
     */
    private $prefix = 'spiral:';

    /**
     * @param string $prefix
     */
    public function __construct($prefix = 'spiral:')
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable()
    {
        return extension_loaded('xcache');
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return xcache_isset($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return xcache_get($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $data, $lifetime)
    {
        return xcache_set($this->prefix . $name, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever($name, $data)
    {
        return xcache_set($this->prefix . $name, $data, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        xcache_unset($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function inc($name, $delta = 1)
    {
        return xcache_inc($this->prefix . $name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function dec($name, $delta = 1)
    {
        return xcache_dec($this->prefix . $name, $delta);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \ErrorException
     */
    public function flush()
    {
        xcache_clear_cache(XC_TYPE_VAR);
    }
}
