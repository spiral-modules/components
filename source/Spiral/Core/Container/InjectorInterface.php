<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core\Container;

use Spiral\Core\Exceptions\Container\ContainerException;

/**
 * Magic spiral interface used to resolve dependencies based on their context. Container MUST execute
 * such method if INJECTOR constant found in requested class.
 */
interface InjectorInterface
{
    /**
     * Injector will receive requested class or interface reflection and reflection linked
     * to parameter in constructor or method.
     *
     * This method can return pre-defined instance or create new one based on requested class. Parameter
     * reflection can be used for dynamic class constructing, for example it can define database name
     * or config section to be used to construct requested instance.
     *
     * @param \ReflectionClass     $class
     * @param \ReflectionParameter $parameter
     * @return object
     * @throws ContainerException
     * @throws \ErrorException
     */
    public function createInjection(\ReflectionClass $class, \ReflectionParameter $parameter);
}