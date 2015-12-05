<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug\Configs;

use Spiral\Core\InjectableConfig;

/**
 * Debug component configuration. Must only contain array of log handlers for monolog channels.
 */
class DebuggerConfig extends InjectableConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'monolog';

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @param string $channel
     * @return array
     */
    public function hasHandlers($channel)
    {
        return isset($this->config[$channel]);
    }

    /**
     * @param string $channel
     * @return array
     */
    public function logHandlers($channel)
    {
        return $this->config[$channel];
    }
}