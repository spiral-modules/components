<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core\Traits;

/**
 * Provides simplified access to component specific configuration.
 */
trait ConfigurableTrait
{
    /**
     * Component configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Update config with new values, new configuration will be merged with old one.
     *
     * @param array $config
     * @return array
     */
    public function setConfig(array $config)
    {
        return $this->config = $config + $this->config;
    }

    /**
     * Current component configuration. Short naming.
     *
     * @return array
     */
    public function config()
    {
        return $this->config;
    }
}