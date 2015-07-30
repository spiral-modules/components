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
 * Basic spiral cell.
 */
abstract class Component
{
    /**
     * Global container instance used not very often by some component traits like Loggers, Events
     * and etc.
     *
     * @var ContainerInterface
     */
    private static $container = null;

    /**
     * Set instance of global container. Required for some traits.
     *
     * @param ContainerInterface $container
     */
    final public static function setContainer(ContainerInterface $container)
    {
        self::$container = $container;
    }

    /**
     * Get global container instance or return null.
     *
     * @return ContainerInterface|null
     */
    final public static function container()
    {
        return self::$container;
    }
}