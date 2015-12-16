<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\Storage\Configs;

use Spiral\Core\InjectableConfig;
use Spiral\Core\Traits\Config\AliasTrait;

/**
 * Storage manager configuration.
 */
class StorageConfig extends InjectableConfig
{
    use AliasTrait;

    /**
     * Configuration section.
     */
    const CONFIG = 'storage';

    /**
     * @var array
     */
    protected $config = [
        'servers' => [],
        'buckets' => []
    ];

    /**
     * @param string $server
     * @return bool
     */
    public function hasServer($server)
    {
        return isset($this->config['servers'][$server]);
    }

    /**
     * @param string $server
     * @return string
     */
    public function serverClass($server)
    {
        return $this->config['servers'][$server]['class'];
    }

    /**
     * @param string $server
     * @return array
     */
    public function serverOptions($server)
    {
        return $this->config['servers'][$server];
    }

    /**
     * @return array
     */
    public function getBuckets()
    {
        return $this->config['buckets'];
    }
}