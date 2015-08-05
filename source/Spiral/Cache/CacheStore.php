<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache;

/**
 * AbstractStore named like that for convenience and mapping.
 */
abstract class CacheStore implements StoreInterface
{
    /**
     * This is magick constant used by Spiral Container, it helps system to resolve controllable
     * injections.
     */
    const INJECTOR = CacheProvider::class;

    /**
     * Internal store name. Used to read configs in reverse way.
     */
    const STORE = '';

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * New CacheStore. Logic of receiving configuration is reverted for controllable injections in
     * spiral application.
     *
     * @param CacheProvider $cache CacheFacade component.
     */
    public function __construct(CacheProvider $cache)
    {
        $this->options = $cache->storeOptions(static::STORE) + $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function pull($name)
    {
        $value = $this->get($name);
        $this->delete($name);

        return $value;
    }

    /**
     * {@inheritdoc}
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