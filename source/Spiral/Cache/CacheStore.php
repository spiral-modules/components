<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache;

abstract class CacheStore implements StoreInterface
{
    /**
     * This is magick constant used by Spiral Constant, it helps system to resolve controllable injections,
     * once set - Container will ask specific binding for injection.
     */
    const INJECTOR = CacheManager::class;

    /**
     * Internal store name.
     */
    const STORE = '';

    /**
     * Default store options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Create a new cache store instance. Every instance should represent a single cache method.
     * Multiple stores can exist at the same time and be used in different parts of the application.
     *
     * Logic of receiving configuration is reverted for controllable injections in spiral application.
     *
     * @param CacheManager $cache CacheFacade component.
     */
    public function __construct(CacheManager $cache)
    {
        $this->options = $cache->storeOptions(static::STORE) + $this->options;
    }

    /**
     * Read item from cache and delete it afterwards.
     *
     * @param string $name Stored value name.
     * @return mixed
     */
    public function pull($name)
    {
        $value = $this->get($name);
        $this->delete($name);

        return $value;
    }

    /**
     * Get the item from cache and if the item is missing, set a default value using Closure.
     *
     * @param string   $name     Stored value name.
     * @param int      $lifetime Duration in seconds until the value will expire.
     * @param callback $callback Callback should be called if a value doesn't exist in cache.
     * @return mixed
     */
    public function remember($name, $lifetime, $callback)
    {
        if (!$this->has($name))
        {
            $this->set($name, $value = call_user_func($callback), $lifetime);

            return $value;
        }

        return $this->get($name);
    }
}