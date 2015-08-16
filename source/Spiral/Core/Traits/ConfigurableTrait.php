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
     * Current component configuration. Short naming.
     *
     * @return array
     */
    public function config()
    {
        return $this->config;
    }
}