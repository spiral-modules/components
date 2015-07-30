<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache;

use Spiral\Cache\Exceptions\StoreException;

interface StoreInterface
{
    /**
     * Check if store is working properly. Please check if the store drives exists, files are
     * writable, etc.
     *
     * @return bool
     */
    public function isAvailable();

    /**
     * Check if value is present in cache.
     *
     * @param string $name Stored value name.
     * @return bool
     * @throws StoreException
     */
    public function has($name);

    /**
     * Get value stored in cache.
     *
     * @param string $name Stored value name.
     * @return mixed
     * @throws StoreException
     */
    public function get($name);

    /**
     * Save data in cache. Method will replace values created before.
     *
     * @param string $name     Stored value name.
     * @param mixed  $data     Data in string or binary format.
     * @param int    $lifetime Duration in seconds until the value will expire.
     * @return mixed
     * @throws StoreException
     */
    public function set($name, $data, $lifetime);

    /**
     * Store value in cache with infinite lifetime. Value will only expire when the cache is
     * flushed.
     *
     * @param string $name Stored value name.
     * @param mixed  $data Data in string or binary format.
     * @return mixed
     * @throws StoreException
     */
    public function forever($name, $data);

    /**
     * Delete data from cache.
     *
     * @param string $name Stored value name.
     * @throws StoreException
     */
    public function delete($name);

    /**
     * Increment numeric value stored in cache.
     *
     * @param string $name  Stored value name.
     * @param int    $delta How much to increment by. Set to 1 by default.
     * @throws StoreException
     */
    public function increment($name, $delta = 1);

    /**
     * Decrement numeric value stored in cache.
     *
     * @param string $name  Stored value name.
     * @param int    $delta How much to decrement by. Set to 1 by default.
     * @throws StoreException
     */
    public function decrement($name, $delta = 1);

    /**
     * Read item from cache and delete it afterwards.
     *
     * @param string $name Stored value name.
     * @return mixed
     * @throws StoreException
     */
    public function pull($name);

    /**
     * Get the item from cache and if the item is missing, set a default value using Closure.
     *
     * @param string   $name     Stored value name.
     * @param int      $lifetime Duration in seconds until the value will expire.
     * @param callback $callback Callback should be called if a value doesn't exist in cache.
     * @return mixed
     * @throws StoreException
     */
    public function remember($name, $lifetime, $callback);

    /**
     * Flush all values stored in cache.
     *
     * @throws StoreException
     */
    public function flush();
}