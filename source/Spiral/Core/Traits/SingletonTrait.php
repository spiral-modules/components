<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\Core\Traits;

use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\MissingContainerException;

/**
 * Expects to be part of Component which has SINGLETON constant.
 */
trait SingletonTrait
{
    /**
     * Singletons will work as desired only under Spiral Container which can understand SINGLETON
     * constant. You can consider this functionality as "helper", if you can avoid using such
     * function - please do not use it.
     *
     * Global/static container used as fallback to receive class instance.
     *
     * @param ContainerInterface $container
     * @return static
     * @throws MissingContainerException
     */
    public static function instance(ContainerInterface $container = null)
    {
        $container = !empty($container) ? $container : self::staticContainer();
        if (empty($container)) {
            throw new MissingContainerException(
                "Singleton instance can be constructed only using valid Container."
            );
        }

        return $container->get(static::SINGLETON);
    }
}