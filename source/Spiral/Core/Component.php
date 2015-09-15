<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use Spiral\Core\Exceptions\MissingContainerException;

/**
 * Basic spiral cell. Automatically detects if "container" property are presented in class or uses
 * global container as fallback.
 */
abstract class Component
{
    /**
     * Global/static mainly used to resolve singletons outside of the runtime scope.
     *
     * @var ContainerInterface
     */
    private static $staticContainer = null;

    /**
     * Get instance of container associated with given object, uses global container as fallback
     * if not. Method generally used by traits.
     *
     * @return ContainerInterface|null
     */
    protected function container()
    {
        if (
            property_exists($this, 'container')
            && !empty($this->container)
            && $this->container instanceof ContainerInterface
        ) {
            return $this->container;
        }

        return self::$staticContainer;
    }

    /**
     * Get/set instance of global/static container, due this method must be used as few times as
     * possible both getter and setter methods joined into one. Please use this method only as
     * fallback.
     *
     * @internal Do not use for business logic.
     * @param ContainerInterface $container
     * @return ContainerInterface
     */
    final protected static function staticContainer(ContainerInterface $container = null)
    {
        if (!empty($container)) {
            self::$staticContainer = $container;
        }

        return self::$staticContainer;
    }

    /**
     * Must be used only to resolve optional constructor arguments. Use only for classes which are
     * generally resolved using Container.
     *
     * @internal Do not use for business logic.
     * @param mixed  $default Default value.
     * @param string $class   Requested class.
     * @return mixed|null|object
     */
    final protected static function saturate($default, $class)
    {
        if (!empty($default)) {
            return $default;
        }

        if (empty(self::$staticContainer)) {
            throw new MissingContainerException(
                "Unable to saturate '{$class}', global container were not set."
            );
        }

        //Only when global container is set
        return self::staticContainer()->get($class);
    }
}