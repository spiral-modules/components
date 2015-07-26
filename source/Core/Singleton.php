<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

class Singleton extends Component
{
    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Singletons will work as desired only under Spiral Container which can understand SINGLETON
     * constant. You can consider this functionality as "helper".
     *
     * @param ContainerInterface $container
     * @return static
     */
    public static function getInstance(ContainerInterface $container = null)
    {
        $container = !empty($container) ? $container : self::getContainer();

        if (empty($container))
        {
            throw new \RuntimeException(
                "Singleton instance can be constructed only via valid Container."
            );
        }

        return $container->get(static::SINGLETON);
    }
}