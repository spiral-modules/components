<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

abstract class Component
{
    /**
     * Global container instance using not very often by some component traits like Loggers, Events
     * and etc. Some non-core functionality may not work if such variable is not set.
     *
     * @var ContainerInterface
     */
    private static $container = null;

    /**
     * Set instance of global container, this method used to supply container to some component traits
     * and static functionality. It's not necessary to use it, but if used it may simplify development
     * a lot.
     *
     * @param ContainerInterface $container
     */
    final public static function setContainer(ContainerInterface $container)
    {
        self::$container = $container;
    }

    /**
     * Get instance of globally associated container. Method used by component traits and some global
     * functionality.
     *
     * @return ContainerInterface
     */
    final public static function getContainer()
    {
        return self::$container;
    }
}