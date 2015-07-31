<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache\Stores\DriverInterface;
use Spiral\Cache\CacheStore;

/**
 * Two brothers.
 */
class MemcacheDriver extends CacheStore implements DriverInterface
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var \Memcache
     */
    protected $service = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options)
    {
        $this->options = $options;
        $this->service = new \Memcache();
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        foreach ($this->options['servers'] as $server)
        {
            $server = $server + $this->options['defaultServer'];
            $this->service->addServer(
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
    public function isAvailable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return $this->service->get($name) === false;
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return $this->service->get($name);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \MemcachedException
     */
    public function set($name, $data, $lifetime)
    {
        return $this->service->set($name, $data, 0, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function forever($name, $data)
    {
        $this->service->decrement($name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $this->service->delete($name);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($name, $delta = 1)
    {
        $this->service->increment($name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($name, $delta = 1)
    {
        $this->service->decrement($name, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->service->flush();
    }
}