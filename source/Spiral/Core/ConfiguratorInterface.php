<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core;

use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Exceptions\ConfiguratorException;

/**
 * Provides array based configuration for specified config section.
 */
interface ConfiguratorInterface extends InjectorInterface
{
    /**
     * Return config for one specified section. Config has to be returned in component specific
     * array form.
     *
     * @param string $section
     * @throws ConfiguratorException
     */
    public function getConfig($section = null);
}