<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Cache;

use Spiral\Core\Container\InjectableInterface;
use Spiral\Core\Traits\InjectableTrait;

/**
 * AbstractStore named like that for convenience and mapping.
 */
abstract class CacheStore implements StoreInterface, InjectableInterface
{
    /**
     * Constant based injector detection.
     */
    use InjectableTrait;

    /**
     * This is magick constant used by Spiral Container, it helps system to resolve controllable
     * injections.
     */
    const INJECTOR = CacheManager::class;

    /**
     * Store settings class associated with (see CacheManager).
     */
    const STORE = '';

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
        if (!$this->has($name)) {
            $this->set($name, $value = call_user_func($callback), $lifetime);

            return $value;
        }

        return $this->get($name);
    }
}