<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Configs;

use Spiral\Core\InjectableConfig;
use Spiral\Core\Traits\Config\AliasTrait;

/**
 * Translation component configuration.
 */
class ODMConfig extends InjectableConfig
{
    use AliasTrait;

    /**
     * Configuration section.
     */
    const CONFIG = 'odm';

    /**
     * @var array
     */
    protected $config = [
        'default'   => '',
        'aliases'   => [],
        'databases' => [],
        'schemas'   => [
            'mutators'       => [],
            'mutatorAliases' => []
        ]
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
     * Database connection configuration.
     *
     * @param string $database
     * @return array
     */
    public function databaseConfig($database)
    {
        return $this->config['databases'][$database];
    }

    /**
     * Resolve mutator alias.
     *
     * @param string $mutator
     * @return string
     */
    public function mutatorAlias($mutator)
    {
        if (!is_string($mutator) || !isset($this->config['schemas']['mutatorAliases'][$mutator])) {
            return $mutator;
        }

        return $this->config['schemas']['mutatorAliases'][$mutator];
    }

    /**
     * Get list of mutators associated with given type.
     *
     * @param string $type
     * @return array
     */
    public function getMutators($type)
    {
        return isset($this->config['schemas']['mutators'][$type])
            ? $this->config['schemas']['mutators'][$type]
            : [];
    }
}