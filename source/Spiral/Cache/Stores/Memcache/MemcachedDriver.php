<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Stores\Memcache;

/**
 * Two sisters.
 */
class MemcachedDriver extends AbstractDriver
{
    /**
     * @var \Memcached
     */
    protected $driver = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $servers)
    {
        $this->servers = $servers;
        $this->driver = new \Memcached();
    }

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
    public function has(string $name): bool
    {
        if ($this->driver->get($name) === false) {
            return $this->driver->getResultCode() != \Memcached::RES_NOTFOUND;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name)
    {
        return $this->driver->get($name);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \MemcachedException
     */
    public function set(string $name, $data, int $lifetime)
    {
        return $this->driver->set($name, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever(string $name, $data)
    {
        $this->driver->set($name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name)
    {
        $this->driver->delete($name);
    }

    /**
     * {@inheritdoc}
     */
    public function inc(string $name, int $delta = 1): int
    {
        if (!$this->has($name)) {
            $this->forever($name, $delta);

            return $delta;
        }

        return $this->driver->increment($name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function dec(string $name, int $delta = 1): int
    {
        return $this->driver->decrement($name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->driver->flush();
    }
}
