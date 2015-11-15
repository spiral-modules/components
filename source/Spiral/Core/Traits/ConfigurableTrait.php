<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core\Traits;

use Spiral\Core\ConfigInterface;

/**
 * Provides simplified access to component specific configuration.
 */
trait ConfigurableTrait
{
    /**
     * Component configuration.
     *
     * @var ConfigInterface
     */
    protected $config = null;

    /**
     * Current component configuration. Short naming.
     *
     * @return ConfigInterface|array
     */
    public function config()
    {
        return $this->config;
    }
}