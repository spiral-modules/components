<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache\Stores;

use Spiral\Cache\CacheProvider;
use Spiral\Cache\CacheStore;
use Spiral\Cache\Exceptions\StoreException;
use Spiral\Cache\Stores\Memcache\DriverInterface;
use Spiral\Cache\Stores\Memcache\MemcachedDriver;
use Spiral\Cache\Stores\Memcache\MemcacheDriver;

/**
 * Talks to Memcache and Memcached drivers using interface.
 */
class MemcacheStore extends CacheStore
{
    /**
     * {@inheritdoc}
     */
    const STORE = 'memcache';

    /**
     * {@inheritdoc}
     */
    protected $options = [
        'prefix'        => 'spiral:',
        'options'       => [],
        'defaultServer' => [
            'host'       => 'localhost',
            'port'       => 11211,
            'persistent' => true,
            'weight'     => 1
        ]
    ];

    /**
     * Maximum expiration time you can set.
     *
     * @link http://www.php.net/manual/ru/memcache.set.php
     */
    const MAX_EXPIRATION = 2592000;

    /**
     * Currently active driver.
     *
     * @var DriverInterface
     */
    protected $driver = null;

    /**
     * {@inheritdoc}
     *
     * @param DriverInterface $driver  Pre-created driver instance.
     * @param bool            $connect If true, custom driver will be connected.
     * @throws StoreException
     */
    public function __construct(
        CacheProvider $cache,
        DriverInterface $driver = null,
        $connect = true
    ) {
        parent::__construct($cache);

        if (is_object($driver)) {
            $this->setDriver($driver, $connect);

            return;
        }

        if (empty($this->options['servers'])) {
            throw new StoreException(
                'Unable to create Memcache[d] cache store. A lest one server must be specified.'
            );
        }

        //New Driver creation
        if (class_exists('Memcache')) {
            $this->setDriver(new MemcacheDriver($this->options), true);
        }

        if (class_exists('Memcached')) {
            $this->setDriver(new MemcachedDriver($this->options), true);
        }

        if (empty($this->driver)) {
            throw new StoreException('No available Memcache drivers found.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable()
    {
        return $this->driver->isAvailable();
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return $this->driver->get($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return $this->driver->get($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     *
     * Will apply MAX_EXPIRATION.
     */
    public function set($name, $data, $lifetime)
    {
        $lifetime = min(self::MAX_EXPIRATION + time(), $lifetime + time());
        if ($lifetime < 0) {
            $lifetime = 0;
        }

        return $this->driver->set($this->options['prefix'] . $name, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever($name, $data)
    {
        return $this->driver->forever($this->options['prefix'] . $name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        return $this->driver->delete($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($name, $delta = 1)
    {
        return $this->driver->increment($this->options['prefix'] . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($name, $delta = 1)
    {
        return $this->driver->decrement($this->options['prefix'] . $name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->driver->flush();
    }

    /**
     * Set pre-created Memcache driver.
     *
     * @param DriverInterface $driver  Pre-created driver instance.
     * @param bool            $connect Force connection.
     */
    protected function setDriver(DriverInterface $driver, $connect = false)
    {
        $this->driver = $driver;
        $connect && $this->driver->connect();
    }
}