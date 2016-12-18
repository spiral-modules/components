<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Stores;

use Spiral\Cache\Prototypes\CacheStore;

/**
 * Talks to xcache functions.
 */
class XCacheStore extends CacheStore
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @param string $prefix
     */
    public function __construct(string $prefix = 'spiral:')
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return extension_loaded('xcache');
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return xcache_isset($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name)
    {
        return xcache_get($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $name, $data, $ttl = null)
    {
        return xcache_set($this->prefix . $name, $data, $this->lifetime($ttl, 0));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name)
    {
        xcache_unset($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function inc(string $name, int $delta = 1): int
    {
        return xcache_inc($this->prefix . $name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function dec(string $name, int $delta = 1): int
    {
        return xcache_dec($this->prefix . $name, $delta);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \ErrorException
     */
    public function clear()
    {
        xcache_clear_cache(XC_TYPE_VAR);
    }
}
