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
 * Provides array based configuration for specified config section.
 */
interface ConfiguratorInterface
{
    /**
     * Return config for one specified section. Config has to be returned in component specific array
     * form.
     *
     * @param string $section
     * @return array
     */
    public function getConfig($section = null);
}