<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use Spiral\Core\Exceptions\ConfiguratorException;

/**
 * Provides array based configuration for specified config section.
 */
interface ConfiguratorInterface
{
    /**
     * Return config for one specified section. Config has to be returned in component specific
     * array form.
     *
     * @param string $section
     * @return array
     * @throws ConfiguratorException
     */
    public function getConfig($section = null);
}