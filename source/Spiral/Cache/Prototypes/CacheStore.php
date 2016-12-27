<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Prototypes;

use Spiral\Cache\ActiveStoreInterface;
use Spiral\Cache\CacheManager;
use Spiral\Cache\StoreInterface;
use Spiral\Core\Container\InjectableInterface;

/**
 * AbstractStore named like that for convenience and mapping.
 */
abstract class CacheStore implements StoreInterface, InjectableInterface, ActiveStoreInterface
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

    /**
     * Get lifetime in seconds based on a ttl, when ttl is null - null must be returned.
     *
     * @param null|int|\DateInterval $ttl
     * @param mixed                  $onNull Value to return when ttl is null.
     * @param int                    $offset Seconds to add to ttl when ttl is not null. Pass
     *                                       time() in here to get expiration time.
     *
     * @return int|null
     */
    protected function lifetime($ttl, $onNull = null, int $offset = 0)
    {
        if ($ttl === null) {
            return $onNull;
        }

        if ($ttl instanceof \DateInterval) {
            return $offset + $this->dateIntervalToSeconds($ttl);
        }

        return $offset + min($ttl, 0);
    }

    /**
     * @see http://stackoverflow.com/questions/3176609/calculate-total-seconds-in-php-dateinterval
     *
     * @param \DateInterval $interval
     *
     * @return int
     */
    private function dateIntervalToSeconds(\DateInterval $interval): int
    {
        $reference = new \DateTimeImmutable();
        $endTime = $reference->add($interval);

        return $endTime->getTimestamp() - $reference->getTimestamp();
    }
}
