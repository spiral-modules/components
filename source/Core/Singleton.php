<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

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
     */
    public static function getInstance(ContainerInterface $container = null)
    {
        if (empty($container))
        {
            if (empty($container = self::getContainer()))
            {
                throw new \RuntimeException(
                    "Singleton instance can be constructed only using valid Container."
                );
            }
        }

        return $container->get(static::SINGLETON);
    }
}