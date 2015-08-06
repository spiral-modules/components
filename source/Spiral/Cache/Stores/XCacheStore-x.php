<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache\Stores;

use Spiral\Cache\CacheStore;

/**
 * Talks to xcache functions.
 */
class XCacheStore extends CacheStore
{
    /**
     * {@inheritdoc}
     */
    const STORE = 'xcache';

    /**
     * {@inheritdoc}
     */
    protected $options = [
        'prefix' => 'spiral:'
    ];

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
        return xcache_isset($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return xcache_get($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $data, $lifetime)
    {
        return xcache_set($this->options['prefix'] . $name, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever($name, $data)
    {
        return xcache_set($this->options['prefix'] . $name, $data, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        xcache_unset($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($name, $delta = 1)
    {
        return xcache_inc($this->options['prefix'] . $name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($name, $delta = 1)
    {
        return xcache_dec($this->options['prefix'] . $name, $delta);
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