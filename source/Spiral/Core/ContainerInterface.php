<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use ReflectionFunctionAbstract as ContextFunction;
use Spiral\Core\Exceptions\Container\ArgumentException;
use Spiral\Core\Exceptions\Container\ContainerException;
use Spiral\Core\Exceptions\Container\InstanceException;

/**
 * Spiral IoC container interface. Used to resolve dependencies and etc.
 *
 *
 * @see InjectorInterface
 * @see StaurableInterface
 * @see SingletonInterface
 */
interface ContainerInterface
{
    /**
     * @param string $alias
     * @return bool
     */
    public function has($alias);

    /**
     * Resolve alias into it value. I value pointing to class resolver or singleto an object will
     * be returned.
     *
     * @param string $alias
     * @return mixed|null|object
     * @throws InstanceException
     * @throws ArgumentException
     */
    public function get($alias);

    /**
     * Create instance of requested class using binding class aliases and set of parameters provided
     * by user, rest of constructor parameters must be filled by container. Method might return
     * pre-constructed singleton.
     *
     * @param string $class
     * @param array  $parameters Parameters to construct new class.
     * @return mixed|null|object
     * @throws InstanceException
     * @throws ArgumentException
     */
    public function construct($class, $parameters = []);

    /**
     * Get list of arguments with resolved dependencies for specified function or method.
     *
     * @param ContextFunction $reflection Target function or method.
     * @param array           $parameters User specified parameters.
     * @return array
     * @throws ArgumentException
     */
    public function resolveArguments(ContextFunction $reflection, array $parameters = []);

    /**
     * Bind value resolver to container alias. Resolver can be class name (will be constructed
     * every
     * method call), function array or Closure (executed every call). Only object resolvers
     * supported by this method.
     *
     * @param string                $alias
     * @param string|array|callable $resolver
     */
    public function bind($alias, $resolver);

    /**
     * Bind value resolver to container alias to be executed as cached. Resolver can be class name
     * (will be constructed only once), function array or Closure (executed only once call).
     *
     * @param string                $alias
     * @param string|array|callable $resolver
     */
    public function bindSingleton($alias, $resolver);

    /**
     * Replace existed binding and return payload (implementation specific data) of previous
     * binding, previous binding can be restored using restore() method and such payload.
     *
     * @see restore()
     * @param string                $alias
     * @param string|array|callable $resolver
     * @return mixed
     */
    public function replace($alias, $resolver);

    /**
     * Restore previously pulled binding value using implementation specific payload. Method should
     * only accept result of replace() method.
     *
     * @see replace
     * @param mixed $replacePayload
     * @throws ContainerException
     */
    public function restore($replacePayload);

    /**
     * Check if alias points to constructed instance (singleton).
     *
     * @param string $alias
     * @return bool
     */
    public function hasInstance($alias);

    /**
     * @param string $alias
     */
    public function removeBinding($alias);
}