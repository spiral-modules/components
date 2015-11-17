<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug\Configs;

use Spiral\Core\ArrayConfig;

/**
 * Debug component configuration.
 */
class DebuggerConfig extends ArrayConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'debug';

    /**
     * @var array
     */
    protected $config = [
        'logHandlers' => []
    ];

    /**
     * @param string $channel
     * @return array
     */
    public function hasHandlers($channel)
    {
        return isset($this->config['logHandlers'][$channel]);
    }

    /**
     * @param string $channel
     * @return array
     */
    public function logHandlers($channel)
    {
        return $this->config['logHandlers'][$channel];
    }
}