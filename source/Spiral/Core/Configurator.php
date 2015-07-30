<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

/**
 * One simple configuration for different uses.
 */
class Configurator implements ConfiguratorInterface
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     *{@inheritdoc}
     *
     * Configurator will return same config for every section.
     */
    public function getConfig($section = null)
    {
        return $this->config;
    }
}