<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Core\Container;

/**
 * Class must be injected using specialized factory and argument context.
 */
interface InjectableInterface
{
    /**
     * Has to return name of class used for injection/as factory.
     *
     * @return string
     */
    static public function getInjector();
}