<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Stores\Memcache;

use Spiral\Cache\StoreInterface;

/**
 * User by MemcacheStore to abstract drivers.
 */
interface DriverInterface extends StoreInterface
{
    /**
     * New driver using user options. Driver should ignore prefix from config.
     *
     * @param array $servers
     */
    public function __construct(array $servers);

    /**
     * Connect driver.
     */
    public function connect();
}
