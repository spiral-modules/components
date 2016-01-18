<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Configs;

use Spiral\Core\InjectableConfig;
use Spiral\Core\Traits\Config\AliasTrait;

/**
 * Databases config.
 */
class DatabasesConfig extends InjectableConfig
{
    use AliasTrait;

    /**
     * Configuration section.
     */
    const CONFIG = 'databases';

    /**
     * @var array
     */
    protected $config = [
        'default'     => 'default',
        'aliases'     => [],
        'databases'   => [],
        'connections' => []
    ];

    /**
     * @return string
     */
    public function defaultDatabase()
    {
        return $this->config['default'];
    }

    /**
     * @param string $database
     * @return bool
     */
    public function hasDatabase($database)
    {
        return isset($this->config['databases'][$database]);
    }

    /**
     * @param string $connection
     * @return bool
     */
    public function hasConnection($connection)
    {
        return isset($this->config['connections'][$connection]);
    }

    /**
     * @return array
     */
    public function databaseNames()
    {
        return array_keys($this->config['databases']);
    }

    /**
     * @return array
     */
    public function connectionNames()
    {
        return array_keys($this->config['connections']);
    }

    /**
     * @param string $database
     * @return string
     */
    public function databaseConnection($database)
    {
        return $this->config['databases'][$database]['connection'];
    }

    /**
     * @param string $database
     * @return string
     */
    public function databasePrefix($database)
    {
        if (isset($this->config['databases'][$database]['tablePrefix'])) {
            return $this->config['databases'][$database]['tablePrefix'];
        }

        return '';
    }

    /**
     * @param string $connection
     * @return string
     */
    public function connectionDriver($connection)
    {
        return $this->config['connections'][$connection]['driver'];
    }

    /**
     * @param string $connection
     * @return array
     */
    public function connectionConfig($connection)
    {
        return $this->config['connections'][$connection];
    }
}
