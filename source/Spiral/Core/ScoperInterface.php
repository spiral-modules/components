<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Core;

use Spiral\Core\Exceptions\Container\ContainerException;

/**
 * Provides ability to open and close IoC scope.
 */
interface ScoperInterface
{
    /**
     * Replace existed binding and return payload (implementation specific data) of previous
     * binding, previous binding can be restored using restore() method and such payload.
     *
     * @see restore()
     *
     * @param string                $alias
     * @param string|array|callable $resolver
     *
     * @return mixed Scope payload.
     */
    public function replace($alias, $resolver);

    /**
     * Restore previously pulled binding value using implementation specific payload. Method should
     * only accept result of replace() method.
     *
     * @see replace
     *
     * @param mixed $replacePayload
     *
     * @throws ContainerException
     */
    public function restore($replacePayload);
}