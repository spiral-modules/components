<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Stores\Memcache;

use Spiral\Cache\Prototypes\CacheStore;

/**
 * Common functionality for Memcache and Memcached drivers.
 */
abstract class AbstractDriver extends CacheStore implements DriverInterface
{
    /**
     * @var array
     */
    protected $servers = [];

    /**
     * Default server options.
     *
     * @invisible
     *
     * @var array
     */
    protected $defaultServer = [
        'host'       => 'localhost',
        'port'       => 11211,
        'persistent' => true,
        'weight'     => 1,
    ];

    /**
     * @var \Memcached|\Memcache
     */
    protected $driver = null;

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        foreach ($this->servers as $server) {
            //Merging default options
            $server = $server + $this->defaultServer;

            $this->driver->addServer(
                $server['host'],
                $server['port'],
                $server['weight']
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return true;
    }
}
