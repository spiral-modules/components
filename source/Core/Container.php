<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use ReflectionParameter;
use Exception;
use RuntimeException;
use Spiral\Core\Container\ArgumentException;
use ReflectionFunctionAbstract as ContextFunction;
use Spiral\Core\Container\InstanceException;

class Container extends Component implements ContainerInterface
{
    /**
     * Exception  to throw when instance can not be constructed.
     */
    const CODE_NON_INSTANTIABLE = 777;

    /**
     * IoC bindings. Binding can include interface - class aliases, closures, singleton closures
     * and already constructed components stored as instances. Binding can be added using
     * Container::bind() or Container::bindSingleton() methods, every existed binding can be defined
     * or redefined at any moment of application flow.
     *
     * Instance or class name can be also binded to alias, this technique used for all spiral core
     * components and can simplify development. Spiral additionally provides way to create DI without
     * binding, it can be done by using real class or model name, or via ControllableInjection interface.
     *
     * @invisible
     * @var array
     */
    protected $bindings = [];

    /**
     * First constructed container will become global container by default.
     */
    public function __construct()
    {
        if (empty(self::getContainer()))
        {
            self::setContainer($this);
        }
    }

    /**
     * Resolve class instance using IoC container. Class can be requested using it's own name, alias
     * binding, singleton binding, closure function, closure function with singleton resolution, or
     * via InjectableInterface interface. To add binding use Container::bind() or Container::bindSingleton()
     * methods.
     *
     * This method widely used inside spiral core to resolve adapters, handlers and databases.
     *
     * @param string              $alias            Class/interface name or binded alias should be
     *                                              resolved to instance.
     * @param array               $parameters       Parameters to be mapped to class constructor or
     *                                              forwarded to closure.
     * @param ReflectionParameter $context          Context parameter were used to declare DI.
     * @return mixed|null|object
     * @throws InstanceException
     * @throws ArgumentException
     * @throws RuntimeException
     */
    public function get($alias, $parameters = [], ReflectionParameter $context = null)
    {
        if ($alias == ContainerInterface::class)
        {
            return $this;
        }

        if (!isset($this->bindings[$alias]))
        {
            $reflector = new \ReflectionClass($alias);

            /**
             * We have to construct class here. Remember about this magick constant?
             */
            if (!empty($context) && $injector = $reflector->getConstant('INJECTOR'))
            {
                //We can bypass class creation to associated injector
                return call_user_func(
                    [$this->get($injector), 'createInjection'],
                    $reflector,
                    $context,
                    $this
                );
            }

            if (!$reflector->isInstantiable())
            {
                throw new InstanceException("Class '{$alias}' can not be constructed.");
            }

            if (empty($constructor = $reflector->getConstructor()))
            {
                $instance = $reflector->newInstanceArgs($this->resolveArguments(
                    $constructor,
                    $parameters
                ));
            }
            else
            {
                //No constructor specified
                $instance = $reflector->newInstance();
            }

            //Component declared SINGLETON constant, binding as constant value and class name.
            if (!empty($singleton = $reflector->getConstant('SINGLETON')))
            {
                $this->bindings[$reflector->getName()] = $this->bindings[$singleton] = $instance;
            }

            return $instance;
        }

        if (is_object($binding = $this->bindings[$alias]))
        {
            return $binding;
        }

        if (is_string($binding))
        {
            //Binding is pointing to something else
            $instance = $this->get($binding, $parameters, $context);

            if ($instance instanceof Singleton)
            {
                //To prevent double binding
                $this->bindings[$binding] = $this->bindings[get_class($instance)] = $instance;
            }

            return $instance;
        }

        if (is_array($binding))
        {
            if (is_string($binding[0]))
            {
                //Class name
                $instance = $this->get($binding[0], $parameters, $context);
            }
            else
            {
                //Closure
                $instance = call_user_func_array($binding[0], $parameters);
            }

            if ($binding[1])
            {
                //Singleton
                $this->bindings[$alias] = $instance;
            }

            return $instance;
        }

        return null;
    }

