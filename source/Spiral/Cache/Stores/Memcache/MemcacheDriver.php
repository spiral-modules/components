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
    public function has($name)
    {
        return $this->driver->get($name) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return $this->driver->get($name);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \MemcachedException
     */
    public function set($name, $data, $lifetime)
    {
        return $this->driver->set($name, $data, 0, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever($name, $data)
    {
        $this->driver->set($name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $this->driver->delete($name);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($name, $delta = 1)
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
    public function decrement($name, $delta = 1)
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