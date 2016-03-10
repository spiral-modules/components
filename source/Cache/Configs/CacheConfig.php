<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Configs;

use Spiral\Core\InjectableConfig;

us  Spiral\Core\Traits\Config\AliasTrait;

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
        'stores' => [],
    ];

    /**
     * @return string
     */
    public function defaultStore()
    {
        return $this->config['store'];
    }

    /**
     * @param string $store
     *
     * @return bool
     */
    public function hasStore($store)
    {
        return isset($this->config['stores'][$store]);
    }

    /**
     * @param string $store
     *
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
     *
     * @return array
     */
    public function storeOptions($store)
    {
        return $this->config['stores'][$store];
    }

    /**
     * Detect store ID based on provided store class.
     *
     * @param \ReflectionClass $class
     *
     * @return string|null
     */
    public function detectStore(\ReflectionClass $class)
    {
        foreach ($this->config['stores'] as $store => $options) {
            if ($options['class'] == $class->getName()) {
                return $store;
            }
        }

        return;
    }
}
