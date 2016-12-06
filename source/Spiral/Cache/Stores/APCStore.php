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
 * Talks to apc and apcu driver.
 */
class APCStore extends CacheStore
{
    /**
     * Cache driver types.
     */
    const APC_DRIVER  = 0;
    const APCU_DRIVER = 1;

    /**
     * {@inheritdoc}
     */
    private $prefix;

    /**
     * Cache driver type.
     *
     * @var int
     */
    private $driverType;

    /**
     * @param string $prefix
     */
    public function __construct(string $prefix = 'spiral:')
    {
        $this->prefix = $prefix;

        $this->driverType = function_exists('apcu_store') ? self::APCU_DRIVER : self::APC_DRIVER;
    }

    /**
     * Get APC cache type (APC or APCU).
     *
     * @see APCStore::APC_DRIVER
     * @see APCStore::APCU_DRIVER
     * @return int
     */
    public function getDriver(): int
    {
        return $this->driverType;
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return function_exists('apcu_store') || function_exists('apc_store');
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        if ($this->driverType == self::APCU_DRIVER) {
            return apcu_exists($this->prefix . $name);
        }

        return apc_exists($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name)
    {
        if ($this->driverType == self::APCU_DRIVER) {
            return apcu_fetch($this->prefix . $name);
        }

        return apc_fetch($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $name, $data, int $lifetime)
    {
        if ($this->driverType == self::APCU_DRIVER) {
            return apcu_store($this->prefix . $name, $data, $lifetime);
        }

        return apc_store($this->prefix . $name, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever(string $name, $data)
    {
        return $this->set($name, $data, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name)
    {
        if ($this->driverType == self::APCU_DRIVER) {
            apcu_delete($this->prefix . $name);

            return;
        }

        apc_delete($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function inc(string $name, int $delta = 1): int
    {
        if ($this->driverType == self::APCU_DRIVER) {
            return apcu_inc($this->prefix . $name, $delta);
        }

        return apc_inc($this->prefix . $name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function dec(string $name, int $delta = 1): int
    {
        if ($this->driverType == self::APCU_DRIVER) {
            return apcu_dec($this->prefix . $name, $delta);
        }

        return apc_dec($this->prefix . $name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        if ($this->driverType == self::APCU_DRIVER) {
            apcu_clear_cache();

            return;
        }

        apc_clear_cache('user');
    }
}
