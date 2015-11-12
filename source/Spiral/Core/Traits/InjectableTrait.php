<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Core\Traits;

use Spiral\Core\Exceptions\Container\InjectionException;

/**
 * Uses class constant "INJECTOR" to set injector value.
 */
trait InjectableTrait
{
    /**
     * Has to return name of class used for injection/as factory.
     *
     * @return string
     */
    static public function getInjector()
    {
        if (!defined('self::INJECTOR')) {
            throw new InjectionException("Constant 'INJECTOR' not defined.");
        }

        return static::INJECTOR;
    }
}