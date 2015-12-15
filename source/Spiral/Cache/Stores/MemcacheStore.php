<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Cache\Stores;

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
     * Maximum expiration time you can set.
     *
     * @link http://www.php.net/manual/ru/memcache.set.php
     */
    const MAX_EXPIRATION = 2592000;

    /**
     * @var string
     */
    private $prefix = 'spiral:';

    /**
     * Currently active driver.
     *
     * @var DriverInterface
     */
    private $driver = null;

    /**
     * @param string          $prefix
     * @param array           $servers
     * @param DriverInterface $driver Pre-created driver instance.
     * @param bool            $connect
     */
    public function __construct(
        $prefix = 'spiral:',
        array $servers = [],
        DriverInterface $driver = null,
        $connect = true
    ) {
        $this->prefix = $prefix;

        if (is_object($driver)) {
            $this->setDriver($driver, $connect);

            return;
        }

        if (empty($servers)) {
            throw new StoreException(
                'Unable to create Memcache[d] cache store. A least one server has to be specified.'
            );
        }

        //New Driver creation
        if (class_exists('Memcache')) {
            $this->setDriver(new MemcacheDriver($servers), true);
        }

        if (class_exists('Memcached')) {
            $this->setDriver(new MemcachedDriver($servers), true);
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
        return $this->driver->get($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return $this->driver->get($this->prefix . $name);
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

        return $this->driver->set($this->prefix . $name, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever($name, $data)
    {
        return $this->driver->forever($this->prefix . $name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        return $this->driver->delete($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function inc($name, $delta = 1)
    {
        return $this->driver->inc($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function dec($name, $delta = 1)
    {
        return $this->driver->dec($this->prefix . $name, $delta);
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

        if ($connect) {
            $this->driver->connect();
        }
    }
}