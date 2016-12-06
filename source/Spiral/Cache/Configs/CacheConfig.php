<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cache\Configs;

use Spiral\Cache\Exceptions\ConfigException;
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
        'stores' => [],
    ];

    /**
     * @return string
     */
    public function defaultStore(): string
    {
        return $this->config['store'];
    }

    /**
     * @param string $store
     *
     * @return bool
     */
    public function hasStore(string $store): bool
    {
        return isset($this->config['stores'][$store]);
    }

    /**
     * @param string $store
     *
     * @return string
     */
    public function storeClass(string $store): string
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
    public function storeOptions(string $store): array
    {
        if (isset($this->config['stores'][$store]['options'])) {
            return $this->config['stores'][$store]['options'];

        }

        $options = $this->config['stores'][$store];
        unset($options['class']);

        return $options;
    }

    /**
     * Detect store ID based on provided store class. Attention, method expects that config key
     * is store id/name.
     *
     * @param \ReflectionClass $class
     *
     * @return string
     *
     * @throws ConfigException
     */
    public function resolveStore(\ReflectionClass $class): string
    {
        foreach ($this->config['stores'] as $store => $options) {
            if ($options['class'] == $class->getName()) {
                return $store;
            }
        }

        throw new ConfigException(
            "Unable to detect store options for cache store '{$class->getName()}'"
        );
    }
}
