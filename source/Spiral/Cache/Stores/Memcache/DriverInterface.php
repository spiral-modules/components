<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
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
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * Connect driver.
     */
    public function connect();
}