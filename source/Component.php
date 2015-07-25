<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral;

use Spiral\Core\ContainerInterface;

class Component
{

    private static $container;

    public static function setContainer(ContainerInterface $container)
    {
        self::$container = $container;
    }

    //this creates THE FUCKING CHAIN!
    /**
     * @return ContainerInterface
     */
    public static function getContainer()
    {
        return self::$container;
    }

    /**
     * Create or retrieve component instance using IoC container. This method can return already
     * existed instance of class if that instance were defined as singleton and binded in core under
     * same class name. Using binding mechanism target instance can be redefined to use another
     * declaration. Be aware of that.
     *
     * @param array              $parameters Named parameters list to use for instance constructing.
     * @param ContainerInterface $container  Container instance used to resolve dependencies, if not provided
     *                                       global container will be used.
     * @return $this
     */
    public static function make($parameters = [], ContainerInterface $container = null)
    {
        if (empty($container))
        {
            $container = static::getContainer();
        }

        return $container->get(get_called_class(), $parameters);
    }
}