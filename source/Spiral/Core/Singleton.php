<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Exceptions\MissingContainerException;

/**
 * Spiral Container will treat classes like that as singletons.
 */
abstract class Singleton extends Component implements SingletonInterface
{
    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = null;

    /**
     * Singletons will work as desired only under Spiral Container which can understand SINGLETON
     * constant. You can consider this functionality as "helper".
     *
     * @param ContainerInterface $container
     * @return static
     * @throws MissingContainerException
     */
    public static function instance(ContainerInterface $container = null)
    {
        if (empty($container = self::container()) && empty($container)) {
            throw new MissingContainerException(
                "Singleton instance can be constructed only using valid Container."
            );
        }

        return $container->get(static::SINGLETON);
    }
}