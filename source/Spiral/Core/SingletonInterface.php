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
 * Class treated as singleton by spiral container and should be saved as reference in bindings.
 * Must declare SINGLETON constant.
 */
interface SingletonInterface
{
    /**
     * Singletons will work as desired only under Spiral Container which can understand SINGLETON
     * constant. You can consider this functionality as "helper".
     *
     * @param ContainerInterface $container
     * @return static
     * @throws MissingContainerException
     */
    public static function getInstance(ContainerInterface $container = null);
}