<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Cache;

use Spiral\Cache\Exceptions\StoreException;

/**
 * Provides additional store functionality and singular operations.
 */
interface ActiveStoreInterface
{
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
}