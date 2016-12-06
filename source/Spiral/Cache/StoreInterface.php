<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache;

use Spiral\Cache\Exceptions\StoreException;

/**
 * Represents single cache store.
 *
 * @todo planned to make it compatible with PSR16 once it's finally completed
 * @see  https://github.com/php-fig/fig-standards/blob/master/proposed/simplecache.md
 */
interface StoreInterface
{
    /**
     * Check if store is working properly. Please check if the store drives exists, files are
     * writable, etc.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Check if value is present in cache.
     *
     * @param string $name Stored value name.
     *
     * @return bool
     *
     * @throws StoreException
     */
    public function has(string $name): bool;

    /**
     * Get value stored in cache.
     *
     * @param string $name Stored value name.
     *
     * @return mixed
     *
     * @throws StoreException
     */
    public function get(string $name);

    /**
     * Save data in cache. Method will replace values created before.
     *
     * @param string $name
     * @param mixed  $data
     * @param int    $lifetime Duration in seconds until the value will expire.
     *
     * @throws StoreException
     */
    public function set(string $name, $data, int $lifetime);

    /**
     * Store value in cache with infinite lifetime. Value will only expire when the cache is
     * flushed.
     *
     * @param string $name
     * @param mixed  $data
     *
     * @throws StoreException
     */
    public function forever(string $name, $data);

    /**
     * Delete data from cache.
     *
     * @param string $name Stored value name.
     *
     * @throws StoreException
     */
    public function delete(string $name);

    /**
     * Increment numeric value stored in cache. Must return incremented value.
     *
     * @param string $name
     * @param int    $delta How much to increment by. Set to 1 by default.
     *
     * @return int
     *
     * @throws StoreException
     */
    public function inc(string $name, int $delta = 1): int;

    /**
     * Decrement numeric value stored in cache. Must return decremented value.
     *
     * @param string $name
     * @param int    $delta How much to decrement by. Set to 1 by default.
     *
     * @return int
     *
     * @throws StoreException
     */
    public function dec(string $name, int $delta = 1): int;

    /**
     * Read item from cache and delete it afterwards.
     *
     * @param string $name Stored value name.
     *
     * @return mixed
     *
     * @throws StoreException
     */
    public function pull(string $name);

    /**
     * Get the item from cache and if the item is missing, set a default value using Closure.
     *
     * @param string   $name
     * @param int      $lifetime
     * @param callback $callback Callback should be called if a value doesn't exist in cache.
     *
     * @return mixed
     *
     * @throws StoreException
     */
    public function remember(string $name, int $lifetime, $callback);

    /**
     * Flush all values stored in cache.
     *
     * @throws StoreException
     */
    public function flush();
}
