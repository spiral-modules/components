<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Core;

use Interop\Container\ContainerInterface;
use ReflectionFunctionAbstract as ContextFunction;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Exceptions\ConfigException;
use Spiral\Core\Exceptions\Container\ArgumentException;
use Spiral\Core\Exceptions\Container\AutowireException;
use Spiral\Core\Exceptions\Container\ContainerException;
use Spiral\Core\Exceptions\Container\InjectionException;

/**
 * Super simple auto-wiring container with declarative singletons and injectors integration.
 * Compatible with Container Interop.
 *
 * Container does not support setter injections, private properties and etc. Normally it will work
 * with classes only to be as much invisible as possible.
 *
 * @see  InjectableInterface
 * @see  SingletonInterface
 */
class Container extends Component implements
    ContainerInterface,
    FactoryInterface,
    ResolverInterface,
    ScoperInterface
{
    /**
     * IoC bindings.
     *
     * @what-if private
     * @invisible
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Registered injectors.
     *
     * @what-if private
     * @invisible
     *
     * @var array
     */
    protected $injectors = [];

    /**
     * {@inheritdoc}
     */
    public function has($alias)
    {
        return array_key_exists($alias, $this->bindings);
    }

    /**
     * {@inheritdoc}
     *
     * Context parameter will be passed to class injectors, which makes possible to use this method
     * as:
     * $this->container->get(DatabaseInterface::class, 'default');
     *
     * @param string|null $context Call context.
     */
    public function get($alias, $context = null)
    {
        //Direct bypass to construct, i might think about this option... or not.
        return $this->make($alias, [], $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null $context Related to parameter caused injection if any.
     */
    public function make(string $class, $parameters = [], string $context = null)
    {
        if (!isset($this->bindings[$class])) {
            return $this->autowire($class, $parameters, $context);
        }

        if ($class == ContainerInterface::class && empty($parameters)) {
            //self wrapping
            return $this;
        }

        if (is_object($binding = $this->bindings[$class])) {
            //Singleton
            return $binding;
        }

        if (is_string($binding)) {
            //Binding is pointing to something else
            return $this->make($binding, $parameters, $context);
        }

        //todo: unify using InvokerInterface
        if (is_array($binding)) {
            if (is_string($binding[0])) {
                //Class name
                $instance = $this->make($binding[0], $parameters, $context);
            } elseif ($binding[0] instanceof \Closure) {
                $reflection = new \ReflectionFunction($binding[0]);

                //Invoking Closure
                $instance = $reflection->invokeArgs(
                    $this->resolveArguments($reflection, $parameters, $context)
                );
            } elseif (is_array($binding[0])) {
                //In a form of resolver and method
                list($resolver, $method) = $binding[0];

                $resolver = $this->get($resolver);
                $method = new \ReflectionMethod($resolver, $method);
                $method->setAccessible(true);

                $instance = $method->invokeArgs(
                    $resolver, $this->resolveArguments($method, $parameters, $context)
                );
            } else {
                throw new ContainerException("Invalid binding for {$class}");
            }

            if ($binding[1]) {
                //Declared singleton
                $this->bindings[$class] = $instance;
            }

            if (!is_object($instance)) {
                //Non object bindings are allowed
                return $instance;
            }

            //todo: Do we want to register object here but skip auto singletons?
            return $instance;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $context
     */
    public function resolveArguments(
        ContextFunction $reflection,
        array $parameters = [],
        string $context = null
    ): array {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            try {
                $class = $parameter->getClass();
            } catch (\ReflectionException $e) {
                throw new ContainerException($e->getMessage(), $e->getCode(), $e);
            }

            if (empty($class)) {
                if (array_key_exists($name, $parameters)) {
                    //Scalar value supplied by user
                    $arguments[] = $parameters[$name];
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    //Or default value?
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                //Unable to resolve scalar argument value
                throw new ArgumentException($parameter, $reflection);
            }

            if (isset($parameters[$name]) && is_object($parameters[$name])) {
                //Supplied by user
                $arguments[] = $parameters[$name];
                continue;
            }

            try {
                //Trying to resolve dependency (contextually)
                $arguments[] = $this->get($class->getName(), $parameter->getName());

                continue;
            } catch (AutowireException $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    //Let's try to use default value instead
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw $e;
            }
        }

        return $arguments;
    }

    /**
     * Bind value resolver to container alias. Resolver can be class name (will be constructed
     * every method call), function array or Closure (executed every call). Only object resolvers
     * supported by this method.
     *
     * @param string                $alias
     * @param string|array|callable $resolver
     *
     * @return self
     */
    public function bind(string $alias, $resolver): Container
    {
        if (is_array($resolver) || $resolver instanceof \Closure) {
            $this->bindings[$alias] = [$resolver, false];

            return $this;
        }

        $this->bindings[$alias] = $resolver;

        return $this;
    }

    /**
     * Bind value resolver to container alias to be executed as cached. Resolver can be class name
     * (will be constructed only once), function array or Closure (executed only once call).
     *
     * @param string                $alias
     * @param string|array|callable $resolver
     *
     * @return self
     */
    public function bindSingleton(string $alias, $resolver): Container
    {
        if (is_object($resolver) && !$resolver instanceof \Closure) {
            $this->bindings[$alias] = $resolver;

            return $this;
        }

        $this->bindings[$alias] = [$resolver, true];

        return $this;
    }

    /**
     * Specify binding which has to be used for class injection.
     *
     * @param string        $class
     * @param string|object $injector
     *
     * @return self
     */
    public function bindInjector(string $class, $injector): Container
    {
        if (!is_string($injector)) {
            throw new \InvalidArgumentException('Injector can only be set as string binding');
        }

        $this->injectors[$class] = $injector;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function replace(string $alias, $resolver): array
    {
        $payload = [$alias, null];
        if (isset($this->bindings[$alias])) {
            $payload[1] = $this->bindings[$alias];
        }

        $this->bind($alias, $resolver);

        return $payload;
    }

    /**
     * {@inheritdoc}
     */
    public function restore(array $replacePayload)
    {
        list($alias, $resolver) = $replacePayload;

        unset($this->bindings[$alias]);

        if (!empty($resolver)) {
            //Restoring original value
            $this->bindings[$alias] = $resolver;
        }
    }

    /**
     * Check if alias points to constructed instance (singleton).
     *
     * @param string $alias
     *
     * @return bool
     */
    public function hasInstance(string $alias): bool
    {
        if (!$this->has($alias)) {
            return false;
        }

        //Cross bindings
        while (isset($this->bindings[$alias]) && is_string($this->bindings[$alias])) {
            $alias = $this->bindings[$alias];
        }

        return isset($this->bindings[$alias]) && is_object($this->bindings[$alias]);
    }

    /**
     * @param string $alias
     */
    public function removeBinding(string $alias)
    {
        unset($this->bindings[$alias]);
    }

    /**
     * Every declared Container binding. Must not be used in production code due container format is
     * vary.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Every binded injector.
     *
     * @return array
     */
    public function getInjectors(): array
    {
        return $this->injectors;
    }

    /**
     * Automatically create class.
     *
     * @param string $class
     * @param array  $parameters
     * @param string $context
     *
     * @return object
     *
     * @throws AutowireException
     */
    protected function autowire(string $class, array $parameters, string $context = null)
    {
        if (!class_exists($class)) {
            throw new AutowireException("Undefined class or binding '{$class}'");
        }

        //OK, we can create class by ourselves
        $instance = $this->createInstance($class, $parameters, $context, $reflector);

        if (empty($reflector)) {
            throw new ConfigException("Unable to receive ReflectionClass instance");
        }

        return $this->registerInstance($instance, $reflector, $parameters);
    }

    /**
     * Check if given class has associated injector.
     *
     * @param \ReflectionClass $reflection
     *
     * @return bool
     */
    protected function hasInjector(\ReflectionClass $reflection): bool
    {
        if (isset($this->injectors[$reflection->getName()])) {
            return true;
        }

        return $reflection->isSubclassOf(InjectableInterface::class);
    }

    /**
     * Get injector associated with given class.
     *
     * @param \ReflectionClass $reflection
     *
     * @return InjectorInterface
     */
    protected function getInjector(\ReflectionClass $reflection): InjectorInterface
    {
        if (isset($this->injectors[$reflection->getName()])) {
            $injector = $this->get($this->injectors[$reflection->getName()]);
        } else {
            $injector = $this->get($reflection->getConstant('INJECTOR'));
        }

        if (!$injector instanceof InjectorInterface) {
            throw new InjectionException(
                "Class '" . get_class($injector) . "' must be an instance of InjectorInterface for '{$reflection->getName()}'"
            );
        }

        return $injector;
    }

    /**
     * Create instance of desired class.
     *
     * @param string           $class
     * @param array            $parameters Constructor parameters.
     * @param string|null      $context
     * @param \ReflectionClass $reflection Instance of reflection associated with class,
     *                                     reference.
     *
     * @return object
     *
     * @throws ContainerException
     */
    private function createInstance(
        string $class,
        array $parameters,
        string $context = null,
        \ReflectionClass &$reflection = null
    ) {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new ContainerException($e->getMessage(), $e->getCode(), $e);
        }

        //We have to construct class using external injector
        if (empty($parameters) && $this->hasInjector($reflection)) {
            //Creating class using injector/factory
            $instance = $this->getInjector($reflection)->createInjection(
                $reflection,
                $context
            );

            if (!$reflection->isInstance($instance)) {
                throw new InjectionException("Invalid injection response for '{$reflection->getName()}'");
            }

            return $instance;
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class '{$class}' can not be constructed");
        }

        if (!empty($constructor = $reflection->getConstructor())) {
            //Using constructor with resolved arguments
            $instance = $reflection->newInstanceArgs(
                $this->resolveArguments($constructor, $parameters)
            );
        } else {
            //No constructor specified
            $instance = $reflection->newInstance();
        }

        return $instance;
    }

    /**
     * Make sure instance conditions are met.
     *
     * @param object           $instance
     * @param \ReflectionClass $reflector
     * @param array            $parameters
     *
     * @return object
     */
    private function registerInstance($instance, \ReflectionClass $reflector, array $parameters)
    {
        if (empty($parameters) && $instance instanceof SingletonInterface) {
            $singleton = get_class($instance);

            if (!isset($this->bindings[$singleton])) {
                $this->bindings[$singleton] = $instance;
            }
        }

        //todo: additional registration operations?

        return $instance;
    }
}
