<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Session\Handlers;

use Spiral\Cache\CacheProviderInterface;
use Spiral\Cache\StoreInterface;
use Spiral\Core\Container\SaturableInterface;

/**
 * Stores session data in specified cache store.
 */
class CacheHandler implements \SessionHandlerInterface, SaturableInterface
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var StoreInterface
     */
    protected $cacheStore = null;

    /**
     * @var int
     */
    protected $lifetime = 0;

    /**
     * @param array $options  Session handler options.
     * @param int   $lifetime Default session lifetime.
     */
    public function __construct(array $options, $lifetime = 0)
    {
        $this->lifetime = $lifetime;
        $this->options = $options;
    }

    /**
     * @param CacheProviderInterface $cache
     */
    public function init(CacheProviderInterface $cache)
    {
        $this->cacheStore = $cache->store($this->options['store']);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($session_id)
    {
        $this->cacheStore->delete($this->options['prefix'] . $session_id);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function open($save_path, $session_id)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($session_id)
    {
        return $this->cacheStore->get($this->options['prefix'] . $session_id);
    }

    /**
     * {@inheritdoc}
     */
    public function write($session_id, $session_data)
    {
        return $this->cacheStore->set(
            $this->options['prefix'] . $session_id,
            $session_data,
            $this->lifetime
        );
    }
}