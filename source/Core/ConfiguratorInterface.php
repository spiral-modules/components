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
     * Get configuration for specific requester.
     *
     * @param object|string $source
     * @return array
     */
    public function getConfig($source = null);
}