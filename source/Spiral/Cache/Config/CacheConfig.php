<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Cache\Config;

use Spiral\Core\ArrayConfig;

/**
 * Cache component configuration manager.
 */
class CacheConfig extends ArrayConfig
{
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
     * @return array
     */
    public function storeOptions($store)
    {
        return $this->config['stores'][$store];
    }
}