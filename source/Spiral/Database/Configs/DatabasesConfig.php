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
        'connections' => [],
    ];

    /**
     * @return string
     */
    public function defaultDatabase(): string
    {
        return $this->config['default'];
    }

    /**
     * @param string $database
     *
     * @return bool
     */
    public function hasDatabase(string $database): bool
    {
        return isset($this->config['databases'][$database]);
    }

    /**
     * @param string $connection
     *
     * @return bool
     */
    public function hasConnection(string $connection): bool
    {
        return isset($this->config['connections'][$connection]);
    }

    /**
     * @return array
     */
    public function databaseNames(): array
    {
        return array_keys($this->config['databases']);
    }

    /**
     * @return array
     */
    public function connectionNames(): array
    {
        return array_keys($this->config['connections']);
    }

    /**
     * @param string $database
     *
     * @return string
     */
    public function databaseConnection(string $database): string
    {
        return $this->config['databases'][$database]['connection'];
    }

    /**
     * @param string $database
     *
     * @return string
     */
    public function databasePrefix(string $database): string
    {
        if (isset($this->config['databases'][$database]['tablePrefix'])) {
            return $this->config['databases'][$database]['tablePrefix'];
        }

        return '';
    }

    /**
     * @param string $connection
     *
     * @return string
     */
    public function connectionDriver(string $connection): string
    {
        return $this->config['connections'][$connection]['driver'];
    }

    /**
     * @param string $connection
     *
     * @return array
     */
    public function connectionOptions(string $connection): array
    {
        return $this->config['connections'][$connection];
    }
}
