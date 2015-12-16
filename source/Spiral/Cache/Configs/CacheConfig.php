<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Cache\Configs;

use Spiral\Core\InjectableConfig;
use Spiral\Core\Traits\Config\AliasTrait;

/**
 * Cache component configuration manager.
 */
class CacheConfig extends InjectableConfig
{
    use AliasTrait;

    /**
     * Configuration section.
     */
    const CONFIG = 'cache';

    /**
     * @var array
     */
    protected $config = [
        'store'  => '',
        'stores' => []
    ];

    /**
     * @param string $store
     * @return bool
     */
    public function hasStore($store)
    {
        return isset($this->config['stores'][$store]);
    }

    /**
     * @param string $store
     * @return string
     */
    public function storeClass($store)
    {
        if (class_exists($store)) {
            //Legacy format support
            return $store;
        }

        return $this->config['stores'][$store]['class'];
    }

    /**
     * @param string $store
     * @return array
     */
    public function storeOptions($store)
    {
        return $this->config['stores'][$store];
    }
}