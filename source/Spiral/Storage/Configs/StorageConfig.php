<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\Storage\Configs;

use Spiral\Core\InjectableConfig;

/**
 * Storage manager configuration.
 */
class StorageConfig extends InjectableConfig
{
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