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
 * @todo planned to make it compatible with PSR16 or PSR6 once it's finally completed
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
     * @param string                 $name
     * @param mixed                  $data
     * @param null|int|\DateInterval $ttl
     *
     * @throws StoreException
     */
    public function set(string $name, $data, $ttl = null);

    /**
     * Delete data from cache.
     *
     * @param string $name Stored value name.
     *
     * @throws StoreException
     */
    public function delete(string $name);

    /**
     * Flush all values stored in cache.
     *
     * @throws StoreException
     */
    public function clear();
}
