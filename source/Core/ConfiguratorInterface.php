<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

interface ConfiguratorInterface
{
    /**
     * Configuration section to be loaded.
     *
     * @param string $section
     * @return array
     */
    public function getConfig($section = null);
}