    /**
     * Helper method to resolve constructor or function arguments, build required DI using IoC
     * container and mix with pre-defined set of named parameters.
     *
     * @param ContextFunction $reflection Method or constructor should be filled with DI.
     * @param array           $parameters Outside parameters used in priority to DI.
     *                                    Named list.
     * @return array
     * @throws ArgumentException
     * @throws Exception
     */
    public function resolveArguments(ContextFunction $reflection, array $parameters = [])
    {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter)
        {
            $name = $parameter->getName();

            if (!$parameter->getClass())
            {
                if (array_key_exists($name, $parameters))
                {
                    //Scalar value supplied by user
                    $arguments[] = $parameters[$name];
                    continue;
                }

                if ($parameter->isDefaultValueAvailable())
                {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                //Unable to resolve scalar argument value
                throw new ArgumentException($parameter, $reflection);
            }

            if (isset($parameters[$name]) && is_object($parameters[$name]))
            {
                //Supplied by user
                $arguments[] = $parameters[$name];
                continue;
            }

            try
            {
                //Trying to resolve
                $arguments[] = $this->get($parameter->getClass()->getName(), [], $parameter);

                continue;
            }
            catch (Exception $exception)
            {
                if ($exception instanceof InstanceException && $parameter->isDefaultValueAvailable())
                {
                    //Let's try to use default value instead (some interface requested)
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw $exception;
            }
        }

        return $arguments;
    }

    /**
     * IoC binding can create a link between specified alias and method to resolve that alias, resolver
     * can be either class instance (that instance will be resolved as singleton), callback or string
     * alias. String aliases can be used to rewrite core classes with custom realization, or specify
     * what interface child should be used.
     *
     * @param string                 $alias  Alias where singleton will be attached to.
     * @param string|object|callable Closure to resolve class instance, class instance or class name.
     */
    public function bind($alias, $resolver)
    {
        if (is_array($resolver) || $resolver instanceof \Closure)
        {
            $this->bindings[$alias] = [$resolver, false];

            return;
        }

        $this->bindings[$alias] = $resolver;
    }

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
    public function replace($alias, $resolver)
    {
        $payload = [$alias, null];
        if (isset($this->bindings[$alias]))
        {
            $payload[1] = $this->bindings[$alias];
        }

        $this->bind($alias, $resolver);

        return $payload;
    }

    /**
     * Restore previously pulled binding value. Method will accept only result of replace() method.
     *
     * @param mixed $binding
     */
    public function restore($binding)
    {
        list($alias, $resolver) = $binding;

        unset($this->bindings[$alias]);
        if (!empty($resolver))
        {
            //Restoring original value
            $this->bindings[$alias] = $binding;
        }
    }

    /**
     * Bind closure or class name which will be performed only once, after first call class instance
     * will be attached to specified alias and will be returned directly without future invoking.
     *
     * @param string   $alias    Alias where singleton will be attached to.
     * @param callable $resolver Closure to resolve class instance.
     */
    public function bindSingleton($alias, $resolver)
    {
        $this->bindings[$alias] = [$resolver, true];
    }

    /**
     * Check if desired alias or class name binded in Container. You can bind new alias using
     * Container::bind(), Container::bindSingleton().
     *
     * @param string $alias
     * @return bool
     */
    public function hasBinding($alias)
    {
        return isset($this->bindings[$alias]);
    }

    /**
     * Check if alias points to constructed instance or singleton instance.
     *
     * @param string $alias
     * @return bool
     */
    public function isInstance($alias)
    {
        if (!$this->hasBinding($alias))
        {
            return false;
        }

        return is_object($this->bindings[$alias]);
    }

    /**
     * Remove existed binding.
     *
     * @param string $alias
     */
    public function removeBinding($alias)
    {
        unset($this->bindings[$alias]);
    }
}