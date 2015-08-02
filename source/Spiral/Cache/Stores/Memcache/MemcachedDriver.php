<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache\Stores\Memcache;

use Spiral\Cache\CacheStore;

/**
 * Two sisters.
 */
class MemcachedDriver extends CacheStore implements DriverInterface
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var \Memcached
     */
    protected $service = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options)
    {
        $this->options = $options;
        $this->service = new \Memcached();
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        foreach ($this->options['options'] as $option => $value)
        {
            $this->service->setOption($option, $value);
        }

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
        if ($this->service->get($name) === false)
        {
            return $this->service->getResultCode() != \Memcached::RES_NOTFOUND;
        }

        return true;
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
        return $this->service->set($name, $data, $lifetime);
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