<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Prototypes;

use Spiral\Cache\CacheManager;
use Spiral\Cache\StoreInterface;
use Spiral\Core\Container\InjectableInterface;

/**
 * AbstractStore named like that for convenience and mapping.
 */
abstract class CacheStore implements StoreInterface, InjectableInterface
{
    /**
     * This is magick constant used by Spiral Container, it helps system to resolve controllable
     * injections.
     */
    const INJECTOR = CacheManager::class;

    /**
     * {@inheritdoc}
     */
    public function pull(string $name)
    {
        $value = $this->get($name);
        $this->delete($name);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function remember(string $name, int $lifetime, $callback)
    {
        if (!$this->has($name)) {
            $this->set($name, $value = call_user_func($callback), $lifetime);

            return $value;
        }

        return $this->get($name);
    }
}
