<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Configs;

use Spiral\Core\InjectableConfig;
use Spiral\Core\Traits\Config\AliasTrait;

class MongoConfig extends InjectableConfig
{
    use AliasTrait;

    /**
     * Configuration section.
     */
    const CONFIG = 'mongo';

    /**
     * @var array
     */
    protected $config = [
        'default'   => '',
        'aliases'   => [],
        'databases' => []
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
     * Database connection configuration.
     *
     * @param string $database
     *
     * @return array
     */
    public function databaseOptions(string $database): array
    {
        return $this->config['databases'][$database];
    }

    /**
     * @return array
     */
    public function databaseNames(): array
    {
        return array_keys($this->config['databases']);
    }
}