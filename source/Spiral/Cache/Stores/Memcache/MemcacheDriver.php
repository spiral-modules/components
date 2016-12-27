<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Stores\Memcache;

/**
 * Two brothers.
 */
class MemcacheDriver extends AbstractDriver
{
    /**
     * @var \Memcache
     */
    protected $driver = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $servers)
    {
        $this->servers = $servers;
        $this->driver = new \Memcache();
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        foreach ($this->servers as $server) {
            $server = $server + $this->defaultServer;

            $this->driver->addServer(
                $server['host'],
                $server['port'],
                $server['persistent'],
                $server['weight']
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return $this->driver->get($name) !== false;
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
    public function set(string $name, $data, $ttl = null)
    {
        return $this->driver->set($name, $data, 0, $this->lifetime($ttl, 0));
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
            $this->set($name, $delta);

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
    public function clear()
    {
        $this->driver->flush();
    }
}
