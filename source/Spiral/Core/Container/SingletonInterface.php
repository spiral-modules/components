<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core\Container;

use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\MissingContainerException;

/**
 * Class treated as singleton MUST be saved as reference in IoC bindings. Must declare SINGLETON
 * constant.
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
    public static function instance(ContainerInterface $container = null);
}