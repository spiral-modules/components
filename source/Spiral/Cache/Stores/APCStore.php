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

/**
 * Talks to apc and apcu driver.
 */
class APCStore extends CacheStore
{
    /**
     * {@inheritdoc}
     */
    const STORE = 'apc';

    /**
     * Cache driver types.
     */
    const APC_DRIVER  = 0;
    const APCU_DRIVER = 1;

    /**
     * {@inheritdoc}
     */
    protected $options = [
        'prefix' => 'spiral:'
    ];

    /**
     * Cache driver type.
     *
     * @var int
     */
    protected $driver = self::APC_DRIVER;

    /**
     * {@inheritdoc}
     */
    public function __construct(CacheProvider $cache)
    {
        parent::__construct($cache);
        $this->driver = function_exists('apcu_store') ? self::APCU_DRIVER : self::APC_DRIVER;
    }

    /**
     * Get APC cache type (APC or APCU).
     *
     * @return int
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable()
    {
        return function_exists('apcu_store') || function_exists('apc_store');
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        if ($this->driver == self::APCU_DRIVER) {
            return apcu_exists($this->options['prefix'] . $name);
        }

        return apc_exists($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if ($this->driver == self::APCU_DRIVER) {
            return apcu_fetch($this->options['prefix'] . $name);
        }

        return apc_fetch($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $data, $lifetime)
    {
        if ($this->driver == self::APCU_DRIVER) {
            return apcu_store($this->options['prefix'] . $name, $data, $lifetime);
        }

        return apc_store($this->options['prefix'] . $name, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever($name, $data)
    {
        return $this->set($name, $data, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        if ($this->driver == self::APCU_DRIVER) {
            apcu_delete($this->options['prefix'] . $name);

            return;
        }

        apc_delete($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($name, $delta = 1)
    {
        if ($this->driver == self::APCU_DRIVER) {
            return apcu_inc($this->options['prefix'] . $name, $delta);
        }

        return apc_inc($this->options['prefix'] . $name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($name, $delta = 1)
    {
        if ($this->driver == self::APCU_DRIVER) {
            return apcu_dec($this->options['prefix'] . $name, $delta);
        }

        return apc_dec($this->options['prefix'] . $name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        if ($this->driver == self::APCU_DRIVER) {
            apcu_clear_cache();

            return;
        }

        apc_clear_cache('user');
    }
}