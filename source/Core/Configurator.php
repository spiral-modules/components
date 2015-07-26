<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

class Configurator implements ConfiguratorInterface
{
    /**
     * Config dedicated for one receiver.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Configurator is simple class used to create configuration source for only one receiver.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get configuration for specific requester.
     *
     * @param object|string $source
     * @return array
     */
    public function getConfig($source = null)
    {
        return $this->config;
    }
}