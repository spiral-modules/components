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
use Spiral\Core\Container\ArgumentException;
use Spiral\Core\Container\InstanceException;

interface ContainerInterface
{
    /**
     * Resolve class instance using IoC container. Class can be requested using it's own name, alias
     * binding, singleton binding, closure function, closure function with singleton resolution, or
     * via InjectableInterface interface. To add binding use Container::bind() or Container::bindSingleton()
     * methods.
     *
     * This method widely used inside spiral core to resolve adapters, handlers and databases.
     *
     * @param string $alias                         Class/interface name or binded alias should be
     *                                              resolved to instance.
     * @param array  $parameters                    Parameters to be mapped to class constructor or
     *                                              forwarded to closure.
     * @return mixed|null|object
     * @throws InstanceException
     * @throws ArgumentException
     */
    public function get($alias, $parameters = []);

    /**
     * Helper method to resolve constructor or function arguments, build required DI using IoC
     * container and mix with pre-defined set of named parameters.
     *
     * @param ContextFunction $reflection Method or constructor should be filled with DI.
     * @param array           $parameters Outside parameters used in priority to DI.
     *                                    Named list.
     * @return array
     * @throws ArgumentException
     */
    public function resolveArguments(ContextFunction $reflection, array $parameters = []);

    /**
     * IoC binding can create a link between specified alias and method to resolve that alias, resolver
     * can be either class instance (that instance will be resolved as singleton), callback or string
     * alias. String aliases can be used to rewrite core classes with custom realization, or specify
     * what interface child should be used.
     *
     * @param string                 $alias  Alias where singleton will be attached to.
     * @param string|object|callable Closure to resolve class instance, class instance or class name.
     */
    public function bind($alias, $resolver);

    /**
     * Replace existed binding with new value. Existed binding value will be returned from this method
     * and can be used again to restore original state using restore() method.
     *
     * Attention, due internal format you can restore original value only using restore method!
     *
     * @see restore()
     * @param string                 $alias  Alias where singleton will be attached to.
     * @param string|object|callable Closure to resolve class instance, class instance or class name.
     * @return object|string|array|null
     */
    public function replace($alias, $resolver);

    /**
     * Restore previously pulled binding value. Method will accept only result of replace() method.
     *
     * @param mixed $binding
     */
    public function restore($binding);

    /**
     * Bind closure or class name which will be performed only once, after first call class instance
     * will be attached to specified alias and will be returned directly without future invoking.
     *
     * @param string   $alias    Alias where singleton will be attached to.
     * @param callable $resolver Closure to resolve class instance.
     */
    public function bindSingleton($alias, $resolver);

    /**
     * Check if desired alias or class name binded in Container. You can bind new alias using
     * Container::bind(), Container::bindSingleton().
     *
     * @param string $alias
     * @return bool
     */
    public function hasBinding($alias);

    /**
     * Check if alias points to constructed instance or singleton instance.
     *
     * @param string $alias
     * @return bool
     */
    public function isInstance($alias);

    /**
     * Remove existed binding.
     *
     * @param string $alias
     */
    public function removeBinding($alias);
}